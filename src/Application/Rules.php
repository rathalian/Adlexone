<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Data\ItemFields;
use Adlexone\support\Communications;
use Adlexone\support\Database;

/**
 * Per-application workflow rules.
 *
 * A rule is columns: when (event), if (field, operator, value), then
 * (email, log, set a field, or set the owner).
 * Older action_definitions rows keep their package files and }-{ strings.
 */
final class Rules
{
    public const EVENT_CREATE = 'create';
    public const EVENT_UPDATE = 'update';
    public const EVENT_LOG = 'log';
    public const EVENT_ATTACHMENT = 'attachment';

    public const OP_ALWAYS = 'always';
    public const OP_EQ = 'eq';
    public const OP_NEQ = 'neq';
    public const OP_BECOMES = 'becomes';

    public const OUTCOME_EMAIL = 'email';
    public const OUTCOME_LOG = 'log';
    public const OUTCOME_SET_FIELD = 'set_field';
    public const OUTCOME_SET_OWNER = 'set_owner';

    public const OWNER_CREATOR = 'creator';

    public const TO_OWNER = 'owner';
    public const TO_CREATOR = 'creator';
    public const TO_ADDRESS = 'address';

    /** @var array<string, string> */
    private const REQUESTS = [
        'create_item' => self::EVENT_CREATE,
        'update_item' => self::EVENT_UPDATE,
        'update_item_log_entry' => self::EVENT_LOG,
        'item_attachment' => self::EVENT_ATTACHMENT,
    ];

