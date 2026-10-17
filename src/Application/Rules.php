<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\support\Communications;
use Adlexone\support\Database;
use Adlexone\Html\Blocks;

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
     * Minimum log-line role. Higher numbers are a wider audience.
     *
     * @return array<int, string>
     */
    public static function logRoles(): array
    {
        return [
            0 => 'Any log line',
            2 => 'Role number 2 or more public',
            3 => 'Role number 3 or more public',
            4 => 'Role number 4 or more public',
            5 => 'Visible to everyone',
        ];
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
            $name = trim((string) ($user['user_name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
            }
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
            if (Database::tableExists('custom_field_menu_values')) {
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
                'label' => (string) $field['custom_field_name'],
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
        if ((string) ($rule['event'] ?? '') === self::EVENT_LOG && (int) ($rule['min_log_role'] ?? 0) > 0) {
            $roles = self::logRoles();
            $condition .= ' (' . ($roles[(int) $rule['min_log_role']] ?? 'log role') . ')';
        }
        $thens = [];
        foreach (self::stepsOf($rule) as $step) {
            $thens[] = self::stepSummary($step, $fieldLabels);
        }
        if ($thens === []) {
            $thens[] = 'Do nothing';
        }

        return $when . '. ' . $condition . '. ' . implode(', then ', $thens) . '.';
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

    /**
     * @param array<string, int> $fieldIdsByName
     */
    public static function seedBlueprint(int $applicationId, int $itemTypeId, string $blueprint, array $fieldIdsByName): void
    {
        $stage = match ($blueprint) {
            ApplicationBuilder::BLUEPRINT_STUDIO => ['Stage', 'Review', 'Tell the owner a job is ready for review', 'Review: ITEM_TITLE', 'ITEM_TITLE is ready for review.'],
            ApplicationBuilder::BLUEPRINT_MATTERS => ['Waiting', 'Waiting on us', 'Tell the owner a matter is waiting on us', 'Waiting on us: ITEM_TITLE', 'ITEM_TITLE is waiting on us.'],
            ApplicationBuilder::BLUEPRINT_HOUSE => ['When', 'Tonight', 'Tell the owner something is on tonight', 'Tonight: ITEM_TITLE', 'ITEM_TITLE is on tonight.'],
            default => null,
        };
        if ($stage === null) {
            return;
        }
        [$fieldName, $match, $name, $subject, $body] = $stage;
        $fieldId = (int) ($fieldIdsByName[$fieldName] ?? 0);
        if ($fieldId <= 0) {
            return;
        }
        self::add($applicationId, [
            'item_type_id' => $itemTypeId,
            'name' => $name,
            'event' => self::EVENT_UPDATE,
            'field_key' => 'custom_field_' . $fieldId,
            'operator' => self::OP_BECOMES,
            'match_value' => $match,
            'outcome' => self::OUTCOME_EMAIL,
            'recipient' => self::TO_OWNER,
            'subject' => $subject,
            'body' => $body,
        ]);
    }

    /**
     * Run rules for an item event.
     *
     * A log line posted with an update also runs log rules, because the item
     * form saves the log and then reports an update.
     */
    public static function run(int $itemId, string $requestingAction): void
    {
        $event = self::REQUESTS[$requestingAction] ?? '';
        if ($itemId <= 0 || $event === '' || !self::ready()) {
            return;
        }
        $events = [$event];
        $posted = is_array($_POST ?? null) ? $_POST : [];
        if ($event === self::EVENT_UPDATE && trim((string) ($posted['log_entry'] ?? '')) !== '') {
            $events[] = self::EVENT_LOG;
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
        $rule = self::find($ruleId, $applicationId);
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
        foreach (self::stepsOf($rule) as $step) {
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
        $rule = self::find($ruleId, $applicationId);
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

    /**
     * @return list<array<string, mixed>>
     */
    public static function runsForItem(int $itemId, int $limit = 10): array
    {
        if ($itemId <= 0 || !Database::tableExists('application_rule_runs')) {
            return [];
        }
        $runs = Database::select(
            'application_rule_runs',
            '*',
            'item_id = ?',
            [$itemId],
            'id DESC',
            $limit
        );
        foreach ($runs as $i => $run) {
            $rule = Database::first('application_rules', ['name'], 'id = ?', [(int) ($run['rule_id'] ?? 0)]);
            $runs[$i]['rule_name'] = (string) ($rule['name'] ?? 'Rule');
        }
        return $runs;
    }

    public static function recordCard(int $itemId): string
    {
        $runs = self::runsForItem($itemId, 8);
        if ($runs === []) {
            return '';
        }
        $html = '';
        foreach ($runs as $run) {
            $when = date('j M Y, g:i a', (int) ($run['ran_at'] ?? time()));
            $html .= '<div class="log-item"><div class="log-heading"><strong>'
                . htmlspecialchars($when, ENT_QUOTES, 'UTF-8')
                . ' — ' . htmlspecialchars((string) $run['rule_name'], ENT_QUOTES, 'UTF-8')
                . '</strong></div><div class="log-text">'
                . nl2br(htmlspecialchars((string) ($run['detail'] ?? ''), ENT_QUOTES, 'UTF-8'))
                . '</div></div>';
        }
        return \Adlexone\Html\Blocks::vertical([['title' => 'Rules that ran', 'html' => $html]]);
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
        if ((string) ($rule['event'] ?? '') === self::EVENT_LOG) {
            if (trim((string) ($current['log_entry'] ?? '')) === '') {
                return false;
            }
            $min = (int) ($rule['min_log_role'] ?? 0);
            if ($min > 0 && (int) ($current['role_id'] ?? 0) < $min) {
                return false;
            }
        }
        $operator = (string) ($rule['operator'] ?? self::OP_ALWAYS);
        if ($operator === self::OP_ALWAYS) {
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
            self::OP_EQ => self::equalsValue($key, $now, $want),
            self::OP_NEQ => !self::equalsValue($key, $now, $want),
            self::OP_BECOMES => self::equalsValue($key, $now, $want) && !self::equalsValue($key, $then, $want),
            self::OP_EMPTY => self::isEmpty($key, $now),
            self::OP_NOT_EMPTY => !self::isEmpty($key, $now),
            self::OP_CONTAINS => self::containsValue($key, $now, $want),
            self::OP_CHANGED => !$now['same']($then),
            self::OP_FROM_TO => self::equalsValue($key, $then, $from) && self::equalsValue($key, $now, $want) && !$now['same']($then),
            default => false,
        };
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
            'role_id' => (int) $first['role_id'],
            'action_field' => (string) $first['action_field'],
            'action_value' => (string) $first['action_value'],
            'skip_actor' => self::skipFlag($input),
            'min_log_role' => $event === self::EVENT_LOG ? (int) ($input['min_log_role'] ?? 0) : 0,
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
                'role_id' => $input['role_id'] ?? 5,
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
        $roleId = (int) ($step['role_id'] ?? 5);
        if ($outcome === self::OUTCOME_EMAIL) {
            $address = trim((string) ($step['address'] ?? ''));
            $subject = trim((string) ($step['subject'] ?? ''));
            $body = trim((string) ($step['body'] ?? ''));
            $userId = (int) ($step['user_id'] ?? 0);
            $groupId = (int) ($step['group_id'] ?? 0);
        } elseif ($outcome === self::OUTCOME_LOG) {
            $body = trim((string) ($step['body'] ?? ''));
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_SET_FIELD) {
            $actionField = trim((string) ($step['action_field'] ?? ''));
            $actionValue = trim((string) ($step['action_value'] ?? ''));
            $recipient = self::TO_OWNER;
        } elseif ($outcome === self::OUTCOME_SET_OWNER) {
            $actionValue = trim((string) ($step['owner_id'] ?? $step['action_value'] ?? ''));
            $recipient = self::TO_OWNER;
        }
        return [
            'outcome' => $outcome,
            'recipient' => $recipient,
            'address' => $address,
            'subject' => $subject,
            'body' => $body,
            'role_id' => $roleId > 0 ? $roleId : 5,
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
    private static function withSteps(array $rules): array
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
    private static function stepsOf(array $rule): array
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
            'role_id' => (int) ($rule['role_id'] ?? 5),
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
        return self::withSteps($rules);
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
        $previous = $event === self::EVENT_CREATE ? [] : $stored;
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
        foreach (self::stepsOf($rule) as $step) {
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
        if ($outcome === self::OUTCOME_EMAIL) {
            $detail = self::sendEmail($itemId, $rule, $step, $current, $write);
            $status = str_starts_with($detail, 'failed') ? 'failed' : (str_starts_with($detail, 'skipped') ? 'skipped' : 'done');
            return ['status' => $status, 'detail' => $detail, 'moved' => false];
        }
        if ($outcome === self::OUTCOME_SET_FIELD) {
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
        if ($outcome === self::OUTCOME_SET_OWNER) {
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
        if ($outcome === self::OUTCOME_EMAIL) {
            $to = self::recipientAddresses($step, $current, $itemId, $skipActor);
            $who = $to === [] ? 'nobody' : implode(', ', $to);
            $subject = self::fill((string) ($step['subject'] ?? ''), $itemId, $current);
            $verb = $past ? 'Emailed' : 'Would email';
            return $verb . ' ' . $who . ($subject !== '' ? '. Subject: ' . $subject : '.');
        }
        if ($outcome === self::OUTCOME_SET_FIELD) {
            $label = self::fieldLabel((string) ($step['action_field'] ?? ''));
            $verb = $past ? 'Set' : 'Would set';
            return $verb . ' ' . $label . ' to ' . (string) ($step['action_value'] ?? '') . '.';
        }
        if ($outcome === self::OUTCOME_SET_OWNER) {
            $name = self::ownerLabel((string) ($step['action_value'] ?? ''), $current);
            $verb = $past ? 'Set the owner to ' : 'Would set the owner to ';
            return $verb . $name . '.';
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
        if ((string) ($rule['event'] ?? '') === self::EVENT_LOG && trim((string) ($current['log_entry'] ?? '')) === '') {
            return 'A log rule matches when a log line is being added. This record is only being read.';
        }
        $operator = (string) ($rule['operator'] ?? '');
        if ($operator === self::OP_BECOMES || $operator === self::OP_CHANGED || $operator === self::OP_FROM_TO) {
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
                $names[] = self::groups()[$id] ?? '';
            }
            return stripos(implode(', ', $names), $want) !== false;
        }
        if ($key === 'owner' || $key === 'creator') {
            $name = self::userName((int) $value['text']);
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
        $kind = (string) ($step['recipient'] ?? self::TO_OWNER);
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
        if ($kind === self::TO_ADDRESS) {
            foreach (preg_split('/[,;]+/', (string) ($step['address'] ?? '')) ?: [] as $part) {
                $part = trim($part);
                if (str_contains($part, '@')) {
                    $emails[$part] = $part;
                }
            }
        } elseif ($kind === self::TO_USER) {
            $addUser((int) ($step['user_id'] ?? 0));
        } elseif ($kind === self::TO_GROUP) {
            $addGroups([(int) ($step['group_id'] ?? 0)]);
        } elseif ($kind === self::TO_CREATOR) {
            $addUser($creatorId);
        } elseif ($kind === self::TO_BOTH) {
            $addUser($ownerId);
            $addUser($creatorId);
        } elseif ($kind === self::TO_OWNER_GROUPS) {
            $addGroups(GroupMembership::userGroupIds($ownerId));
        } elseif ($kind === self::TO_OWNER_AND_GROUPS) {
            $addUser($ownerId);
            $addGroups(GroupMembership::userGroupIds($ownerId));
        } elseif ($kind === self::TO_CREATOR_GROUPS) {
            $addGroups(GroupMembership::userGroupIds($creatorId));
        } elseif ($kind === self::TO_BOTH_GROUPS) {
            $addGroups(GroupMembership::userGroupIds($ownerId));
            $addGroups(GroupMembership::userGroupIds($creatorId));
        } elseif ($kind === self::TO_ITEM_GROUPS) {
            $addGroups(self::groupIdsFrom($current, $itemId));
        } else {
            $addUser($ownerId);
        }
        if ($skipActor) {
            $actor = (int) ($_SESSION['access_user_id'] ?? 0);
            foreach (self::emailsForUsers([$actor]) as $email) {
                unset($emails[$email]);
            }
        }
        return array_values($emails);
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
        return true;
    }

    /**
     * @param array<string, mixed> $rule
     * @param array<string, mixed> $step
     * @param array<string, mixed> $current
     */
    private static function setOwner(int $itemId, array $rule, array $step, array &$current): bool
    {
        $target = (string) ($step['action_value'] ?? '');
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
        Database::insert('core_log', [
            'item_id' => $itemId,
            'create_date' => (string) $now,
            'item_identifier' => 'rule-' . (int) ($rule['id'] ?? 0),
            'log_item_sequence' => $sequence,
            'log_text' => $text,
            'security_id' => (int) ($_SESSION['access_user_id'] ?? 0),
            'role_id' => (int) ($step['role_id'] ?? 5),
        ]);
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
            'ITEM_CREATOR' => self::userName((int) ($current['creator_security'] ?? 0)),
            'ITEM_OWNER' => self::userName((int) ($current['user_security'] ?? 0)),
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

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $fieldLabels
     */
    private static function stepSummary(array $step, array $fieldLabels): string
    {
        return match ((string) ($step['outcome'] ?? '')) {
            self::OUTCOME_EMAIL => 'Email ' . strtolower(self::recipients()[(string) ($step['recipient'] ?? '')] ?? 'the owner'),
            self::OUTCOME_SET_FIELD => 'Set ' . ($fieldLabels[(string) ($step['action_field'] ?? '')] ?? self::fieldLabel((string) ($step['action_field'] ?? '')))
                . ' to ' . (string) ($step['action_value'] ?? ''),
            self::OUTCOME_SET_OWNER => 'Set the owner to ' . self::ownerLabel((string) ($step['action_value'] ?? ''), []),
            default => 'Write a log line',
        };
    }

    private static function fieldLabel(string $key): string
    {
        if ($key === 'item_title') {
            return 'Title';
        }
        $id = ItemFields::fieldIdFromKey($key);
        if ($id === null || !Database::tableExists('custom_fields')) {
            return 'a field';
        }
        $field = Database::first('custom_fields', ['custom_field_name'], 'custom_field_id = ?', [$id]);
        $label = trim((string) ($field['custom_field_name'] ?? ''));
        return $label !== '' ? $label : 'a field';
    }

    /**
     * @param array<string, mixed> $current
     */
    private static function ownerLabel(string $value, array $current): string
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

    private static function userName(int $userId): string
    {
        if ($userId <= 0 || !Database::tableExists('users')) {
            return '';
        }
        $user = Database::first('users', ['user_name', 'first_name', 'last_name'], 'user_id = ?', [$userId]);
        if ($user === null) {
            return '';
        }
        $name = trim((string) ($user['user_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        }
        return $name;
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

    private static function ready(): bool
    {
        return Database::tableExists('application_rules');
    }
}
