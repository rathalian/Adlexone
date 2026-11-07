<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Application\Communications;
use Adlexone\Database\Database;


final class RuleRunner
{


    /**
     * Run rules for an item event.
     *
     * A log line posted with an update also runs log rules, because the item
     * form saves the log and then reports an update.
     */
    public static function run(int $itemId, string $requestingAction): void
    {
        $event = Rules::REQUESTS[$requestingAction] ?? '';
        if ($itemId <= 0 || $event === '' || !self::ready()) {
            return;
        }
        $events = [$event];
        $posted = is_array($_POST ?? null) ? $_POST : [];
        if ($event === Rules::EVENT_UPDATE && trim((string) ($posted['log_entry'] ?? '')) !== '') {
            $events[] = Rules::EVENT_LOG;
        }
        $context = self::context($itemId, $event);
        if ($context === null) {
            return;
        }
        $rules = self::rulesFor((int) $context['type_id'], $events);
        $fired = [];
        for ($pass = 0; $pass < 4; $pass++) {
            $moved = false;
            foreach ($rules as $rule) {
                $ruleId = (int) ($rule['id'] ?? 0);
                if ($ruleId > 0 && isset($fired[$ruleId])) {
                    continue;
                }
                if (!self::matchesState($rule, $context['current'], $context['previous'], $itemId)) {
                    continue;
                }
                if ($ruleId > 0) {
                    $fired[$ruleId] = true;
                }
                $moved = self::perform($itemId, $rule, $context['current'], true) || $moved;
            }
            if (!$moved) {
                break;
            }
        }
    }


    /**
     * Explain a rule against the record as it is stored now.
     *
     * @return array<string, mixed>
     */
    public static function preview(int $ruleId, int $applicationId, int $itemId): array
    {
        $rule = Rules::find($ruleId, $applicationId);
        if ($rule === null) {
            return ['ok' => false, 'matches' => false, 'reason' => 'That rule is not on this application.', 'steps' => []];
        }
        $stored = self::loadItem($itemId);
        if ($stored === null || (int) ($stored['item_type_id'] ?? 0) !== (int) $rule['item_type_id']) {
            return ['ok' => false, 'matches' => false, 'reason' => 'Choose a record of this rule\'s type.', 'steps' => []];
        }
        $matches = self::matchesState($rule, $stored, $stored, $itemId);
        $reason = $matches
            ? 'This record matches the rule as it stands now.'
            : self::mismatch($rule, $stored, $stored, $itemId);
        $steps = [];
        $skip = (int) ($rule['skip_actor'] ?? 1) === 1;
        foreach (Rules::stepsOf($rule) as $step) {
            $steps[] = [
                'outcome' => (string) ($step['outcome'] ?? ''),
                'detail' => self::describeStep($itemId, $step, $stored, false, $skip),
            ];
        }
        return [
            'ok' => true,
            'matches' => $matches,
            'reason' => $reason,
            'title' => (string) ($stored['item_title'] ?? ''),
            'steps' => $steps,
        ];
    }


    /**
     * Run one rule against the stored record when it matches.
     *
     * @return array<string, mixed>
     */
    public static function executeOne(int $ruleId, int $applicationId, int $itemId): array
    {
        $preview = self::preview($ruleId, $applicationId, $itemId);
        if (($preview['ok'] ?? false) !== true || ($preview['matches'] ?? false) !== true) {
            $preview['ran'] = false;
            return $preview;
        }
        $rule = Rules::find($ruleId, $applicationId);
        $stored = self::loadItem($itemId);
        if ($rule === null || $stored === null) {
            $preview['ran'] = false;
            return $preview;
        }
        self::perform($itemId, $rule, $stored, true);
        $preview['ran'] = true;
        $preview['reason'] = 'Ran against ' . (string) ($stored['item_title'] ?? ('record ' . $itemId)) . '.';
        return $preview;
    }


