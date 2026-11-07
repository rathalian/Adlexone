<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Data\ItemFields;
use Adlexone\Database\Database;

/**
 * Per-application rules.
 *
 * A rule is when (event), if (field, operator, value), then one or more
 * actions in order: email, write a log line, set a field, or set the owner.
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
    public const OP_EMPTY = 'empty';
    public const OP_NOT_EMPTY = 'not_empty';
    public const OP_CONTAINS = 'contains';
    public const OP_CHANGED = 'changed';
    public const OP_FROM_TO = 'from_to';

    public const OUTCOME_EMAIL = 'email';
    public const OUTCOME_LOG = 'log';
    public const OUTCOME_SET_FIELD = 'set_field';
    public const OUTCOME_SET_OWNER = 'set_owner';
    public const OUTCOME_STAMP = 'stamp';
    public const OUTCOME_ANNOUNCE = 'announce';

    public const OWNER_CREATOR = 'creator';

    public const TO_OWNER = 'owner';
    public const TO_CREATOR = 'creator';
    public const TO_BOTH = 'both';
    public const TO_OWNER_GROUPS = 'owner_groups';
    public const TO_OWNER_AND_GROUPS = 'owner_and_groups';
    public const TO_CREATOR_GROUPS = 'creator_groups';
    public const TO_BOTH_GROUPS = 'both_groups';
    public const TO_ITEM_GROUPS = 'item_groups';
    public const TO_USER = 'user';
    public const TO_GROUP = 'group';
    public const TO_ADDRESS = 'address';

    /** @var array<string, string> */
    public const REQUESTS = [
        'create_item' => self::EVENT_CREATE,
        'update_record' => self::EVENT_UPDATE,
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
            self::OP_EQ => 'Equals',
            self::OP_NEQ => 'Does not equal',
            self::OP_BECOMES => 'Becomes',
            self::OP_EMPTY => 'Is empty',
            self::OP_NOT_EMPTY => 'Is not empty',
            self::OP_CONTAINS => 'Contains',
            self::OP_CHANGED => 'Changes',
            self::OP_FROM_TO => 'Changes from / to',
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
            self::OUTCOME_STAMP => 'Stamp today\'s date',
            self::OUTCOME_ANNOUNCE => 'Post a notice',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function recipients(): array
    {
        return [
            self::TO_OWNER => 'The owner',
            self::TO_CREATOR => 'The creator',
            self::TO_BOTH => 'The owner and the creator',
            self::TO_OWNER_AND_GROUPS => 'The owner and their groups',
            self::TO_OWNER_GROUPS => 'People in the owner\'s groups',
            self::TO_CREATOR_GROUPS => 'People in the creator\'s groups',
            self::TO_BOTH_GROUPS => 'People in the owner and creator\'s groups',
            self::TO_ITEM_GROUPS => 'People in the record\'s groups',
            self::TO_USER => 'A person',
            self::TO_GROUP => 'A group',
            self::TO_ADDRESS => 'This address',
        ];
    }

    /**
     * How public a note must be before a log rule matches.
     * An empty value matches every note.
     *
     * @return array<string, string>
     */
    public static function logAudiences(): array
    {
        return [
            '' => 'Any note',
            'administrator' => 'Administrator or wider',
            'manager' => 'Manager or wider',
            'user' => 'User or wider',
            'everyone' => 'Visible to everyone',
        ];
    }

    /**
     * A stored note audience. A number is the role that number used to name.
     * 0 and 5 are notes for everyone.
     */
    public static function noteAudience(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            return match ((int) $value) {
                1 => 'site-administrator',
                2 => 'administrator',
                3 => 'manager',
                4 => 'user',
                default => '',
            };
        }
        $text = trim((string) $value);

        return in_array($text, ['site-administrator', 'administrator', 'manager', 'user'], true) ? $text : '';
    }

    /**
     * The narrowest note a log rule still matches. "everyone" matches only notes for everyone.
     */
    public static function minimumAudience(mixed $value): string
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            return match ((int) $value) {
                1 => 'site-administrator',
                2 => 'administrator',
                3 => 'manager',
                4 => 'user',
                5 => 'everyone',
                default => '',
            };
        }
        $text = trim((string) $value);

        return array_key_exists($text, self::logAudiences()) ? $text : '';
    }

    /**
     * Who a "set the owner" step can assign.
     *
     * @return array<string, string> stored value => label
     */
    public static function ownerChoices(): array
    {
        $choices = [
            self::OWNER_CREATOR => 'The creator',
        ];
        foreach (self::people() as $id => $name) {
            $choices[(string) $id] = $name;
        }
        return $choices;
    }

    /**
     * @return array<int, string>
     */
    public static function people(): array
    {
        $people = [];
        if (!Database::tableExists('users')) {
            return $people;
        }
        foreach (Database::select('users', ['user_id', 'user_name', 'first_name', 'last_name'], '', [], 'user_name ASC') as $user) {
            $id = (int) ($user['user_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = RecordQuery::displayName($user);
            $people[$id] = $name !== '' ? $name : ('User ' . $id);
        }
        return $people;
    }

    /**
     * @return array<int, string>
     */
    public static function groups(): array
    {
        $groups = [];
        if (!Database::tableExists('groups')) {
            return $groups;
        }
        foreach (Database::select('groups', ['group_id', 'group_name'], '', [], 'group_name ASC') as $group) {
            $id = (int) ($group['group_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $name = trim((string) ($group['group_name'] ?? ''));
            $groups[$id] = $name !== '' ? $name : ('Group ' . $id);
        }
        return $groups;
    }

    /**
     * Fields a condition or "set field" step can use for one record type.
     *
     * @return list<array{key: string, label: string, kind: string, options: list<array{value: string, label: string}>}>
     */
    public static function fieldCatalog(int $itemTypeId): array
    {
        $people = [];
        foreach (self::people() as $id => $name) {
            $people[] = ['value' => (string) $id, 'label' => $name];
        }
        $groups = [];
        foreach (self::groups() as $id => $name) {
            $groups[] = ['value' => (string) $id, 'label' => $name];
        }
        $fields = [
            ['key' => 'item_title', 'label' => 'Title', 'kind' => 'text', 'options' => []],
            ['key' => 'owner', 'label' => 'Owner', 'kind' => 'user', 'options' => $people],
            ['key' => 'creator', 'label' => 'Creator', 'kind' => 'user', 'options' => $people],
            ['key' => 'groups', 'label' => 'Groups', 'kind' => 'group', 'options' => $groups],
        ];
        if ($itemTypeId <= 0) {
            return $fields;
        }
        $links = Database::select(
            'item_type_custom_fields',
            ['custom_field_id'],
            'item_type_id = ?',
            [$itemTypeId],
            'custom_field_order ASC'
        );
        foreach ($links as $link) {
            $fieldId = (int) $link['custom_field_id'];
            $field = Database::first('custom_fields', ['custom_field_name', 'field_type'], 'custom_field_id = ?', [$fieldId]);
            if ($field === null) {
                continue;
            }
            $options = [];
            $kind = 'text';
            $scoped = WorkflowFields::scopedStatusValues($itemTypeId, $fieldId);
            if ($scoped !== null) {
                foreach ($scoped as $value) {
                    $options[] = ['value' => $value, 'label' => $value];
                }
                $kind = 'menu';
            } elseif (Database::tableExists('custom_field_menu_values')) {
                $menus = Database::select(
                    'custom_field_menu_values',
                    ['menu_value'],
                    'custom_field_id = ? AND parent_menu_value_id = 0',
                    [$fieldId],
                    'menu_value_id ASC'
                );
                foreach ($menus as $menu) {
                    $value = (string) ($menu['menu_value'] ?? '');
                    if ($value === '') {
                        continue;
                    }
                    $options[] = ['value' => $value, 'label' => $value];
                }
                if ($options !== []) {
                    $kind = 'menu';
                }
            }
            $fields[] = [
                'key' => 'custom_field_' . $fieldId,
                'label' => self::plainFieldLabel((string) $field['custom_field_name']),
                'kind' => $kind,
                'options' => $options,
            ];
        }
        return $fields;
    }

    /**
     * @param list<int> $itemTypeIds
     * @return array<string, string> field key => label
     */
    public static function fieldChoices(array $itemTypeIds): array
    {
        $choices = [
            '' => 'Any record',
            'item_title' => 'Title',
            'owner' => 'Owner',
            'creator' => 'Creator',
            'groups' => 'Groups',
        ];
        foreach ($itemTypeIds as $typeId) {
            foreach (self::fieldCatalog((int) $typeId) as $field) {
                if (!isset($choices[$field['key']])) {
                    $choices[$field['key']] = $field['label'];
                }
            }
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
        $operator = (string) ($rule['operator'] ?? '');
        if ($operator === self::OP_ALWAYS) {
            $condition = 'Always';
        } elseif ($operator === self::OP_EMPTY || $operator === self::OP_NOT_EMPTY || $operator === self::OP_CHANGED) {
            $field = $fieldLabels[(string) ($rule['field_key'] ?? '')] ?? (string) ($rule['field_key'] ?? '');
            $condition = trim($field . ' ' . strtolower($operators[$operator] ?? $operator));
        } elseif ($operator === self::OP_FROM_TO) {
            $field = $fieldLabels[(string) ($rule['field_key'] ?? '')] ?? (string) ($rule['field_key'] ?? '');
            $condition = trim($field . ' changes from ' . self::displayValue($rule, (string) ($rule['match_from'] ?? ''), $fieldLabels)
                . ' to ' . self::displayValue($rule, (string) ($rule['match_value'] ?? ''), $fieldLabels));
        } else {
            $field = $fieldLabels[(string) ($rule['field_key'] ?? '')] ?? (string) ($rule['field_key'] ?? '');
            $op = strtolower($operators[$operator] ?? $operator);
            $condition = trim($field . ' ' . $op . ' ' . self::displayValue($rule, (string) ($rule['match_value'] ?? ''), $fieldLabels));
        }
        $minimum = self::minimumAudience($rule['min_audience'] ?? ($rule['min_log_role'] ?? ''));
        if ((string) ($rule['event'] ?? '') === self::EVENT_LOG && $minimum !== '') {
            $condition .= ' (' . (self::logAudiences()[$minimum] ?? 'a narrower note') . ')';
        }
        $thens = [];
        foreach (self::stepsOf($rule) as $step) {
            $thens[] = self::stepSummary($step, $fieldLabels);
        }
        if ($thens === []) {
            $thens[] = 'Do nothing';
        }

        $done = rtrim(implode(', then ', $thens), '.');

        return $when . '. ' . $condition . '. ' . $done . '.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forApplication(int $applicationId): array
    {
        if ($applicationId <= 0 || !Database::tableExists('application_rules')) {
            return [];
        }
        $rules = Database::select(
            'application_rules',
            '*',
            'application_id = ?',
            [$applicationId],
            'sort_order ASC, id ASC'
        );
        return self::withSteps($rules);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(int $ruleId, int $applicationId): ?array
    {
        if ($ruleId <= 0 || $applicationId <= 0 || !Database::tableExists('application_rules')) {
            return null;
        }
        $rule = Database::first(
            'application_rules',
            '*',
            'id = ? AND application_id = ?',
            [$ruleId, $applicationId]
        );
        if ($rule === null) {
            return null;
        }
        $loaded = self::withSteps([$rule]);
        return $loaded[0] ?? null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function add(int $applicationId, array $input): int
    {
        $row = self::normalise($applicationId, $input);
        $steps = $row['steps'];
        unset($row['steps']);
        if ((int) ($row['sort_order'] ?? 0) <= 0) {
            $row['sort_order'] = self::nextSort($applicationId);
        }
        $id = Database::insert('application_rules', $row);
        self::replaceSteps($id, $steps);
        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function update(int $ruleId, int $applicationId, array $input): void
    {
        $existing = self::find($ruleId, $applicationId);
        if ($existing === null) {
            return;
        }
        $row = self::normalise($applicationId, $input);
        $steps = $row['steps'];
        unset($row['steps'], $row['application_id'], $row['sort_order']);
        Database::update('application_rules', $row, 'id = ? AND application_id = ?', [$ruleId, $applicationId]);
        self::replaceSteps($ruleId, $steps);
    }

    public static function delete(int $ruleId, int $applicationId): void
    {
        if ($ruleId <= 0 || $applicationId <= 0 || !Database::tableExists('application_rules')) {
            return;
        }
        if (Database::tableExists('application_rule_steps')) {
            Database::delete('application_rule_steps', 'rule_id = ?', [$ruleId]);
        }
        if (Database::tableExists('application_rule_runs')) {
            Database::delete('application_rule_runs', 'rule_id = ?', [$ruleId]);
        }
        Database::delete('application_rules', 'id = ? AND application_id = ?', [$ruleId, $applicationId]);
    }

    public static function deleteForApplication(int $applicationId): void
    {
        if ($applicationId <= 0 || !Database::tableExists('application_rules')) {
            return;
        }
        foreach (self::forApplication($applicationId) as $rule) {
            self::delete((int) $rule['id'], $applicationId);
        }
    }

    public static function setEnabled(int $ruleId, int $applicationId, bool $enabled): void
    {
        if (self::find($ruleId, $applicationId) === null) {
            return;
        }
        Database::update(
            'application_rules',
            ['enabled' => $enabled ? 1 : 0],
            'id = ? AND application_id = ?',
            [$ruleId, $applicationId]
        );
    }

    public static function duplicate(int $ruleId, int $applicationId): int
    {
        $rule = self::find($ruleId, $applicationId);
        if ($rule === null) {
            return 0;
        }
        $rule['name'] = 'Copy of ' . (string) $rule['name'];
        $rule['sort_order'] = 0;
        return self::add($applicationId, $rule);
    }

    public static function move(int $ruleId, int $applicationId, string $direction): void
    {
        $rule = self::find($ruleId, $applicationId);
        if ($rule === null) {
            return;
        }
        $siblings = [];
        foreach (self::forApplication($applicationId) as $row) {
            if ((int) $row['item_type_id'] === (int) $rule['item_type_id']) {
                $siblings[] = $row;
            }
        }
        $index = null;
        foreach ($siblings as $i => $row) {
            if ((int) $row['id'] === $ruleId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $direction === 'up' ? $index - 1 : $index + 1;
        if (!isset($siblings[$swap])) {
            return;
        }
        $leftOrder = (int) $siblings[$index]['sort_order'];
        $rightOrder = (int) $siblings[$swap]['sort_order'];
        if ($leftOrder === $rightOrder) {
            $rightOrder = $leftOrder + ($direction === 'up' ? -1 : 1);
        }
        Database::update('application_rules', ['sort_order' => $rightOrder], 'id = ?', [(int) $siblings[$index]['id']]);
        Database::update('application_rules', ['sort_order' => $leftOrder], 'id = ?', [(int) $siblings[$swap]['id']]);
    }
    public static function run(int $itemId, string $requestingAction): void
    {
        RuleRunner::run($itemId, $requestingAction);
    }

    public static function preview(int $ruleId, int $applicationId, int $itemId): array
    {
        return RuleRunner::preview($ruleId, $applicationId, $itemId);
    }

    public static function executeOne(int $ruleId, int $applicationId, int $itemId): array
    {
        return RuleRunner::executeOne($ruleId, $applicationId, $itemId);
    }


    /**
     * @return list<array<string, mixed>>
     */
    public static function recentRuns(int $ruleId, int $limit = 15): array
    {
        if ($ruleId <= 0 || !Database::tableExists('application_rule_runs')) {
            return [];
        }
        return Database::select(
            'application_rule_runs',
            '*',
            'rule_id = ?',
            [$ruleId],
            'id DESC',
            $limit
        );
    }
    public static function matchesState(array $rule, array $current, array $previous, int $itemId = 0): bool
    {
        return RuleRunner::matchesState($rule, $current, $previous, $itemId);
    }


    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function normalise(int $applicationId, array $input): array
    {
        $events = self::events();
        $operators = self::operators();
        $event = (string) ($input['event'] ?? self::EVENT_UPDATE);
        $operator = (string) ($input['operator'] ?? self::OP_ALWAYS);
        $name = trim((string) ($input['name'] ?? ''));
        $steps = self::stepsFromInput($input);
        $first = $steps[0];
        if ($name === '') {
            $name = self::stepSummary($first, []) . ' when ' . strtolower($events[$event] ?? 'updated');
        }
        $needsValue = !in_array($operator, [self::OP_ALWAYS, self::OP_EMPTY, self::OP_NOT_EMPTY, self::OP_CHANGED], true);

        return [
            'application_id' => $applicationId,
            'item_type_id' => (int) ($input['item_type_id'] ?? 0),
            'name' => $name,
            'enabled' => self::enabledFlag($input),
            'event' => isset($events[$event]) ? $event : self::EVENT_UPDATE,
            'field_key' => $operator === self::OP_ALWAYS ? '' : trim((string) ($input['field_key'] ?? '')),
            'operator' => isset($operators[$operator]) ? $operator : self::OP_ALWAYS,
            'match_value' => $needsValue ? trim((string) ($input['match_value'] ?? '')) : '',
            'match_from' => $operator === self::OP_FROM_TO ? trim((string) ($input['match_from'] ?? '')) : '',
            'outcome' => (string) $first['outcome'],
            'recipient' => (string) $first['recipient'],
            'address' => (string) $first['address'],
            'subject' => (string) $first['subject'],
            'body' => (string) $first['body'],
            'audience' => (string) $first['audience'],
            'action_field' => (string) $first['action_field'],
            'action_value' => (string) $first['action_value'],
            'skip_actor' => self::skipFlag($input),
            'min_audience' => $event === self::EVENT_LOG ? self::minimumAudience($input['min_audience'] ?? ($input['min_log_role'] ?? '')) : '',
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'steps' => $steps,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return list<array<string, mixed>>
     */
    private static function stepsFromInput(array $input): array
    {
        $raw = $input['steps'] ?? null;
        if (!is_array($raw) || $raw === []) {
            $raw = [[
                'outcome' => $input['outcome'] ?? self::OUTCOME_LOG,
                'recipient' => $input['recipient'] ?? self::TO_OWNER,
                'address' => $input['address'] ?? '',
                'subject' => $input['subject'] ?? '',
                'body' => $input['body'] ?? '',
                'audience' => $input['audience'] ?? self::noteAudience($input['role_id'] ?? ''),
                'action_field' => $input['action_field'] ?? '',
                'action_value' => $input['action_value'] ?? ($input['owner_id'] ?? ''),
                'user_id' => $input['user_id'] ?? 0,
                'group_id' => $input['group_id'] ?? 0,
            ]];
        }
        $steps = [];
        $order = 0;
        foreach ($raw as $step) {
            if (!is_array($step)) {
                continue;
            }
            $normal = self::normaliseStep($step);
            if ($normal === null) {
                continue;
            }
            $normal['sort_order'] = $order;
            $order += 10;
            $steps[] = $normal;
        }
        if ($steps === []) {
            $steps[] = self::normaliseStep(['outcome' => self::OUTCOME_LOG, 'body' => '']) ?? [];
            $steps[0]['sort_order'] = 0;
        }
        return $steps;
    }

    /**
     * @param array<string, mixed> $step
     * @return array<string, mixed>|null
     */
    private static function normaliseStep(array $step): ?array
    {
        $outcomes = self::outcomes();
        $recipients = self::recipients();
        $outcome = (string) ($step['outcome'] ?? '');
        if ($outcome === '') {
            return null;
        }
        if (!isset($outcomes[$outcome])) {
            $outcome = self::OUTCOME_LOG;
        }
        $recipient = (string) ($step['recipient'] ?? self::TO_OWNER);
        if (!isset($recipients[$recipient])) {
            $recipient = self::TO_OWNER;
        }
        $address = '';
        $subject = '';
        $body = '';
        $actionField = '';
        $actionValue = '';
        $userId = 0;
        $groupId = 0;
        $audience = self::noteAudience($step['audience'] ?? ($step['role_id'] ?? ''));
        if ($outcome === self::OUTCOME_EMAIL) {
            $address = trim((string) ($step['address'] ?? ''));
            $subject = trim((string) ($step['subject'] ?? ''));
            $body = trim((string) ($step['body'] ?? ''));
            $userId = (int) ($step['user_id'] ?? 0);
            $groupId = (int) ($step['group_id'] ?? 0);
        } elseif ($outcome === self::OUTCOME_LOG) {
            $body = trim((string) ($step['body'] ?? ''));
            $groupId = (int) ($step['group_id'] ?? 0);
            $audience = '';
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_SET_FIELD) {
            $actionField = trim((string) ($step['action_field'] ?? ''));
            $actionValue = trim((string) ($step['action_value'] ?? ''));
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_SET_OWNER) {
            $actionValue = trim((string) ($step['owner_id'] ?? $step['action_value'] ?? ''));
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_STAMP) {
            $actionField = trim((string) ($step['action_field'] ?? ''));
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_ANNOUNCE) {
            $subject = trim((string) ($step['subject'] ?? ''));
            $body = trim((string) ($step['body'] ?? ''));
            $recipient = self::TO_OWNER;
        }
        return [
            'outcome' => $outcome,
            'recipient' => $recipient,
            'address' => $address,
            'subject' => $subject,
            'body' => $body,
            'audience' => $audience,
            'action_field' => $actionField,
            'action_value' => $actionValue,
            'user_id' => $userId,
            'group_id' => $groupId,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rules
     * @return list<array<string, mixed>>
     */
    public static function withSteps(array $rules): array
    {
        if ($rules === [] || !Database::tableExists('application_rule_steps')) {
            foreach ($rules as $i => $rule) {
                $rules[$i]['steps'] = self::stepsOf($rule);
            }
            return $rules;
        }
        $ids = [];
        foreach ($rules as $rule) {
            $id = (int) ($rule['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $byRule = [];
        if ($ids !== []) {
            $rows = Database::select(
                'application_rule_steps',
                '*',
                'rule_id IN (' . implode(',', $ids) . ')',
                [],
                'sort_order ASC, id ASC'
            );
            foreach ($rows as $row) {
                $byRule[(int) $row['rule_id']][] = $row;
            }
        }
        foreach ($rules as $i => $rule) {
            $id = (int) ($rule['id'] ?? 0);
            $rules[$i]['steps'] = $byRule[$id] ?? self::legacySteps($rule);
        }
        return $rules;
    }

    /**
     * @param array<string, mixed> $rule
     * @return list<array<string, mixed>>
     */
    public static function stepsOf(array $rule): array
    {
        $steps = $rule['steps'] ?? null;
        if (is_array($steps) && $steps !== []) {
            return array_values(array_filter($steps, 'is_array'));
        }
        return self::legacySteps($rule);
    }

    /**
     * @param array<string, mixed> $rule
     * @return list<array<string, mixed>>
     */
    private static function legacySteps(array $rule): array
    {
        if ((string) ($rule['outcome'] ?? '') === '') {
            return [];
        }
        return [[
            'outcome' => (string) $rule['outcome'],
            'recipient' => (string) ($rule['recipient'] ?? self::TO_OWNER),
            'address' => (string) ($rule['address'] ?? ''),
            'subject' => (string) ($rule['subject'] ?? ''),
            'body' => (string) ($rule['body'] ?? ''),
            'audience' => self::noteAudience($rule['audience'] ?? ($rule['role_id'] ?? '')),
            'action_field' => (string) ($rule['action_field'] ?? ''),
            'action_value' => (string) ($rule['action_value'] ?? ''),
            'user_id' => 0,
            'group_id' => 0,
        ]];
    }

    /**
     * @param list<array<string, mixed>> $steps
     */
    private static function replaceSteps(int $ruleId, array $steps): void
    {
        if ($ruleId <= 0 || !Database::tableExists('application_rule_steps')) {
            return;
        }
        Database::delete('application_rule_steps', 'rule_id = ?', [$ruleId]);
        foreach ($steps as $step) {
            $step['rule_id'] = $ruleId;
            unset($step['id']);
            Database::insert('application_rule_steps', $step);
        }
    }

    public static function today(): string
    {
        return \Adlexone\Data\FieldValidation::today();
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $fieldLabels
     */
    private static function stepSummary(array $step, array $fieldLabels): string
    {
        return match ((string) ($step['outcome'] ?? '')) {
            self::OUTCOME_EMAIL => 'Email ' . strtolower(self::recipients()[(string) ($step['recipient'] ?? '')] ?? 'the owner'),
            self::OUTCOME_SET_FIELD => self::setFieldSummary($fieldLabels[(string) ($step['action_field'] ?? '')] ?? self::fieldLabel((string) ($step['action_field'] ?? '')), (string) ($step['action_value'] ?? '')),
            self::OUTCOME_SET_OWNER => 'Set the owner to ' . self::ownerLabel((string) ($step['action_value'] ?? ''), []),
            self::OUTCOME_STAMP => 'Stamp ' . ($fieldLabels[(string) ($step['action_field'] ?? '')] ?? self::fieldLabel((string) ($step['action_field'] ?? ''))) . ' with today\'s date',
            self::OUTCOME_ANNOUNCE => 'Post a notice',
            default => 'Write a log line',
        };
    }

    private static function setFieldSummary(string $label, string $value): string
    {
        return trim($value) === '' ? 'Clear ' . $label : 'Set ' . $label . ' to ' . $value;
    }

    private static function plainFieldLabel(string $name): string
    {
        $plain = \Adlexone\Screen\WorkScreen::plainFieldName($name);
        if ($plain === '') {
            return trim($name);
        }

        return ucfirst($plain);
    }

    public static function fieldLabel(string $key): string
    {
        if ($key === 'item_title') {
            return 'Title';
        }
        $id = ItemFields::fieldIdFromKey($key);
        if ($id === null || !Database::tableExists('custom_fields')) {
            return 'a field';
        }
        $field = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [$id]);
        $label = self::plainFieldLabel((string) ($field['custom_field_name'] ?? ''));
        return $label !== '' ? $label : 'a field';
    }

    /**
     * @param array<string, mixed> $current
     */
    public static function ownerLabel(string $value, array $current): string
    {
        if ($value === self::OWNER_CREATOR) {
            return 'the creator';
        }
        if (ctype_digit($value)) {
            $name = self::userName((int) $value);
            return $name !== '' ? $name : ('user ' . $value);
        }
        return 'a person';
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, string> $fieldLabels
     */
    private static function displayValue(array $rule, string $value, array $fieldLabels): string
    {
        $key = (string) ($rule['field_key'] ?? '');
        if (($key === 'owner' || $key === 'creator') && ctype_digit($value)) {
            $name = self::userName((int) $value);
            return $name !== '' ? $name : $value;
        }
        if ($key === 'groups' && ctype_digit($value)) {
            return self::groups()[(int) $value] ?? $value;
        }
        return $value;
    }

    public static function userName(int $userId): string
    {
        if ($userId <= 0 || !Database::tableExists('users')) {
            return '';
        }
        $user = Database::first('users', ['user_name', 'first_name', 'last_name'], 'user_id = ?', [$userId]);
        if ($user === null) {
            return '';
        }
        return RecordQuery::displayName($user);
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function enabledFlag(array $input): int
    {
        if (!array_key_exists('enabled', $input)) {
            return 1;
        }
        $value = $input['enabled'];
        if (is_int($value)) {
            return $value === 1 ? 1 : 0;
        }
        $text = strtolower(trim((string) $value));
        return in_array($text, ['0', 'no', 'off'], true) ? 0 : 1;
    }

    /**
     * @param array<string, mixed> $input
     */
    private static function skipFlag(array $input): int
    {
        if (!array_key_exists('skip_actor', $input) && !array_key_exists('email_actor', $input)) {
            return 1;
        }
        if (array_key_exists('email_actor', $input)) {
            $value = strtolower(trim((string) $input['email_actor']));
            return in_array($value, ['1', 'yes', 'on'], true) ? 0 : 1;
        }
        $value = $input['skip_actor'];
        if (is_int($value)) {
            return $value === 0 ? 0 : 1;
        }
        $text = strtolower(trim((string) $value));
        return in_array($text, ['0', 'no', 'off'], true) ? 0 : 1;
    }

    private static function nextSort(int $applicationId): int
    {
        $row = Database::row(
            'SELECT COALESCE(MAX(sort_order), 0) AS n FROM application_rules WHERE application_id = ?',
            [$applicationId]
        );
        return ((int) ($row['n'] ?? 0)) + 10;
    }
}