    /**
     * @return array<string, string>
     */
    public static function events(): array
    {
        return [
            self::EVENT_CREATE => 'Record is created',
            self::EVENT_UPDATE => 'Record is updated',
            self::EVENT_LOG => 'A log line is added',
            self::EVENT_ATTACHMENT => 'A file is attached',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function operators(): array
    {
        return [
            self::OP_ALWAYS => 'Always',
            self::OP_EQ => 'Field equals',
            self::OP_NEQ => 'Field does not equal',
            self::OP_BECOMES => 'Field becomes',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function outcomes(): array
    {
        return [
            self::OUTCOME_EMAIL => 'Send an email',
            self::OUTCOME_LOG => 'Write a log line',
            self::OUTCOME_SET_FIELD => 'Set a field',
            self::OUTCOME_SET_OWNER => 'Set the owner',
        ];
    }

    /**
     * Who a "set the owner" rule can assign.
     *
     * @return array<string, string> stored value => label
     */
    public static function ownerChoices(): array
    {
        $choices = [
            self::OWNER_CREATOR => 'The creator',
        ];
        foreach (Database::select('users', ['user_id', 'user_name', 'first_name', 'last_name'], '', [], 'user_name ASC') as $user) {
            $id = (int) ($user['user_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($user['user_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            }
            $choices[(string) $id] = $name !== '' ? $name : ('User ' . $id);
        }
        return $choices;
    }

    /**
     * One line a person can read: when, if, then.
     *
     * @param array<string, mixed> $rule
     * @param array<string, string> $fieldLabels
     */
    public static function summary(array $rule, array $fieldLabels): string
    {
        $events = self::events();
        $operators = self::operators();
        $when = $events[(string) ($rule['event'] ?? '')] ?? (string) ($rule['event'] ?? '');
        if ((string) ($rule['operator'] ?? '') === self::OP_ALWAYS) {
            $condition = 'Always';
        } else {
            $field = $fieldLabels[(string) ($rule['field_key'] ?? '')] ?? (string) ($rule['field_key'] ?? '');
            $op = strtolower($operators[(string) ($rule['operator'] ?? '')] ?? (string) ($rule['operator'] ?? ''));
            $condition = trim($field . ' ' . $op . ' ' . (string) ($rule['match_value'] ?? ''));
        }
        $then = match ((string) ($rule['outcome'] ?? '')) {
            self::OUTCOME_EMAIL => 'Email ' . strtolower(self::recipients()[(string) ($rule['recipient'] ?? '')] ?? 'the owner'),
            self::OUTCOME_SET_FIELD => 'Set ' . ($fieldLabels[(string) ($rule['action_field'] ?? '')] ?? 'a field')
                . ' to ' . (string) ($rule['action_value'] ?? ''),
            self::OUTCOME_SET_OWNER => 'Set the owner to ' . (self::ownerChoices()[(string) ($rule['action_value'] ?? '')] ?? 'a person'),
            default => 'Write a log line',
        };

        return $when . '. ' . $condition . '. ' . $then . '.';
    }

    /**
     * @return array<string, string>
     */
    public static function recipients(): array
    {
        return [
            self::TO_OWNER => 'The owner',
            self::TO_CREATOR => 'The creator',
            self::TO_ADDRESS => 'This address',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forApplication(int $applicationId): array
    {
        if ($applicationId <= 0 || !Database::tableExists('application_rules')) {
            return [];
        }
        return Database::select(
            'application_rules',
            '*',
            'application_id = ?',
            [$applicationId],
            'id ASC'
        );
    }

    /**
     * @return array<string, string> field key => label
     */
    public static function fieldChoices(array $itemTypeIds): array
    {
        $choices = [
            '' => 'Any record',
            'item_title' => 'Title',
        ];
        foreach ($itemTypeIds as $typeId) {
            $links = Database::select(
                'item_type_custom_fields',
                ['custom_field_id'],
                'item_type_id = ?',
                [(int) $typeId],
                'custom_field_order ASC'
            );
            foreach ($links as $link) {
                $fieldId = (int) $link['custom_field_id'];
                $field = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [$fieldId]);
                if ($field === null) {
                    continue;
                }
                $choices['custom_field_' . $fieldId] = (string) $field['custom_field_name'];
            }
        }
        return $choices;
    }

    /**
     * @param array<string, mixed> $input
     */
    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $ruleId, int $applicationId): ?array
    {
        if ($ruleId <= 0 || $applicationId <= 0 || !Database::tableExists('application_rules')) {
            return null;
        }
        return Database::first(
            'application_rules',
            '*',
            'id = ? AND application_id = ?',
            [$ruleId, $applicationId]
        );
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function add(int $applicationId, array $input): void
    {
        Database::insert('application_rules', self::normalise($applicationId, $input));
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function update(int $ruleId, int $applicationId, array $input): void
    {
        if (self::find($ruleId, $applicationId) === null) {
            return;
        }
        $row = self::normalise($applicationId, $input);
        unset($row['application_id']);
        Database::update('application_rules', $row, 'id = ? AND application_id = ?', [$ruleId, $applicationId]);
    }

    public static function delete(int $ruleId, int $applicationId): void
    {
        if ($ruleId <= 0 || $applicationId <= 0 || !Database::tableExists('application_rules')) {
            return;
        }
        Database::delete('application_rules', 'id = ? AND application_id = ?', [$ruleId, $applicationId]);
    }

    public static function deleteForApplication(int $applicationId): void
    {
        if ($applicationId <= 0 || !Database::tableExists('application_rules')) {
            return;
        }
        Database::delete('application_rules', 'application_id = ?', [$applicationId]);
    }

    /**
     * @param array<string, int> $fieldIdsByName
     */
    public static function seedBlueprint(int $applicationId, int $itemTypeId, string $blueprint, array $fieldIdsByName): void
    {
        if ($blueprint === ApplicationBuilder::BLUEPRINT_CASE || $blueprint === ApplicationBuilder::BLUEPRINT_HELPDESK) {
            $priorityId = (int) ($fieldIdsByName['Priority'] ?? 0);
            if ($priorityId > 0) {
                self::add($applicationId, [
                    'item_type_id' => $itemTypeId,
                    'name' => 'Email the owner when priority is high',
                    'event' => self::EVENT_UPDATE,
                    'field_key' => 'custom_field_' . $priorityId,
                    'operator' => self::OP_BECOMES,
                    'match_value' => 'High',
                    'outcome' => self::OUTCOME_EMAIL,
                    'recipient' => self::TO_OWNER,
                    'subject' => 'High priority: ITEM_TITLE',
                    'body' => 'ITEM_TITLE (ITEM_ID) is now high priority.',
                ]);
            }
        }
        if ($blueprint === ApplicationBuilder::BLUEPRINT_CRM) {
            $stageId = (int) ($fieldIdsByName['Stage'] ?? 0);
            if ($stageId > 0) {
                self::add($applicationId, [
                    'item_type_id' => $itemTypeId,
                    'name' => 'Log when a contact becomes a customer',
                    'event' => self::EVENT_UPDATE,
                    'field_key' => 'custom_field_' . $stageId,
                    'operator' => self::OP_BECOMES,
                    'match_value' => 'Customer',
                    'outcome' => self::OUTCOME_LOG,
                    'body' => 'ITEM_TITLE is now a customer.',
                ]);
            }
        }
    }

    /**
     * Run rules for an item event. Called beside the older package runner.
     */
    public static function run(int $itemId, string $requestingAction): void
    {
        $event = self::REQUESTS[$requestingAction] ?? '';
        if ($itemId <= 0 || $event === '' || !Database::tableExists('application_rules')) {
            return;
        }
        $stored = Database::first('items', '*', 'item_id = ?', [$itemId]);
        if ($stored === null) {
            return;
        }
        $stored = ItemFields::hydrate($stored);
        $typeId = (int) ($stored['item_type_id'] ?? 0);
        $posted = is_array($_POST ?? null) ? $_POST : [];
        $current = array_merge($stored, $posted);
        $previous = $event === self::EVENT_CREATE ? [] : $stored;

        $rules = Database::select(
            'application_rules',
            '*',
            'item_type_id = ? AND event = ? AND enabled = 1',
            [$typeId, $event],
            'id ASC'
        );
        $fired = [];
        for ($pass = 0; $pass < 4; $pass++) {
            $moved = false;
            foreach ($rules as $rule) {
                $ruleId = (int) ($rule['id'] ?? 0);
                if ($ruleId > 0 && isset($fired[$ruleId])) {
                    continue;
                }
                if (!self::matches($rule, $current, $previous)) {
                    continue;
                }
                if ($ruleId > 0) {
                    $fired[$ruleId] = true;
                }
                $outcome = (string) ($rule['outcome'] ?? '');
                if ($outcome === self::OUTCOME_EMAIL) {
                    self::sendEmail($itemId, $rule, $current);
                } elseif ($outcome === self::OUTCOME_SET_FIELD) {
                    if (self::setField($itemId, $rule, $current)) {
                        $moved = true;
                    }
                } elseif ($outcome === self::OUTCOME_SET_OWNER) {
                    if (self::setOwner($itemId, $rule, $current)) {
                        $moved = true;
                    }
                } else {
                    self::writeLog($itemId, $rule, $current);
                }
            }
            if (!$moved) {
                break;
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function normalise(int $applicationId, array $input): array
    {
        $events = self::events();
        $operators = self::operators();
        $outcomes = self::outcomes();
        $recipients = self::recipients();

        $event = (string) ($input['event'] ?? self::EVENT_UPDATE);
        $operator = (string) ($input['operator'] ?? self::OP_ALWAYS);
        $outcome = (string) ($input['outcome'] ?? self::OUTCOME_LOG);
        $recipient = (string) ($input['recipient'] ?? self::TO_OWNER);
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $name = ($outcomes[$outcome] ?? 'Rule') . ' when ' . strtolower($events[$event] ?? 'updated');
        }
        if (!isset($outcomes[$outcome])) {
            $outcome = self::OUTCOME_LOG;
        }

        $address = '';
        $subject = '';
        $body = '';
        $actionField = '';
        $actionValue = '';
        if ($outcome === self::OUTCOME_EMAIL) {
            $address = trim((string) ($input['address'] ?? ''));
            $subject = trim((string) ($input['subject'] ?? ''));
            $body = trim((string) ($input['body'] ?? ''));
        } elseif ($outcome === self::OUTCOME_LOG) {
            $body = trim((string) ($input['body'] ?? ''));
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_SET_FIELD) {
            $actionField = trim((string) ($input['action_field'] ?? ''));
            $actionValue = trim((string) ($input['action_value'] ?? ''));
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_SET_OWNER) {
            $actionValue = trim((string) ($input['owner_id'] ?? $input['action_value'] ?? ''));
            $recipient = self::TO_OWNER;
        }

        return [
            'application_id' => $applicationId,
            'item_type_id' => (int) ($input['item_type_id'] ?? 0),
            'name' => $name,
            'enabled' => self::enabledFlag($input),
            'event' => isset($events[$event]) ? $event : self::EVENT_UPDATE,
            'field_key' => trim((string) ($input['field_key'] ?? '')),
            'operator' => isset($operators[$operator]) ? $operator : self::OP_ALWAYS,
            'match_value' => trim((string) ($input['match_value'] ?? '')),
            'outcome' => $outcome,
            'recipient' => isset($recipients[$recipient]) ? $recipient : self::TO_OWNER,
            'address' => $address,
            'subject' => $subject,
            'body' => $body,
            'role_id' => (int) ($input['role_id'] ?? 5),
            'action_field' => $actionField,
            'action_value' => $actionValue,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function enabledFlag(array $input): int
    {
        if (!array_key_exists('enabled', $input)) {
            return 1;
        }
        $value = strtolower(trim((string) $input['enabled']));
        return in_array($value, ['0', 'no', 'off'], true) ? 0 : 1;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     * @param array<string, mixed> $previous
     */
    private static function matches(array $rule, array $current, array $previous): bool
    {
        $operator = (string) ($rule['operator'] ?? self::OP_ALWAYS);
        if ($operator === self::OP_ALWAYS) {
            return true;
        }
        $key = (string) ($rule['field_key'] ?? '');
        if ($key === '') {
            return false;
        }
        $now = trim((string) ($current[$key] ?? ''));
        $then = trim((string) ($previous[$key] ?? ''));
        $want = trim((string) ($rule['match_value'] ?? ''));
        return match ($operator) {
            self::OP_EQ => $now === $want,
            self::OP_NEQ => $now !== $want,
            self::OP_BECOMES => $now === $want && $then !== $want,
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     */
    private static function setField(int $itemId, array $rule, array &$current): bool
    {
        $key = (string) ($rule['action_field'] ?? '');
        $value = (string) ($rule['action_value'] ?? '');
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
            self::note($itemId, $rule, 'Title set to ' . $value . '.');
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
        $field = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [$fieldId]);
        $label = trim((string) ($field['custom_field_name'] ?? 'Field'));
        self::note($itemId, $rule, $label . ' set to ' . $value . '.');
        return true;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     */
    private static function setOwner(int $itemId, array $rule, array &$current): bool
    {
        $target = (string) ($rule['action_value'] ?? '');
        $userId = $target === self::OWNER_CREATOR
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
        $name = self::userName($userId);
        self::note($itemId, $rule, 'Owner set to ' . ($name !== '' ? $name : ('user ' . $userId)) . '.');
        return true;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private static function note(int $itemId, array $rule, string $text): void
    {
        $rule['body'] = $text;
        self::writeLog($itemId, $rule, ['item_title' => '', 'creator_security' => 0, 'user_security' => 0]);
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     */
    private static function sendEmail(int $itemId, array $rule, array $current): void
    {
        $host = defined('SET_SMTP_HOST') ? trim((string) SET_SMTP_HOST) : '';
        $from = defined('SET_SMTP_USER') ? trim((string) SET_SMTP_USER) : '';
        if ($host === '' || str_starts_with(strtolower($host), 'enter ') || !str_contains($from, '@')) {
            return;
        }
        $to = self::recipientAddress($rule, $current);
        if ($to === '' || !str_contains($to, '@')) {
            return;
        }
        $subject = self::fill((string) ($rule['subject'] ?? ''), $itemId, $current);
        $body = self::fill((string) ($rule['body'] ?? ''), $itemId, $current);
        if ($subject === '') {
            $subject = 'ITEM_ID ' . $itemId;
        }
        Communications::sendSMTPMail(
            $host,
            (int) (defined('SET_SMTP_PORT') ? SET_SMTP_PORT : 25),
            $from,
            defined('SET_SMTP_PASSWORD') ? (string) SET_SMTP_PASSWORD : '',
            $to,
            $subject,
            $body,
            $from
        );
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     */
    private static function recipientAddress(array $rule, array $current): string
    {
        $kind = (string) ($rule['recipient'] ?? self::TO_OWNER);
        if ($kind === self::TO_ADDRESS) {
            return trim((string) ($rule['address'] ?? ''));
        }
        $userId = (int) ($kind === self::TO_CREATOR
            ? ($current['creator_security'] ?? 0)
            : ($current['user_security'] ?? 0));
        if ($userId <= 0) {
            return '';
        }
        $user = Database::first('users', ['email'], 'user_id = ?', [$userId]);
        return trim((string) ($user['email'] ?? ''));
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $current
     */
    private static function writeLog(int $itemId, array $rule, array $current): void
    {
        $text = self::fill((string) ($rule['body'] ?? ''), $itemId, $current);
        if ($text === '') {
            return;
        }
        $sequence = Database::newID('core_log', 'log_item_sequence', 'item_id = ' . $itemId);
        $now = time();
        Database::insert('core_log', [
            'item_id' => $itemId,
            'create_date' => (string) $now,
            'item_identifier' => 'rule-' . (int) ($rule['id'] ?? 0),
            'log_item_sequence' => $sequence,
            'log_text' => $text,
            'security_id' => (int) ($_SESSION['access_user_id'] ?? 0),
            'role_id' => (int) ($rule['role_id'] ?? 5),
        ]);
        Database::update('items', ['core_log_updated' => $now], 'item_id = ?', [$itemId]);
    }

    /**
     * @param array<string, mixed> $current
     */
    private static function fill(string $text, int $itemId, array $current): string
    {
        $creator = self::userName((int) ($current['creator_security'] ?? 0));
        $owner = self::userName((int) ($current['user_security'] ?? 0));
        $replace = [
            'ITEM_ID' => (string) $itemId,
            'ITEM_TITLE' => (string) ($current['item_title'] ?? ''),
            'ITEM_CREATOR' => $creator,
            'ITEM_OWNER' => $owner,
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

    private static function userName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }
        $user = Database::first('users', ['user_name'], 'user_id = ?', [$userId]);
        return trim((string) ($user['user_name'] ?? ''));
    }
}