    /**
     * Pure condition check. Group tests can pass group_security on either side.
     *
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     * @param array<string, mixed> $previous
     */
    public static function matchesState(array $rule, array $current, array $previous, int $itemId = 0): bool
    {
        if ((string) ($rule['event'] ?? '') === Rules::EVENT_LOG) {
            if (trim((string) ($current['log_entry'] ?? '')) === '') {
                return false;
            }
            $min = (int) ($rule['min_log_role'] ?? 0);
            if ($min > 0 && (int) ($current['role_id'] ?? 0) < $min) {
                return false;
            }
        }
        $operator = (string) ($rule['operator'] ?? Rules::OP_ALWAYS);
        if ($operator === Rules::OP_ALWAYS) {
            return true;
        }
        $key = (string) ($rule['field_key'] ?? '');
        if ($key === '') {
            return false;
        }
        $now = self::readValue($key, $current, $itemId);
        $then = self::readValue($key, $previous, $itemId);
        $want = trim((string) ($rule['match_value'] ?? ''));
        $from = trim((string) ($rule['match_from'] ?? ''));
        return match ($operator) {
            Rules::OP_EQ => self::equalsValue($key, $now, $want),
            Rules::OP_NEQ => !self::equalsValue($key, $now, $want),
            Rules::OP_BECOMES => self::equalsValue($key, $now, $want) && !self::equalsValue($key, $then, $want),
            Rules::OP_EMPTY => self::isEmpty($key, $now),
            Rules::OP_NOT_EMPTY => !self::isEmpty($key, $now),
            Rules::OP_CONTAINS => self::containsValue($key, $now, $want),
            Rules::OP_CHANGED => !$now['same']($then),
            Rules::OP_FROM_TO => self::equalsValue($key, $then, $from) && self::equalsValue($key, $now, $want) && !$now['same']($then),
            default => false,
        };
    }


    /**
     * @param list<string> $events
     * @return list<array<string, mixed>>
     */
    private static function rulesFor(int $typeId, array $events): array
    {
        $events = array_values(array_unique($events));
        if ($typeId <= 0 || $events === []) {
            return [];
        }
        $marks = implode(',', array_fill(0, count($events), '?'));
        $rules = Database::select(
            'application_rules',
            '*',
            'item_type_id = ? AND enabled = 1 AND event IN (' . $marks . ')',
            array_merge([$typeId], $events),
            'sort_order ASC, id ASC'
        );
        return Rules::withSteps($rules);
    }


    /**
     * @return array{type_id: int, current: array<string, mixed>, previous: array<string, mixed>}|null
     */
    private static function context(int $itemId, string $event): ?array
    {
        $stored = self::loadItem($itemId);
        if ($stored === null) {
            return null;
        }
        $posted = is_array($_POST ?? null) ? $_POST : [];
        $current = array_merge($stored, $posted);
        $previous = $event === Rules::EVENT_CREATE ? [] : $stored;
        return [
            'type_id' => (int) ($stored['item_type_id'] ?? 0),
            'current' => $current,
            'previous' => $previous,
        ];
    }


    /**
     * @return array<string, mixed>|null
     */
    private static function loadItem(int $itemId): ?array
    {
        if ($itemId <= 0) {
            return null;
        }
        $stored = Database::first('items', '*', 'item_id = ?', [$itemId]);
        if ($stored === null) {
            return null;
        }
        return ItemFields::hydrate($stored);
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     */
    private static function perform(int $itemId, array $rule, array &$current, bool $write): bool
    {
        $lines = [];
        $moved = false;
        $ok = 0;
        $failed = 0;
        foreach (Rules::stepsOf($rule) as $step) {
            $result = self::runStep($itemId, $rule, $step, $current, $write);
            $lines[] = $result['detail'];
            if ($result['moved']) {
                $moved = true;
            }
            if ($result['status'] === 'failed') {
                $failed++;
            } elseif ($result['status'] !== 'skipped') {
                $ok++;
            }
        }
        if ($write) {
            $status = $ok > 0 ? 'done' : ($failed > 0 ? 'failed' : 'skipped');
            self::recordRun((int) ($rule['id'] ?? 0), $itemId, $status, implode("\n", $lines));
        }
        return $moved;
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     * @return array{status: string, detail: string, moved: bool}
     */
    private static function runStep(int $itemId, array $rule, array $step, array &$current, bool $write): array
    {
        $outcome = (string) ($step['outcome'] ?? '');
        if ($outcome === Rules::OUTCOME_EMAIL) {
            $detail = self::sendEmail($itemId, $rule, $step, $current, $write);
            $status = str_starts_with($detail, 'failed') ? 'failed' : (str_starts_with($detail, 'skipped') ? 'skipped' : 'done');
            return ['status' => $status, 'detail' => $detail, 'moved' => false];
        }
        if ($outcome === Rules::OUTCOME_SET_FIELD) {
            $skip = (int) ($rule['skip_actor'] ?? 1) === 1;
            if (!$write) {
                return ['status' => 'done', 'detail' => self::describeStep($itemId, $step, $current, false, $skip), 'moved' => false];
            }
            $moved = self::setField($itemId, $rule, $step, $current);
            return [
                'status' => $moved ? 'done' : 'skipped',
                'detail' => $moved
                    ? self::describeStep($itemId, $step, $current, true, $skip)
                    : 'Skipped setting a field. The value is already set, or the field is not on this record.',
                'moved' => $moved,
            ];
        }
        if ($outcome === Rules::OUTCOME_SET_OWNER) {
            $skip = (int) ($rule['skip_actor'] ?? 1) === 1;
            if (!$write) {
                return ['status' => 'done', 'detail' => self::describeStep($itemId, $step, $current, false, $skip), 'moved' => false];
            }
            $moved = self::setOwner($itemId, $rule, $step, $current);
            return [
                'status' => $moved ? 'done' : 'skipped',
                'detail' => $moved
                    ? self::describeStep($itemId, $step, $current, true, $skip)
                    : 'Skipped setting the owner. They are already the owner, or that person is missing.',
                'moved' => $moved,
            ];
        }
        if ($outcome === Rules::OUTCOME_STAMP) {
            $skip = (int) ($rule['skip_actor'] ?? 1) === 1;
            if (!$write) {
                return ['status' => 'done', 'detail' => self::describeStep($itemId, $step, $current, false, $skip), 'moved' => false];
            }
            $dated = $step;
            $dated['action_value'] = Rules::today();
            $moved = self::setField($itemId, $rule, $dated, $current);
            return [
                'status' => $moved ? 'done' : 'skipped',
                'detail' => $moved
                    ? self::describeStep($itemId, $step, $current, true, $skip)
                    : 'Skipped the date. It is already today, or the field is not on this record.',
                'moved' => $moved,
            ];
        }
        if ($outcome === Rules::OUTCOME_ANNOUNCE) {
            $skip = (int) ($rule['skip_actor'] ?? 1) === 1;
            if (!$write) {
                return ['status' => 'done', 'detail' => self::describeStep($itemId, $step, $current, false, $skip), 'moved' => false];
            }
            $posted = self::postNotice($itemId, $rule, $step, $current);
            return [
                'status' => $posted ? 'done' : 'skipped',
                'detail' => $posted
                    ? self::describeStep($itemId, $step, $current, true, $skip)
                    : 'Skipped the notice. It needs a subject or a message.',
                'moved' => false,
            ];
        }
        $skip = (int) ($rule['skip_actor'] ?? 1) === 1;
        if (!$write) {
            return ['status' => 'done', 'detail' => self::describeStep($itemId, $step, $current, false, $skip), 'moved' => false];
        }
        $wrote = self::writeLog($itemId, $rule, $step, $current);
        return [
            'status' => $wrote ? 'done' : 'skipped',
            'detail' => $wrote ? self::describeStep($itemId, $step, $current, true, $skip) : 'Skipped the log line. The message was empty.',
            'moved' => false,
        ];
    }


    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     */
    private static function describeStep(int $itemId, array $step, array $current, bool $past, bool $skipActor = true): string
    {
        $outcome = (string) ($step['outcome'] ?? '');
        if ($outcome === Rules::OUTCOME_EMAIL) {
            $to = self::recipientAddresses($step, $current, $itemId, $skipActor);
            $who = $to === [] ? 'nobody' : implode(', ', $to);
            $subject = self::fill((string) ($step['subject'] ?? ''), $itemId, $current);
            $verb = $past ? 'Emailed' : 'Would email';
            return $verb . ' ' . $who . ($subject !== '' ? '. Subject: ' . $subject : '.');
        }
        if ($outcome === Rules::OUTCOME_SET_FIELD) {
            $label = Rules::fieldLabel((string) ($step['action_field'] ?? ''));
            $value = (string) ($step['action_value'] ?? '');
            if (trim($value) === '') {
                return ($past ? 'Cleared ' : 'Would clear ') . $label . '.';
            }
            $verb = $past ? 'Set' : 'Would set';
            return $verb . ' ' . $label . ' to ' . $value . '.';
        }
        if ($outcome === Rules::OUTCOME_SET_OWNER) {
            $name = Rules::ownerLabel((string) ($step['action_value'] ?? ''), $current);
            $verb = $past ? 'Set the owner to ' : 'Would set the owner to ';
            return $verb . $name . '.';
        }
        if ($outcome === Rules::OUTCOME_STAMP) {
            $key = (string) ($step['action_field'] ?? '');
            $label = Rules::fieldLabel($key);
            $when = $past ? trim((string) ($current[$key] ?? '')) : '';
            if ($when === '') {
                $when = Rules::today();
            }
            $verb = $past ? 'Stamped' : 'Would stamp';
            return $verb . ' ' . $label . ' with ' . $when . '.';
        }
        if ($outcome === Rules::OUTCOME_ANNOUNCE) {
            $subject = self::fill((string) ($step['subject'] ?? ''), $itemId, $current);
            $verb = $past ? 'Posted a notice' : 'Would post a notice';
            return $verb . ($subject !== '' ? ': ' . $subject : '') . '.';
        }
        $text = self::fill((string) ($step['body'] ?? ''), $itemId, $current);
        $verb = $past ? 'Wrote a log line: ' : 'Would write a log line: ';
        return $verb . ($text !== '' ? $text : '(empty)');
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     * @param array<string, mixed> $previous
     */
    private static function mismatch(array $rule, array $current, array $previous, int $itemId): string
    {
        if ((string) ($rule['event'] ?? '') === Rules::EVENT_LOG && trim((string) ($current['log_entry'] ?? '')) === '') {
            return 'A log rule matches when a log line is being added. This record is only being read.';
        }
        $operator = (string) ($rule['operator'] ?? '');
        if ($operator === Rules::OP_BECOMES || $operator === Rules::OP_CHANGED || $operator === Rules::OP_FROM_TO) {
            return 'This record already has its current values, so a change has not just happened. Save the record to see the rule fire, or pick a condition such as Equals to try the stored value.';
        }
        $key = (string) ($rule['field_key'] ?? '');
        $now = $key === '' ? '' : self::readValue($key, $current, $itemId)['text'];
        return 'Does not match. Current value is "' . $now . '".';
    }


    /**
     * @param array<string, mixed> $row
     * @return array{text: string, ids: list<int>, same: callable}
     */
    private static function readValue(string $key, array $row, int $itemId): array
    {
        if ($key === 'owner') {
            $id = (int) ($row['user_security'] ?? 0);
            return self::scalarValue((string) $id);
        }
        if ($key === 'creator') {
            $id = (int) ($row['creator_security'] ?? 0);
            return self::scalarValue((string) $id);
        }
        if ($key === 'groups') {
            $ids = self::groupIdsFrom($row, $itemId);
            $copy = $ids;
            sort($copy);
            return [
                'text' => implode(',', $copy),
                'ids' => $copy,
                'same' => static function (array $other) use ($copy): bool {
                    return $copy === ($other['ids'] ?? []);
                },
            ];
        }
        $text = trim((string) ($row[$key] ?? ''));
        return self::scalarValue($text);
    }


    /**
     * @return array{text: string, ids: list<int>, same: callable}
     */
    private static function scalarValue(string $text): array
    {
        return [
            'text' => $text,
            'ids' => [],
            'same' => static function (array $other) use ($text): bool {
                return $text === (string) ($other['text'] ?? '');
            },
        ];
    }


    /**
     * @param array<string, mixed> $row
     * @return list<int>
     */
    private static function groupIdsFrom(array $row, int $itemId): array
    {
        if (array_key_exists('group_security', $row)) {
            return GroupMembership::parseDelimited((string) $row['group_security']);
        }
        if (isset($row['groups']) && is_array($row['groups'])) {
            return array_values(array_unique(array_filter(array_map('intval', $row['groups']))));
        }
        if ($itemId > 0 && Database::tableExists('item_groups')) {
            return GroupMembership::itemGroupIds($itemId);
        }
        return [];
    }


    /**
     * @param array{text: string, ids: list<int>} $value
     */
    private static function equalsValue(string $key, array $value, string $want): bool
    {
        if ($key === 'groups') {
            return in_array((int) $want, $value['ids'], true);
        }
        return $value['text'] === $want;
    }


    /**
     * @param array{text: string, ids: list<int>} $value
     */
    private static function isEmpty(string $key, array $value): bool
    {
        if ($key === 'groups') {
            return $value['ids'] === [];
        }
        if ($key === 'owner' || $key === 'creator') {
            return (int) $value['text'] <= 0;
        }
        return $value['text'] === '';
    }


    /**
     * @param array{text: string, ids: list<int>} $value
     */
    private static function containsValue(string $key, array $value, string $want): bool
    {
        if ($want === '') {
            return false;
        }
        if ($key === 'groups') {
            $names = [];
            foreach ($value['ids'] as $id) {
                $names[] = Rules::groups()[$id] ?? '';
            }
            return stripos(implode(', ', $names), $want) !== false;
        }
        if ($key === 'owner' || $key === 'creator') {
            $name = Rules::userName((int) $value['text']);
            return stripos($name, $want) !== false || $value['text'] === $want;
        }
        return stripos($value['text'], $want) !== false;
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     */
    private static function sendEmail(int $itemId, array $rule, array $step, array $current, bool $write): string
    {
        $skip = (int) ($rule['skip_actor'] ?? 1) === 1;
        $to = self::recipientAddresses($step, $current, $itemId, $skip);
        $subject = self::fill((string) ($step['subject'] ?? ''), $itemId, $current);
        if ($subject === '') {
            $subject = 'ITEM_ID ' . $itemId;
        }
        $body = self::fill((string) ($step['body'] ?? ''), $itemId, $current);
        if ($to === []) {
            return 'skipped: nobody to email.';
        }
        $host = defined('SET_SMTP_HOST') ? trim((string) SET_SMTP_HOST) : '';
        $from = defined('SET_SMTP_USER') ? trim((string) SET_SMTP_USER) : '';
        if (!$write || $host === '' || str_starts_with(strtolower($host), 'enter ') || !str_contains($from, '@')) {
            $verb = $write ? 'skipped: mail is not configured.' : 'Would email';
            return $verb . ' ' . implode(', ', $to) . '. Subject: ' . $subject;
        }
        $sent = [];
        $failed = [];
        foreach ($to as $address) {
            $ok = Communications::sendSMTPMail(
                $host,
                (int) (defined('SET_SMTP_PORT') ? SET_SMTP_PORT : 25),
                $from,
                defined('SET_SMTP_PASSWORD') ? (string) SET_SMTP_PASSWORD : '',
                $address,
                $subject,
                $body,
                $from,
                true
            );
            if ($ok) {
                $sent[] = $address;
            } else {
                $failed[] = $address;
            }
        }
        if ($sent === []) {
            return 'failed: mail server refused ' . implode(', ', $failed) . '.';
        }
        $detail = 'Emailed ' . implode(', ', $sent) . '. Subject: ' . $subject;
        if ($failed !== []) {
            $detail .= ' Failed: ' . implode(', ', $failed) . '.';
        }
        return $detail;
    }


    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     * @return list<string>
     */
    private static function recipientAddresses(array $step, array $current, int $itemId, bool $skipActor): array
    {
        $kind = (string) ($step['recipient'] ?? Rules::TO_OWNER);
        $emails = [];
        $addUser = static function (int $userId) use (&$emails): void {
            foreach (self::emailsForUsers([$userId]) as $email) {
                $emails[$email] = $email;
            }
        };
        $addGroups = static function (array $groupIds) use (&$emails): void {
            foreach (self::emailsForGroups($groupIds) as $email) {
                $emails[$email] = $email;
            }
        };
        $ownerId = (int) ($current['user_security'] ?? 0);
        $creatorId = (int) ($current['creator_security'] ?? 0);
        if ($kind === Rules::TO_ADDRESS) {
            foreach (preg_split('/[,;]+/', (string) ($step['address'] ?? '')) ?: [] as $part) {
                $part = trim($part);
                if (str_contains($part, '@')) {
                    $emails[$part] = $part;
                }
            }
        } elseif ($kind === Rules::TO_USER) {
            $addUser((int) ($step['user_id'] ?? 0));
        } elseif ($kind === Rules::TO_GROUP) {
            $addGroups([(int) ($step['group_id'] ?? 0)]);
        } elseif ($kind === Rules::TO_CREATOR) {
            $addUser($creatorId);
        } elseif ($kind === Rules::TO_BOTH) {
            $addUser($ownerId);
            $addUser($creatorId);
        } elseif ($kind === Rules::TO_OWNER_GROUPS) {
            $addGroups(GroupMembership::userGroupIds($ownerId));
        } elseif ($kind === Rules::TO_OWNER_AND_GROUPS) {
            $addUser($ownerId);
            $addGroups(GroupMembership::userGroupIds($ownerId));
        } elseif ($kind === Rules::TO_CREATOR_GROUPS) {
            $addGroups(GroupMembership::userGroupIds($creatorId));
        } elseif ($kind === Rules::TO_BOTH_GROUPS) {
            $addGroups(GroupMembership::userGroupIds($ownerId));
            $addGroups(GroupMembership::userGroupIds($creatorId));
        } elseif ($kind === Rules::TO_ITEM_GROUPS) {
            $addGroups(self::groupIdsFrom($current, $itemId));
        } else {
            $addUser($ownerId);
        }
        if ($skipActor) {
            $actor = (int) ($_SESSION['access_user_id'] ?? 0);
            $keep = [];
            if (self::recipientIncludesOwner($kind) && $actor > 0 && $actor === $ownerId) {
                foreach (self::emailsForUsers([$ownerId]) as $email) {
                    $keep[$email] = true;
                }
            }
            foreach (self::emailsForUsers([$actor]) as $email) {
                if (!isset($keep[$email])) {
                    unset($emails[$email]);
                }
            }
        }
        return array_values($emails);
    }


    /**
     * The owner is a recipient, including when that person made the change.
     */
    private static function recipientIncludesOwner(string $kind): bool
    {
        return in_array($kind, [Rules::TO_OWNER, Rules::TO_BOTH, Rules::TO_OWNER_AND_GROUPS, ''], true);
    }


    /**
     * @param list<int> $userIds
     * @return list<string>
     */
    private static function emailsForUsers(array $userIds): array
    {
        $emails = [];
        foreach ($userIds as $userId) {
            if ($userId <= 0 || !Database::tableExists('users')) {
                continue;
            }
            $user = Database::first('users', ['email'], 'user_id = ?', [$userId]);
            $email = trim((string) ($user['email'] ?? ''));
            if (str_contains($email, '@')) {
                $emails[$email] = $email;
            }
        }
        return array_values($emails);
    }


    /**
     * @param list<int> $groupIds
     * @return list<string>
     */
    private static function emailsForGroups(array $groupIds): array
    {
        $emails = [];
        foreach ($groupIds as $groupId) {
            if ($groupId <= 0 || !Database::tableExists('user_groups')) {
                continue;
            }
            $rows = Database::rows(
                'SELECT u.email FROM users u INNER JOIN user_groups ug ON ug.user_id = u.user_id WHERE ug.group_id = ?',
                [(int) $groupId]
            );
            foreach ($rows as $row) {
                $email = trim((string) ($row['email'] ?? ''));
                if (str_contains($email, '@')) {
                    $emails[$email] = $email;
                }
            }
        }
        return array_values($emails);
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     */
    private static function setField(int $itemId, array $rule, array $step, array &$current): bool
    {
        $key = (string) ($step['action_field'] ?? '');
        $value = (string) ($step['action_value'] ?? '');
        $typeId = (int) ($current['item_type_id'] ?? 0);
        if ($key === '' || $typeId <= 0) {
            return false;
        }
        if ($key === 'item_title') {
            if ($value === '' || (string) ($current['item_title'] ?? '') === $value) {
                return false;
            }
            Database::update('items', ['item_title' => $value], 'item_id = ?', [$itemId]);
            $current['item_title'] = $value;
            self::keepPosted($key, $value);
            return true;
        }
        $fieldId = ItemFields::fieldIdFromKey($key);
        if ($fieldId === null) {
            return false;
        }
        $onType = Database::first(
            'item_type_custom_fields',
            ['id'],
            'item_type_id = ? AND custom_field_id = ?',
            [$typeId, $fieldId]
        );
        if ($onType === null || (string) ($current[$key] ?? '') === $value) {
            return false;
        }
        ItemFields::saveFromArray($itemId, [$key => $value]);
        $current[$key] = $value;
        self::keepPosted($key, $value);
        return true;
    }


    /**
     * The record form writes $_POST after the rule has run. Keep the value the rule just set.
     */
    private static function keepPosted(string $key, string $value): void
    {
        if ($key === '' || !is_array($_POST ?? null)) {
            return;
        }
        $_POST[$key] = $value;
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     */
    private static function setOwner(int $itemId, array $rule, array $step, array &$current): bool
    {
        $target = (string) ($step['action_value'] ?? '');
        $userId = $target === Rules::OWNER_CREATOR
            ? (int) ($current['creator_security'] ?? 0)
            : (ctype_digit($target) ? (int) $target : 0);
        if ($userId <= 0 || (int) ($current['user_security'] ?? 0) === $userId) {
            return false;
        }
        if (Database::first('users', ['user_id'], 'user_id = ?', [$userId]) === null) {
            return false;
        }
        Database::update('items', ['user_security' => $userId], 'item_id = ?', [$itemId]);
        $current['user_security'] = $userId;
        self::keepPosted('user_security', (string) $userId);
        return true;
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     */
    private static function postNotice(int $itemId, array $rule, array $step, array $current): bool
    {
        if (!Database::tableExists('announcements')) {
            return false;
        }
        $subject = self::fill((string) ($step['subject'] ?? ''), $itemId, $current);
        $message = self::fill((string) ($step['body'] ?? ''), $itemId, $current);
        if ($subject === '' && $message === '') {
            return false;
        }
        $slug = '';
        $applicationId = (int) ($rule['application_id'] ?? 0);
        if ($applicationId > 0 && Database::tableExists('applications')) {
            $app = Database::first('applications', ['slug'], 'application_id = ?', [$applicationId]);
            $slug = (string) ($app['slug'] ?? '');
        }
        Database::insert('announcements', [
            'time' => time(),
            'subject' => $subject,
            'message' => $message,
            'type' => '',
            'application' => $slug,
        ]);

        return true;
    }


    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     */
    private static function writeLog(int $itemId, array $rule, array $step, array $current): bool
    {
        $text = self::fill((string) ($step['body'] ?? ''), $itemId, $current);
        if ($text === '' || !Database::tableExists('core_log')) {
            return false;
        }
        $sequence = Database::newID('core_log', 'log_item_sequence', 'item_id = ' . $itemId);
        $now = time();
        $entry = [
            'item_id' => $itemId,
            'create_date' => (string) $now,
            'item_identifier' => 'rule-' . (int) ($rule['id'] ?? 0),
            'log_item_sequence' => $sequence,
            'log_text' => $text,
            'security_id' => (int) ($_SESSION['access_user_id'] ?? 0),
            'role_id' => (int) ($step['role_id'] ?? 5),
        ];
        if (Database::columnExists('core_log', 'group_id')) {
            $entry['group_id'] = (int) ($step['group_id'] ?? 0);
        }
        Database::insert('core_log', $entry);
        Database::update('items', ['core_log_updated' => $now], 'item_id = ?', [$itemId]);
        return true;
    }


    /**
     * @param array<string, mixed> $current
     */
    private static function fill(string $text, int $itemId, array $current): string
    {
        $replace = [
            'ITEM_ID' => (string) $itemId,
            'ITEM_TITLE' => (string) ($current['item_title'] ?? ''),
            'ITEM_CREATOR' => Rules::userName((int) ($current['creator_security'] ?? 0)),
            'ITEM_OWNER' => Rules::userName((int) ($current['user_security'] ?? 0)),
        ];
        foreach ($current as $key => $value) {
            if (!is_string($key) || is_array($value)) {
                continue;
            }
            if (preg_match('/^custom_field_(\d+)$/', $key, $match) === 1) {
                $replace['CUSTOM_FIELD_' . $match[1]] = (string) $value;
            }
        }
        return strtr($text, $replace);
    }


    private static function recordRun(int $ruleId, int $itemId, string $status, string $detail): void
    {
        if ($ruleId <= 0 || $itemId <= 0 || !Database::tableExists('application_rule_runs')) {
            return;
        }
        Database::insert('application_rule_runs', [
            'rule_id' => $ruleId,
            'item_id' => $itemId,
            'ran_at' => time(),
            'status' => $status,
            'detail' => $detail,
        ]);
        Database::run(
            'DELETE FROM application_rule_runs WHERE rule_id = ? AND id NOT IN (
                SELECT id FROM application_rule_runs WHERE rule_id = ? ORDER BY id DESC LIMIT 100
            )',
            [$ruleId, $ruleId]
        );
    }


    private static function ready(): bool
    {
        return Database::tableExists('application_rules');
    }
}
