<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\Rules;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Application\WorkflowFields;
use Adlexone\Auth\Csrf;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Html\Blocks;
use Adlexone\Html\Charts;
use Adlexone\Html\Timeline;
use Adlexone\Html\Form;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Http\Page;
use Adlexone\Http\Router;
use Adlexone\Http\View;
use Adlexone\support\Actions;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\FieldValidation;
use Adlexone\support\MenuOptions;

/**
 * Counts, owners, next steps, diary, parties, activity, files, ageing, register, and intake.
 */
final class RecordScreens
{
    /**
     * @param array<string, mixed> $config
     */
    public static function open(string $capability, array $config): void
    {
        $option = (string) ($_GET['option'] ?? '');
        if ($option === 'save_steps' && $capability === ScreenCatalog::STEPS) {
            self::saveSteps($config);
            return;
        }
        if ($option === 'save_owners' && $capability === ScreenCatalog::OWNERS) {
            self::saveOwners($config);
            return;
        }
        if ($option === 'save_intake' && $capability === ScreenCatalog::INTAKE) {
            self::saveIntake($config);
            return;
        }

        match ($capability) {
            ScreenCatalog::COUNTS => self::counts($config),
            ScreenCatalog::OWNERS => self::owners($config),
            ScreenCatalog::STEPS => self::steps($config),
            ScreenCatalog::DIARY => self::diary($config),
            ScreenCatalog::PARTIES => self::parties($config),
            ScreenCatalog::ACTIVITY => self::activity($config),
            ScreenCatalog::FILES => self::files($config),
            ScreenCatalog::AGE => self::age($config),
            ScreenCatalog::REGISTER => self::register($config),
            ScreenCatalog::INTAKE => self::intake($config),
            default => View::respond('This screen is not available.'),
        };
    }

    /**
     * Extra fields on Manage → Navigation for the screen that is selected.
     *
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    public static function navFields(string $capability, array $config, string $slug): array
    {
        $typeId = (int) (ctype_digit((string) ($config['item_type_id'] ?? '')) ? $config['item_type_id'] : 0);
        if ($typeId <= 0 && $slug !== '') {
            $fallback = ApplicationSettings::defaultItemTypeId($slug);
            $typeId = ctype_digit($fallback) ? (int) $fallback : 0;
        }
        $html = [];
        if (in_array($capability, [ScreenCatalog::DIARY, ScreenCatalog::STEPS, ScreenCatalog::AGE], true)) {
            $html['Open statuses'] = Form::text('open_statuses', implode(', ', RecordQuery::strings($config['open_statuses'] ?? [])))
                . '<p class="form-help">Leave blank to keep every status except the last one. Separate values with commas.</p>';
        }
        if ($capability === ScreenCatalog::ACTIVITY) {
            $html['Days'] = Form::text('days', (string) ($config['days'] ?? ''))
                . '<p class="form-help">How many days of log lines to show. Blank is 30.</p>';
            $groupIds = ['0'];
            $groupLabels = ['Everyone'];
            foreach (\Adlexone\Application\Rules::groups() as $id => $name) {
                $groupIds[] = (string) $id;
                $groupLabels[] = $name;
            }
            $html['Visible to'] = Form::select('group_id', $groupIds, $groupLabels, (string) ($config['group_id'] ?? '0'))
                . '<p class="form-help">Everyone includes a note that was not limited to a group.</p>';
        }
        if ($capability === ScreenCatalog::INTAKE) {
            $status = WorkScreen::workStatusField($typeId, $slug);
            $values = [''];
            $labels = ['First status'];
            foreach ($status['values'] ?? [] as $value) {
                $values[] = $value;
                $labels[] = $value;
            }
            $html['Opening status'] = Form::select('status_value', $values, $labels, (string) ($config['status_value'] ?? ''))
                . '<p class="form-help">The status written when the report is saved.</p>';
        }

        return $html;
    }

    /**
     * @return array<string, mixed>
     */
    public static function postedConfig(string $capability, string $itemTypeId, string $slug): array
    {
        $typeId = ctype_digit($itemTypeId) ? (int) $itemTypeId : 0;
        if ($typeId <= 0 && $slug !== '') {
            $fallback = ApplicationSettings::defaultItemTypeId($slug);
            $typeId = ctype_digit($fallback) ? (int) $fallback : 0;
        }
        $config = [];
        if (in_array($capability, [ScreenCatalog::DIARY, ScreenCatalog::STEPS, ScreenCatalog::AGE], true)) {
            $open = RecordQuery::strings((string) ($_POST['open_statuses'] ?? ''));
            if ($open !== []) {
                $config['open_statuses'] = $open;
            }
        }
        if ($capability === ScreenCatalog::ACTIVITY) {
            $days = (int) ($_POST['days'] ?? 0);
            if ($days > 0) {
                $config['days'] = (string) min($days, 365);
            }
            $groupId = (int) ($_POST['group_id'] ?? 0);
            if ($groupId > 0 && array_key_exists($groupId, \Adlexone\Application\Rules::groups())) {
                $config['group_id'] = (string) $groupId;
            }
        }
        if ($capability === ScreenCatalog::INTAKE) {
            $value = trim((string) ($_POST['status_value'] ?? ''));
            $status = WorkScreen::workStatusField($typeId, $slug);
            if ($value !== '' && $status !== null && in_array($value, $status['values'], true)) {
                $config['status_value'] = $value;
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function counts(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $field = self::chosen($loaded, $config, 'menu');
        if ($field === null) {
            self::missing('Add a menu field on the record type.');
            return;
        }
        $focus = (string) ($_GET['focus'] ?? '');
        $focusValue = (string) ($_GET['value'] ?? '');
        $isStatus = $field['plain'] === 'status' || str_contains($field['plain'], 'status');
        if (!$isStatus && $focus === 'value') {
            self::countRecords($loaded, $field, $focusValue);
            return;
        }
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $counts = [];
        foreach ($field['type'] === FieldTypes::MENU ? $field['values'] : [] as $value) {
            $counts[$value] = 0;
        }
        $empty = 0;
        foreach ($loaded['ids'] as $id) {
            $value = $values[$id] ?? '';
            if ($value === '') {
                $empty++;
                continue;
            }
            if (!array_key_exists($value, $counts)) {
                $counts[$value] = 0;
            }
            $counts[$value]++;
        }
        $rows = [];
        foreach ($counts as $value => $count) {
            $rows[] = self::linkRow((string) $value, self::countHref($field, (string) $value), ['count' => (string) $count], (string) $value);
        }
        if ($empty > 0) {
            $rows[] = self::linkRow('Not set', self::countHref($field, ''), ['count' => (string) $empty], '');
        }
        $help = $isStatus
            ? 'Each row opens Work on that value.'
            : 'Each row lists the records with that value.';
        $parts = [];
        $barRows = [];
        $usedTones = [];
        foreach ($counts as $value => $count) {
            $tone = $isStatus ? Presence::tone((string) $value) : '';
            if ($tone !== '' && isset($usedTones[$tone])) {
                $tone = '';
            }
            $usedTones[$tone] = true;
            $parts[] = ['label' => (string) $value, 'value' => $count, 'tone' => $tone];
            $barRows[] = ['label' => (string) $value, 'value' => $count, 'tone' => $tone, 'href' => self::countHref($field, (string) $value)];
        }
        if ($empty > 0) {
            $parts[] = ['label' => 'Not set', 'value' => $empty, 'tone' => 'neutral'];
            $barRows[] = ['label' => 'Not set', 'value' => $empty, 'tone' => 'neutral', 'href' => self::countHref($field, '')];
        }
        $total = count($loaded['ids']);
        $largest = $counts === [] ? '' : (string) array_search(max($counts), $counts, true);
        $lead = Charts::kpis([
            ['label' => 'Records', 'value' => (string) $total, 'icon' => 'ic-files', 'tone' => 'brand'],
            ['label' => 'Values in use', 'value' => (string) count(array_filter($counts)), 'icon' => 'ic-counts', 'tone' => 'info'],
            ['label' => 'Most common', 'value' => $largest !== '' && max($counts) > 0 ? $largest : 'None', 'note' => $largest !== '' && max($counts) > 0 ? self::plural(max($counts), 'record') : '', 'icon' => 'ic-trend-up', 'tone' => 'success'],
            ['label' => 'Not set', 'value' => (string) $empty, 'icon' => 'ic-circle-alert', 'tone' => $empty > 0 ? 'warning' : 'neutral'],
        ]);
        if ($total > 0) {
            $lead .= Charts::row([
                Charts::panel('Share of records', Charts::donut($parts, (string) $total, 'records')),
                Charts::panel('Records by ' . strtolower($field['label']), Charts::bars($barRows)),
            ]);
        }
        self::show($field['label'], $help, Blocks::recordList([
            'column' => $field['label'],
            'columns' => [['key' => 'count', 'label' => 'Records']],
            'searchLabel' => 'Search',
            'empty' => 'No records yet',
            'emptyIllustration' => 'empty-work',
            'emptyTitle' => 'No records yet',
            'emptyCopy' => 'Create the first record.',
            'groups' => [['rows' => $rows]],
        ]), $lead);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function owners(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $people = Rules::people();
        $groups = [];
        foreach ($loaded['items'] as $item) {
            $key = $item['owner_id'] > 0 ? (string) $item['owner_id'] : '0';
            $groups[$key][] = $item;
        }
        $ordered = [];
        if (isset($groups['0'])) {
            $ordered['No owner'] = $groups['0'];
            unset($groups['0']);
        }
        $named = [];
        foreach ($groups as $id => $items) {
            $named[$people[(int) $id] ?? ($items[0]['owner'] !== '' ? $items[0]['owner'] : 'Owner ' . $id)] = $items;
        }
        ksort($named);
        $blocks = [];
        foreach (array_merge($ordered, $named) as $label => $items) {
            $rows = [];
            foreach ($items as $item) {
                $rows[] = [
                    'name' => self::title($item),
                    'href' => self::itemHref($item['id']),
                    'mark' => $item['owner'],
                    'control' => self::ownerSelect($item['id'], $item['owner_id'], $people, $item['owner']),
                    'search' => $item['owner'],
                ];
            }
            $blocks[] = ['label' => (string) $label, 'rows' => $rows];
        }
        $ownerBars = [];
        foreach (array_merge($ordered, $named) as $label => $items) {
            $ownerBars[] = ['label' => (string) $label, 'value' => count($items), 'tone' => $label === 'No owner' ? 'warning' : ''];
        }
        usort($ownerBars, static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
        $unowned = count($ordered['No owner'] ?? []);
        $lead = Charts::kpis([
            ['label' => 'Records', 'value' => (string) count($loaded['items']), 'icon' => 'ic-files', 'tone' => 'brand'],
            ['label' => 'Owners', 'value' => (string) count($named), 'icon' => 'ic-owners', 'tone' => 'info'],
            ['label' => 'No owner', 'value' => (string) $unowned, 'note' => $unowned > 0 ? 'Needs an owner' : 'Every record has one', 'icon' => 'ic-user-plus', 'tone' => $unowned > 0 ? 'warning' : 'success'],
        ]);
        if (count($ownerBars) > 0 && count($loaded['items']) > 0) {
            $lead .= Charts::row([Charts::panel('Records per owner', Charts::bars($ownerBars, '', true))]);
        }
        self::show('Owners', 'Records with no owner are listed first. Saving sets the owner and runs the update rules.', self::editor(
            'Record',
            [],
            $blocks,
            self::base() . '&option=save_owners',
            'Save',
            'No records yet',
            'Create the first record.'
        ), $lead);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function saveOwners(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $allowed = array_map('strval', array_keys(Rules::people()));
        $allowed[] = '0';
        foreach ($loaded['items'] as $item) {
            $key = 'owner_' . $item['id'];
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $next = trim((string) $_POST[$key]);
            if (!in_array($next, $allowed, true) || (int) $next === $item['owner_id']) {
                continue;
            }
            RecordQuery::saveOwner($item['id'], (int) $next);
        }
        self::back();
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function steps(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $field = self::chosen($loaded, $config, 'step');
        if ($field === null || !in_array($field['type'], [FieldTypes::TEXT_BOX, FieldTypes::TEXT_AREA], true)) {
            self::missing('Add a text field for the next step on the record type.');
            return;
        }
        $open = self::openSet($loaded, $config);
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $statusValues = self::statusValues($loaded);
        $empty = [];
        $filled = [];
        foreach ($loaded['items'] as $item) {
            if (!RecordQuery::isOpen($open, $statusValues[$item['id']] ?? '')) {
                continue;
            }
            $value = $values[$item['id']] ?? '';
            $row = [
                'name' => self::title($item),
                'href' => self::itemHref($item['id']),
                'mark' => $item['owner'],
                'cells' => ['stand' => $statusValues[$item['id']] ?? ''],
                'tones' => ['stand' => Presence::tone($statusValues[$item['id']] ?? '')],
                'control' => Form::text('step_' . $item['id'], $value),
                'search' => $value . ' ' . $item['owner'],
            ];
            if ($value === '') {
                $empty[] = $row;
            } else {
                $filled[] = $row;
            }
        }
        $groups = [];
        if ($empty !== []) {
            $groups[] = ['label' => 'No next step', 'rows' => $empty];
        }
        if ($filled !== []) {
            $groups[] = ['label' => 'Next step', 'rows' => $filled];
        }
        $openCount = count($empty) + count($filled);
        $lead = Charts::kpis([
            ['label' => 'Open records', 'value' => (string) $openCount, 'icon' => 'ic-files', 'tone' => 'brand'],
            ['label' => 'With a next step', 'value' => (string) count($filled), 'icon' => 'ic-circle-check', 'tone' => 'success'],
            ['label' => 'No next step', 'value' => (string) count($empty), 'note' => $empty === [] ? '' : 'Needs a decision', 'icon' => 'ic-circle-alert', 'tone' => $empty === [] ? 'neutral' : 'warning'],
        ]);
        if ($openCount > 0) {
            $lead .= Charts::row([Charts::panel('Open records with a next step', Charts::stack([
                ['label' => 'Next step set', 'value' => count($filled), 'tone' => 'success'],
                ['label' => 'No next step', 'value' => count($empty), 'tone' => 'warning'],
            ]))]);
        }
        self::show('Next steps', 'Finished records stay off this list. Saving writes ' . $field['label'] . ' and runs the update rules.', self::editor(
            'Record',
            [['key' => 'stand', 'label' => 'Status']],
            $groups,
            self::base() . '&option=save_steps',
            'Save',
            'No open records',
            'Finished records stay off this list.'
        ), $lead);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function saveSteps(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $field = self::chosen($loaded, $config, 'step');
        if ($field === null) {
            self::back();
            return;
        }
        $open = self::openSet($loaded, $config);
        $current = RecordQuery::values($loaded['ids'], $field['id']);
        $statusValues = self::statusValues($loaded);
        foreach ($loaded['items'] as $item) {
            if (!RecordQuery::isOpen($open, $statusValues[$item['id']] ?? '')) {
                continue;
            }
            $key = 'step_' . $item['id'];
            if (!array_key_exists($key, $_POST)) {
                continue;
            }
            $next = trim((string) $_POST[$key]);
            if ($next === ($current[$item['id']] ?? '')) {
                continue;
            }
            RecordQuery::saveField($item['id'], $field['id'], $next);
        }
        self::back();
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function diary(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $field = self::chosen($loaded, $config, 'date');
        if ($field === null || !in_array($field['type'], [FieldTypes::DATE, FieldTypes::DATETIME], true)) {
            self::missing('Add a date field on the record type.');
            return;
        }
        $open = self::openSet($loaded, $config);
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $statusValues = self::statusValues($loaded);
        $today = new \DateTimeImmutable('today');
        $sunday = $today->modify('+' . (7 - (int) $today->format('N')) . ' days');
        $buckets = ['Overdue' => [], 'This week' => [], 'Later' => [], 'No date' => []];
        $offsets = [];
        foreach ($loaded['items'] as $item) {
            if (!RecordQuery::isOpen($open, $statusValues[$item['id']] ?? '')) {
                continue;
            }
            $raw = $values[$item['id']] ?? '';
            $date = FieldValidation::parseDate($raw);
            $row = self::recordRow($item, [
                'stand' => $statusValues[$item['id']] ?? '',
                'when' => $date === null ? '' : RecordQuery::display($field, $raw),
            ], $item['owner']);
            if ($date === null) {
                $buckets['No date'][] = $row;
                continue;
            }
            $day = $date->setTime(0, 0);
            $row['sort'] = $day->getTimestamp();
            $offset = (int) round(($day->getTimestamp() - $today->getTimestamp()) / 86400);
            [$row['cells']['due'], $row['tones']['due']] = self::dueLabel($offset);
            $offsets[] = $offset;
            if ($day < $today) {
                $buckets['Overdue'][] = $row;
            } elseif ($day <= $sunday) {
                $buckets['This week'][] = $row;
            } else {
                $buckets['Later'][] = $row;
            }
        }
        foreach ($buckets as $name => $rows) {
            usort($rows, static fn (array $left, array $right): int => ((int) ($left['sort'] ?? 0)) <=> ((int) ($right['sort'] ?? 0)));
            $buckets[$name] = $rows;
        }
        $lead = Charts::kpis([
            ['label' => 'Overdue', 'value' => (string) count($buckets['Overdue']), 'icon' => 'ic-alert', 'tone' => $buckets['Overdue'] === [] ? 'success' : 'danger', 'note' => $buckets['Overdue'] === [] ? 'Nothing is late' : 'Needs attention'],
            ['label' => 'This week', 'value' => (string) count($buckets['This week']), 'icon' => 'ic-diary', 'tone' => 'warning'],
            ['label' => 'Later', 'value' => (string) count($buckets['Later']), 'icon' => 'ic-clock', 'tone' => 'info'],
            ['label' => 'No date', 'value' => (string) count($buckets['No date']), 'icon' => 'ic-circle-alert', 'tone' => 'neutral'],
        ]);
        $columns = [['label' => 'Overdue', 'value' => 0, 'tone' => 'danger']];
        for ($i = 0; $i < 7; $i++) {
            $columns[] = ['label' => $i === 0 ? 'Today' : $today->modify('+' . $i . ' days')->format('D'), 'value' => 0, 'tone' => $i === 0 ? 'warning' : 'info'];
        }
        $columns[] = ['label' => 'Later', 'value' => 0, 'tone' => 'neutral'];
        foreach ($offsets as $offset) {
            $slot = $offset < 0 ? 0 : ($offset <= 6 ? $offset + 1 : 8);
            $columns[$slot]['value']++;
        }
        if ($offsets !== []) {
            $lead .= Charts::row([Charts::panel('Due over the next week', Charts::columns($columns), 'Open records by ' . strtolower($field['label']) . '.')]);
        }
        self::show('Diary', 'Grouped by ' . $field['label'] . '. Finished records stay off this list.', self::groupedRecords(
            $buckets,
            [['key' => 'stand', 'label' => 'Status'], ['key' => 'when', 'label' => $field['label']], ['key' => 'due', 'label' => 'Due']],
            'No open records',
            'Finished records stay off this list.'
        ), $lead);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function parties(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordQuery::chosen($loaded['fields'], $config, 'party');
        if ($field === null) {
            self::missing('Add a text field for the party on the record type.');
            return;
        }
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $step = self::chosen($loaded, [], 'step');
        $steps = $step === null ? [] : RecordQuery::values($loaded['ids'], $step['id']);
        $statusValues = self::statusValues($loaded);
        $groups = [];
        foreach ($loaded['items'] as $item) {
            $party = $values[$item['id']] ?? '';
            $label = $party === '' ? 'No ' . strtolower($field['label']) : $party;
            $groups[$label][] = self::recordRow($item, [
                'stand' => $statusValues[$item['id']] ?? '',
                'next' => $steps[$item['id']] ?? '',
            ], $party);
        }
        $emptyLabel = 'No ' . strtolower($field['label']);
        $emptyRows = $groups[$emptyLabel] ?? [];
        unset($groups[$emptyLabel]);
        ksort($groups);
        if ($emptyRows !== []) {
            $groups = [$emptyLabel => $emptyRows] + $groups;
        }
        $blocks = [];
        foreach ($groups as $label => $rows) {
            $blocks[] = ['label' => $label, 'rows' => $rows];
        }
        $columns = [['key' => 'stand', 'label' => 'Status']];
        if ($step !== null) {
            $columns[] = ['key' => 'next', 'label' => $step['label']];
        }
        $partyBars = [];
        foreach ($blocks as $block) {
            $partyBars[] = ['label' => (string) $block['label'], 'value' => count($block['rows']), 'tone' => (string) $block['label'] === $emptyLabel ? 'neutral' : ''];
        }
        usort($partyBars, static fn (array $left, array $right): int => $right['value'] <=> $left['value']);
        $lead = Charts::kpis([
            ['label' => 'Records', 'value' => (string) count($loaded['items']), 'icon' => 'ic-files', 'tone' => 'brand'],
            ['label' => $field['label'] . ' count', 'value' => (string) count(array_filter($blocks, static fn (array $block): bool => (string) $block['label'] !== $emptyLabel)), 'icon' => 'ic-parties', 'tone' => 'info'],
            ['label' => 'Without ' . strtolower($field['label']), 'value' => (string) count($emptyRows), 'icon' => 'ic-circle-alert', 'tone' => $emptyRows === [] ? 'success' : 'warning'],
        ]);
        if ($partyBars !== []) {
            $lead .= Charts::row([Charts::panel('Records by ' . strtolower($field['label']), Charts::bars(array_slice($partyBars, 0, 8), '', true), count($partyBars) > 8 ? 'The eight with the most records.' : '')]);
        }
        self::show('Parties', 'Grouped by ' . $field['label'] . '.', $blocks === []
            ? self::emptyList('No records yet', 'Create the first record.')
            : Blocks::recordList([
                'column' => 'Record',
                'columns' => $columns,
                'searchLabel' => 'Search',
                'empty' => 'No records yet',
                'groups' => $blocks,
            ]), $lead);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function activity(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $days = (int) ($config['days'] ?? 30);
        if ($days <= 0) {
            $days = 30;
        }
        $days = min($days, 365);
        $groupId = (int) ($config['group_id'] ?? 0);
        $rows = [];
        $lines = [];
        $names = [];
        if ($loaded['ids'] !== []) {
            $sql = 'SELECT item_id, log_text, create_date, security_id, role_id'
                . (Database::columnExists('core_log', 'group_id') ? ', group_id' : '')
                . ' FROM core_log WHERE item_id IN ('
                . RecordQuery::intList($loaded['ids']) . ') AND CAST(create_date AS INTEGER) >= ?';
            $params = [time() - ($days * 86400)];
            if ($groupId > 0 && Database::columnExists('core_log', 'group_id')) {
                $sql .= ' AND (group_id = 0 OR group_id = ?)';
                $params[] = $groupId;
            }
            $sql .= ' ORDER BY CAST(create_date AS INTEGER) DESC LIMIT 200';
            $lines = array_values(array_filter(
                Database::rows($sql, $params),
                static fn (array $line): bool => \Adlexone\Data\GroupMembership::canSeeLogLine((int) ($line['group_id'] ?? 0), (int) ($line['role_id'] ?? 5))
            ));
            $names = RecordQuery::userNames(array_map(static fn (array $row): int => (int) ($row['security_id'] ?? 0), $lines));
            foreach ($lines as $line) {
                $itemId = (int) $line['item_id'];
                $item = $loaded['items'][$itemId] ?? null;
                if ($item === null) {
                    continue;
                }
                $when = (int) ($line['create_date'] ?? 0);
                $who = $names[(int) ($line['security_id'] ?? 0)] ?? '';
                $text = trim((string) ($line['log_text'] ?? ''));
                $rows[] = [
                    'name' => $text === '' ? 'Log line' : $text,
                    'href' => self::itemHref($itemId),
                    'meta' => self::title($item),
                    'mark' => $who,
                    'cells' => ['when' => Presence::ago($when)],
                    'titles' => ['when' => $when > 0 ? date((string) SET_DATE_FORMAT, $when) : ''],
                    'search' => $who . ' ' . self::title($item),
                ];
            }
        }
        $help = 'Log lines from the last ' . $days . ($days === 1 ? ' day.' : ' days.');
        $events = [];
        $perDay = [];
        $people = [];
        $touched = [];
        foreach ($lines as $line) {
            $itemId = (int) $line['item_id'];
            $item = $loaded['items'][$itemId] ?? null;
            if ($item === null) {
                continue;
            }
            $when = (int) ($line['create_date'] ?? 0);
            $who = $names[(int) ($line['security_id'] ?? 0)] ?? '';
            $text = trim((string) ($line['log_text'] ?? ''));
            $events[] = [
                'text' => $text === '' ? 'Log line' : $text,
                'when' => $when,
                'who' => $who,
                'href' => self::itemHref($itemId),
                'icon' => 'ic-activity',
                'detail' => self::title($item),
            ];
            $perDay[date('Y-m-d', $when)] = ($perDay[date('Y-m-d', $when)] ?? 0) + 1;
            if ($who !== '') {
                $people[$who] = true;
            }
            $touched[$itemId] = true;
        }
        $span = min($days, 14);
        $columns = [];
        for ($i = $span - 1; $i >= 0; $i--) {
            $stamp = strtotime('-' . $i . ' days');
            $columns[] = ['label' => $span > 7 ? date('j', $stamp) : date('D', $stamp), 'value' => $perDay[date('Y-m-d', $stamp)] ?? 0];
        }
        $lead = Charts::kpis([
            ['label' => 'Log lines', 'value' => (string) count($events), 'icon' => 'ic-activity', 'tone' => 'brand'],
            ['label' => 'Records changed', 'value' => (string) count($touched), 'icon' => 'ic-files', 'tone' => 'info'],
            ['label' => 'People active', 'value' => (string) count($people), 'icon' => 'ic-users', 'tone' => 'success'],
        ]);
        if ($events !== []) {
            $lead .= Charts::row([Charts::panel('Log lines per day', Charts::columns($columns, true), 'The last ' . $span . ($span === 1 ? ' day.' : ' days.'))]);
        }
        $timeline = '<section class="chartcard">' . Timeline::render($events, 'No log lines in this period. Lines written on these records show up here.') . '</section>';
        if ($events !== []) {
            self::show('Activity', $help, $timeline, $lead);
            return;
        }
        self::show('Activity', $help, Blocks::recordList([
            'column' => 'Line',
            'columns' => [['key' => 'when', 'label' => 'When']],
            'searchLabel' => 'Search',
            'empty' => 'No log lines in this period',
            'emptyIllustration' => 'empty-work',
            'emptyTitle' => 'No log lines in this period',
            'emptyCopy' => 'Lines written on these records show up here.',
            'groups' => [['rows' => $rows]],
        ]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function files(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $rows = [];
        if ($loaded['ids'] !== []) {
            $files = Database::select(
                'item_attachments',
                ['id', 'item_id', 'file_name', 'file_type', 'file_size', 'added_by', 'create_date'],
                'item_id IN (' . RecordQuery::intList($loaded['ids']) . ')',
                [],
                'create_date DESC'
            );
            $names = RecordQuery::userNames(array_map(static fn (array $row): int => (int) ($row['added_by'] ?? 0), $files));
            foreach ($files as $file) {
                $itemId = (int) ($file['item_id'] ?? 0);
                $item = $loaded['items'][$itemId] ?? null;
                if ($item === null) {
                    continue;
                }
                $stored = (string) ($file['file_name'] ?? '');
                $label = preg_replace('/^[A-Za-z0-9]+_/', '', $stored, 1);
                if (!is_string($label) || $label === '') {
                    $label = $stored;
                }
                $bytes = (int) ($file['file_size'] ?? 0);
                $size = $bytes < 1000 ? $bytes . ' bytes' : (string) round($bytes / 1000) . ' KB';
                $who = $names[(int) ($file['added_by'] ?? 0)] ?? '';
                $when = (int) ($file['create_date'] ?? 0);
                $rows[] = [
                    'name' => $label,
                    'href' => self::base() . '&option=download_attachment&id=' . (int) $file['id'],
                    'meta' => self::title($item),
                    'mark' => $who,
                    'cells' => [
                        'size' => $size,
                        'when' => $when > 0 ? Presence::ago($when) : '',
                    ],
                    'titles' => ['when' => $when > 0 ? date((string) SET_DATE_FORMAT, $when) : ''],
                    'search' => (string) ($file['file_type'] ?? '') . ' ' . $who,
                ];
            }
        }
        self::show('Files', 'Files on the records you can open. The download is the one on the record.', Blocks::recordList([
            'column' => 'File',
            'columns' => [
                ['key' => 'size', 'label' => 'Size'],
                ['key' => 'when', 'label' => 'Added'],
            ],
            'searchLabel' => 'Search',
            'empty' => 'No files yet',
            'emptyIllustration' => 'empty-work',
            'emptyTitle' => 'No files yet',
            'emptyCopy' => 'A file added to a record is listed here.',
            'groups' => [['rows' => $rows]],
        ]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function age(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordQuery::field($loaded['fields'], (int) ($config['field_id'] ?? 0));
        $fromField = $field !== null && in_array($field['type'], [FieldTypes::DATE, FieldTypes::DATETIME], true);
        $open = self::openSet($loaded, $config);
        $values = $fromField ? RecordQuery::values($loaded['ids'], $field['id']) : [];
        $statusValues = self::statusValues($loaded);
        $today = new \DateTimeImmutable('today');
        $dated = [];
        $undated = [];
        foreach ($loaded['items'] as $item) {
            if (!RecordQuery::isOpen($open, $statusValues[$item['id']] ?? '')) {
                continue;
            }
            if ($fromField) {
                $date = FieldValidation::parseDate($values[$item['id']] ?? '');
                $stamp = $date === null ? 0 : $date->getTimestamp();
            } else {
                $date = $item['created'] > 0 ? (new \DateTimeImmutable('@' . $item['created']))->setTime(0, 0) : null;
                $stamp = $item['created'];
            }
            $row = self::recordRow($item, [
                'stand' => $statusValues[$item['id']] ?? '',
                'age' => self::ageLabel($date, $today),
                'when' => $fromField
                    ? RecordQuery::display($field, $values[$item['id']] ?? '')
                    : ($item['created'] > 0 ? \Adlexone\Html\Stamp::day($item['created']) : ''),
            ], $item['owner']);
            $row['sort'] = $stamp;
            if ($date === null) {
                $undated[] = $row;
            } else {
                $dated[] = $row;
            }
        }
        usort($dated, static fn (array $left, array $right): int => ($left['sort'] <=> $right['sort']));
        $buckets = [];
        if ($dated !== []) {
            $buckets[''] = $dated;
        }
        if ($undated !== []) {
            $buckets['No date'] = $undated;
        }
        $whenLabel = $fromField ? $field['label'] : 'Opened';
        $help = $fromField
            ? 'Open records, oldest first, aged from ' . $field['label'] . '.'
            : 'Open records, oldest first, from the day they were created.';
        $bands = [
            ['label' => 'Under a week', 'value' => 0, 'tone' => 'success'],
            ['label' => 'One to four weeks', 'value' => 0, 'tone' => 'info'],
            ['label' => 'One to three months', 'value' => 0, 'tone' => 'warning'],
            ['label' => 'Over three months', 'value' => 0, 'tone' => 'danger'],
        ];
        $days = [];
        foreach ($dated as $row) {
            $age = max(0, (int) floor(($today->getTimestamp() - (int) $row['sort']) / 86400));
            $days[] = $age;
            $bands[$age < 7 ? 0 : ($age < 30 ? 1 : ($age < 90 ? 2 : 3))]['value']++;
        }
        $lead = Charts::kpis([
            ['label' => 'Open records', 'value' => (string) (count($dated) + count($undated)), 'icon' => 'ic-files', 'tone' => 'brand'],
            ['label' => 'Oldest', 'value' => $days === [] ? 'None' : max($days) . ($days !== [] && max($days) === 1 ? ' day' : ' days'), 'icon' => 'ic-ageing', 'tone' => 'warning'],
            ['label' => 'Average age', 'value' => $days === [] ? 'None' : (int) round(array_sum($days) / count($days)) . ' days', 'icon' => 'ic-clock', 'tone' => 'info'],
            ['label' => 'Over 30 days', 'value' => (string) ($bands[2]['value'] + $bands[3]['value']), 'icon' => 'ic-alert', 'tone' => $bands[2]['value'] + $bands[3]['value'] > 0 ? 'danger' : 'success'],
        ]);
        if ($days !== []) {
            $lead .= Charts::row([Charts::panel('Open records by age', Charts::columns($bands), $help)]);
        }
        self::show('Ageing', $help, self::groupedRecords(
            $buckets,
            [
                ['key' => 'stand', 'label' => 'Status'],
                ['key' => 'age', 'label' => 'Age'],
                ['key' => 'when', 'label' => $whenLabel],
            ],
            'No open records',
            'Finished records stay off this list.'
        ), $lead);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function register(array $config): void
    {
        $loaded = self::need($config);
        if ($loaded === null) {
            return;
        }
        $chosen = self::listedFields($loaded['fields'], $config);
        if ($chosen === []) {
            self::missing('Add fields to this record type.');
            return;
        }
        $map = RecordQuery::valueMap($loaded['ids'], array_map(static fn (array $field): int => $field['id'], $chosen));
        $statusValues = self::statusValues($loaded);
        $rows = [];
        foreach ($loaded['items'] as $item) {
            $cells = [];
            $search = '';
            foreach ($chosen as $field) {
                $shown = RecordQuery::display($field, $map[$item['id']][$field['id']] ?? '');
                $cells['f' . $field['id']] = $shown;
                $search .= ' ' . $shown;
            }
            $row = self::recordRow($item, $cells, $item['owner'] . $search);
            $row['cells']['stand'] = $statusValues[$item['id']] ?? '';
            $rows[] = $row;
        }
        $statusField = WorkScreen::workStatusField($loaded['type_id']);
        $statusId = $statusField['id'] ?? 0;
        $columns = [['key' => 'stand', 'label' => 'Status']];
        foreach ($chosen as $field) {
            if ($statusId > 0 && $field['id'] === $statusId) {
                continue;
            }
            $columns[] = [
                'key' => 'f' . $field['id'],
                'label' => $field['label'],
                'wrap' => $field['type'] === FieldTypes::TEXT_AREA,
            ];
        }
        self::show('Register', 'One row per record.', Blocks::recordList([
            'column' => 'Record',
            'columns' => $columns,
            'searchLabel' => 'Search',
            'empty' => 'No records yet',
            'emptyIllustration' => 'empty-work',
            'emptyTitle' => 'No records yet',
            'emptyCopy' => 'Create the first record.',
            'groups' => [['rows' => $rows]],
        ]));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function intake(array $config): void
    {
        $typeId = RecordQuery::typeId($config);
        if ($typeId <= 0) {
            self::missing('Choose a record type for this link under Configure, Applications.');
            return;
        }
        if (!ItemScreen::applicationAllowsItemType($typeId)) {
            \Adlexone\Auth\Access::deny();
        }
        $fields = RecordQuery::fields($typeId);
        $shown = self::intakeFields($fields, $config);
        $status = WorkScreen::workStatusField($typeId);
        $opening = self::openingStatus($config, $status);
        $posted = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
        $errors = [];
        if ($posted && (string) ($_GET['option'] ?? '') === 'save_intake') {
            $errors = self::intakeErrors($typeId);
        }
        $formFields = [];
        $formFields['Name'] = Form::text('item_title', $posted ? (string) ($_POST['item_title'] ?? '') : '');
        foreach ($shown as $field) {
            if ($status !== null && $field['id'] === $status['id']) {
                continue;
            }
            $value = $posted ? (string) ($_POST['custom_field_' . $field['id']] ?? '') : '';
            $formFields[$field['label']] = self::control($field, $value);
        }
        $hidden = Form::hidden('item_type_id', (string) $typeId);
        if ($status !== null && $opening !== '') {
            $hidden .= Form::hidden('custom_field_' . $status['id'], $opening);
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $hidden .= Form::hidden('creator_security', (string) $userId);
        $hidden .= Form::hidden('user_security', (string) $userId);
        foreach (GroupMembership::itemTypeGroupIds($typeId) as $groupId) {
            $hidden .= Form::hidden('group_' . $groupId, (string) $groupId);
        }
        $hidden .= Form::hidden('groups_posted', '1');
        $formFields[''] = $hidden;
        $type = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId]);
        $typeName = trim((string) ($type['item_type_name'] ?? 'record'));
        $options = [
            'kicker' => $typeName,
            'record' => true,
        ];
        if ($opening !== '') {
            $options['badge'] = $opening;
        }
        if ($errors !== []) {
            $options['errors'] = $errors;
            $options['error_title'] = 'Check the form';
        }
        $help = $opening !== ''
            ? '<p class="form-help">This sets the status to ' . Html::e($opening) . '. The create rules then run.</p>'
            : '';
        Page::current()->setBody($help . Form::form(
            'New ' . $typeName,
            self::base() . '&option=save_intake',
            $formFields,
            [Form::button('submit', 'submit_button', 'Save')]
            ,
            $options
        ));
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function saveIntake(array $config): void
    {
        $typeId = RecordQuery::typeId($config);
        if ($typeId <= 0 || !ItemScreen::applicationAllowsItemType($typeId)) {
            self::missing('Choose a record type for this link under Configure, Applications.');
            return;
        }
        if (self::intakeErrors($typeId) !== []) {
            self::intake($config);
            return;
        }
        $fields = RecordQuery::fields($typeId);
        $status = WorkScreen::workStatusField($typeId);
        $opening = self::openingStatus($config, $status);
        MenuOptions::collapseRequest($_POST);
        $payload = [];
        foreach ($fields as $field) {
            $key = 'custom_field_' . $field['id'];
            if (array_key_exists($key, $_POST) && !is_array($_POST[$key])) {
                $payload[$key] = (string) $_POST[$key];
            }
        }
        if ($status !== null && $opening !== '') {
            $payload['custom_field_' . $status['id']] = $opening;
        }
        $userId = (int) ($_SESSION['access_user_id'] ?? 0);
        $itemId = Database::insert('items', [
            'create_date' => time(),
            'core_log_updated' => time(),
            'item_type_id' => $typeId,
            'creator_security' => $userId,
            'user_security' => $userId,
            'item_title' => trim((string) ($_POST['item_title'] ?? '')),
        ]);
        ItemFields::saveFromArray($itemId, $payload);
        GroupMembership::setItemGroups($itemId, GroupMembership::idsFromPost($_POST));
        $_POST = array_merge($_POST, $payload);
        Actions::executeAction($itemId, 'create_item', false);
        $createdTitle = trim((string) ($_POST['item_title'] ?? ''));
        View::flash(($createdTitle !== '' ? $createdTitle : 'The record') . ' created.');
        header('Location: ' . self::base() . '&item=' . $itemId);
        exit;
    }

    /**
     * @return array<string, string>
     */
    private static function intakeErrors(int $typeId): array
    {
        $errors = [];
        if (trim((string) ($_POST['item_title'] ?? '')) === '') {
            $errors['item_title'] = 'Enter a name.';
        }

        return $errors + FieldValidation::postedErrors($_POST, $typeId);
    }

    /**
     * @param array<string, mixed> $config
     * @return array{type_id: int, ids: list<int>, items: array<int, array{id: int, title: string, created: int, owner_id: int, owner: string}>, fields: list<array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}>}|null
     */
    private static function need(array $config): ?array
    {
        $loaded = RecordQuery::load($config);
        if ($loaded === null) {
            self::missing('Choose a record type for this link under Configure, Applications.');
            return null;
        }
        $loaded['fields'] = RecordQuery::fields($loaded['type_id']);

        return $loaded;
    }

    /**
     * Open statuses named on this screen, or the ones stored with the application.
     * A screen with no list of its own uses the blueprint endings, so both Approved
     * and Declined stay off the diary.
     *
     * @param array{type_id: int} $loaded
     * @param array<string, mixed> $config
     * @return list<string>|null
     */
    private static function openSet(array $loaded, array $config): ?array
    {
        $status = WorkScreen::workStatusField((int) $loaded['type_id']);
        if (RecordQuery::strings($config['open_statuses'] ?? []) !== []) {
            return RecordQuery::openValues($config, $status);
        }
        $slug = Page::current()->inApplication() ? Page::current()->applicationSlug() : '';

        return WorkflowFields::openValues($slug, (int) $loaded['type_id'], $status);
    }

    /**
     * @param array{type_id: int, ids: list<int>} $loaded
     * @return array<int, string>
     */
    private static function statusValues(array $loaded): array
    {
        $status = WorkScreen::workStatusField($loaded['type_id']);
        if ($status === null) {
            return [];
        }

        return RecordQuery::values($loaded['ids'], $status['id']);
    }

    /**
     * @param list<array{id: int, plain: string, type: string, label: string}> $fields
     * @param array<string, mixed> $config
     * @return list<array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}>
     */
    private static function listedFields(array $fields, array $config): array
    {
        $wanted = RecordQuery::ints($config['field_ids'] ?? []);
        if ($wanted === []) {
            return $fields;
        }
        $chosen = [];
        foreach ($fields as $field) {
            if (in_array($field['id'], $wanted, true)) {
                $chosen[] = $field;
            }
        }

        return $chosen === [] ? $fields : $chosen;
    }

    /**
     * @param list<array{id: int, plain: string, type: string, label: string}> $fields
     * @param array<string, mixed> $config
     * @return list<array{id: int, plain: string, type: string, label: string, values: list<string>}>
     */
    private static function intakeFields(array $fields, array $config): array
    {
        $wanted = RecordQuery::ints($config['field_ids'] ?? []);
        if ($wanted !== []) {
            $chosen = [];
            foreach ($fields as $field) {
                if (in_array($field['id'], $wanted, true)) {
                    $chosen[] = $field;
                }
            }
            if ($chosen !== []) {
                return self::withRequired($fields, $chosen);
            }
        }
        $chosen = [];
        foreach ($fields as $field) {
            if ($field['plain'] === 'next step' || $field['plain'] === 'status') {
                continue;
            }
            if (($field['type'] === FieldTypes::DATE || $field['type'] === FieldTypes::DATETIME) && str_ends_with($field['plain'], ' on')) {
                continue;
            }
            if (in_array($field['type'], [FieldTypes::TEXT_BOX, FieldTypes::TEXT_AREA, FieldTypes::MENU, FieldTypes::CHECK_BOX, FieldTypes::NUMBER, FieldTypes::EMAIL, FieldTypes::DATE, FieldTypes::DATETIME], true)) {
                $chosen[] = $field;
            }
        }

        return $chosen;
    }

    /**
     * @param list<array{id: int}> $fields
     * @param list<array{id: int}> $chosen
     * @return list<array{id: int}>
     */
    private static function withRequired(array $fields, array $chosen): array
    {
        $have = [];
        foreach ($chosen as $field) {
            $have[$field['id']] = true;
        }
        foreach ($fields as $field) {
            if (isset($have[$field['id']])) {
                continue;
            }
            $row = Database::first('custom_fields', ['required'], 'custom_field_id = ?', [$field['id']]);
            if ($row !== null && strcasecmp((string) ($row['required'] ?? ''), 'Yes') === 0) {
                $chosen[] = $field;
            }
        }

        return $chosen;
    }

    /**
     * @param array{id: int, values: list<string>}|null $status
     * @param array<string, mixed> $config
     */
    private static function openingStatus(array $config, ?array $status): string
    {
        if ($status === null || $status['values'] === []) {
            return '';
        }
        $value = trim((string) ($config['status_value'] ?? ''));
        if ($value !== '' && in_array($value, $status['values'], true)) {
            return $value;
        }

        return $status['values'][0];
    }

    /**
     * @param array{id: int, type: string, values: list<string>} $field
     */
    private static function control(array $field, string $value): string
    {
        $name = 'custom_field_' . $field['id'];
        return match ($field['type']) {
            FieldTypes::TEXT_AREA => Form::textarea($name, $value),
            FieldTypes::MENU => MenuOptions::controls($field['id'], $value, '', $_POST),
            FieldTypes::CHECK_BOX => Form::checkbox($name, (string) $field['id'], ($value !== '' && $value !== '0') ? (string) $field['id'] : ''),
            FieldTypes::DATE => Form::date($name, $value),
            FieldTypes::DATETIME => Form::datetime($name, $value),
            default => Form::text($name, $value),
        };
    }

    /**
     * @param array{type_id: int, ids: list<int>, items: array<int, array{id: int, title: string, created: int, owner_id: int, owner: string}>} $loaded
     * @param array{id: int, label: string, plain: string} $field
     */
    private static function countRecords(array $loaded, array $field, string $value): void
    {
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $rows = [];
        foreach ($loaded['items'] as $item) {
            if (($values[$item['id']] ?? '') !== $value) {
                continue;
            }
            $rows[] = self::recordRow($item, [], $item['owner']);
        }
        $label = $value === '' ? 'Not set' : $value;
        self::show($field['label'], 'Records with this ' . strtolower($field['label']) . '.', Blocks::recordList([
            'column' => 'Record',
            'columns' => [],
            'searchLabel' => 'Search',
            'primary' => ['href' => self::base(), 'label' => 'Counts'],
            'empty' => 'No records with this value',
            'groups' => [['label' => $label, 'rows' => $rows]],
        ]));
    }

    /**
     * @param array{label: string, plain: string} $field
     */
    private static function countHref(array $field, string $value): string
    {
        $isStatus = $field['plain'] === 'status' || str_contains($field['plain'], 'status');
        if ($isStatus) {
            return self::workHref($field, $value);
        }

        return self::base() . '&focus=value&value=' . rawurlencode($value);
    }

    /**
     * @param array{id: int, label: string, type: string, values: list<string>, plain: string} $field
     */
    private static function workHref(array $field, string $value): string
    {
        if (!Page::current()->inApplication()) {
            return self::base();
        }
        $slug = Page::current()->applicationSlug();
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            return self::base();
        }
        foreach (ApplicationStore::navigation((int) $app['application_id']) as $link) {
            if (ScreenCatalog::resolve((string) ($link['capability'] ?? '')) !== ScreenCatalog::WORK) {
                continue;
            }
            if (!Capabilities::linkVisible($link)) {
                continue;
            }
            $query = 'view=list';
            if ($field['plain'] === 'status' || str_contains($field['plain'], 'status')) {
                $query .= $value === '' ? '&focus=empty' : '&focus=status&status=' . rawurlencode($value);
            }

            return Router::applicationUrl($slug, (int) $link['nav_id'], $query);
        }

        return self::base();
    }

    /**
     * @param array{id: int, title: string, owner: string} $item
     * @param array<string, string> $cells
     * @return array<string, mixed>
     */
    private static function recordRow(array $item, array $cells, string $search): array
    {
        $tones = [];
        if (isset($cells['stand'])) {
            $tones['stand'] = Presence::tone($cells['stand']);
        }

        return [
            'name' => self::title($item),
            'href' => self::itemHref($item['id']),
            'mark' => $item['owner'],
            'cells' => $cells,
            'tones' => $tones,
            'search' => $search,
        ];
    }

    /**
     * @param array<string, string> $cells
     * @return array<string, mixed>
     */
    private static function linkRow(string $name, string $href, array $cells, string $tone): array
    {
        return [
            'name' => $name,
            'href' => $href,
            'cells' => $cells,
            'tones' => $tone === '' ? [] : ['stand' => Presence::tone($tone)],
        ];
    }

    /**
     * @param array<string, list<array<string, mixed>>> $buckets
     * @param list<array{key: string, label: string, wrap?: bool}> $columns
     */
    private static function groupedRecords(array $buckets, array $columns, string $emptyTitle, string $emptyCopy): string
    {
        $groups = [];
        foreach ($buckets as $label => $rows) {
            if ($rows === [] && $label === '') {
                continue;
            }
            if ($rows === []) {
                continue;
            }
            $groups[] = ['label' => (string) $label, 'rows' => $rows];
        }
        if ($groups === []) {
            return self::emptyList($emptyTitle, $emptyCopy);
        }

        return Blocks::recordList([
            'column' => 'Record',
            'columns' => $columns,
            'searchLabel' => 'Search',
            'empty' => $emptyTitle,
            'groups' => $groups,
        ]);
    }

    /**
     * @param list<array{label: string, rows: list<array<string, mixed>>}> $groups
     * @param list<array{key: string, label: string}> $columns
     */
    private static function editor(string $column, array $columns, array $groups, string $action, string $submit, string $emptyTitle, string $emptyCopy): string
    {
        $rows = 0;
        foreach ($groups as $group) {
            $rows += count($group['rows']);
        }
        if ($rows === 0) {
            return self::emptyList($emptyTitle, $emptyCopy);
        }
        $heads = '';
        foreach ($columns as $col) {
            $heads .= '<th class="record-list__cell">' . Html::e($col['label']) . '</th>';
        }
        $body = '';
        foreach ($groups as $group) {
            $groupRows = $group['rows'];
            if ($groupRows === []) {
                continue;
            }
            $label = trim((string) $group['label']);
            $span = 2 + count($columns);
            $body .= '<tbody data-group>';
            if ($label !== '') {
                $body .= '<tr class="record-list__grouphead"><th colspan="' . $span . '" scope="rowgroup">'
                    . Html::e($label) . ' <span class="record-list__badge">' . count($groupRows) . '</span></th></tr>';
            }
            foreach ($groupRows as $row) {
                $body .= self::editorRow($row, $columns, $label);
            }
            $body .= '</tbody>';
        }

        return '<form method="post" action="' . Html::e($action) . '">'
            . Csrf::field()
            . '<div class="record-list" data-record-list>'
            . '<div class="record-list__bar">'
            . '<input class="input record-list__search" type="search" placeholder="Search" aria-label="Search" autocomplete="off" form="record-sheet-search">'
            . '<span class="record-list__count" aria-live="polite">' . $rows . '</span>'
            . '<button class="btn btn--primary btn--sm" type="submit">' . Html::e($submit) . '</button>'
            . '</div>'
            . '<div class="record-list__tablewrap"><table class="table table-hover"><thead><tr><th>'
            . Html::e($column) . '</th>' . $heads . '<th class="record-list__cell">Update</th></tr></thead>'
            . $body
            . '</table></div>'
            . '<p class="record-list__nomatch" hidden>No matches.</p>'
            . '</div></form>';
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array{key: string, label: string}> $columns
     */
    private static function editorRow(array $row, array $columns, string $groupLabel): string
    {
        $name = (string) ($row['name'] ?? '');
        $mark = trim((string) ($row['mark'] ?? ''));
        $search = strtolower($name . ' ' . $groupLabel . ' ' . $mark . ' ' . (string) ($row['search'] ?? ''));
        $nameHtml = '<span class="record-list__name">'
            . ($mark !== '' ? Presence::mark($mark) : '')
            . '<span class="record-list__title"><a class="record-link" href="' . Html::e((string) ($row['href'] ?? '')) . '">'
            . Html::e($name) . '</a></span></span>';
        $cells = is_array($row['cells'] ?? null) ? $row['cells'] : [];
        $tones = is_array($row['tones'] ?? null) ? $row['tones'] : [];
        $cellsHtml = '';
        foreach ($columns as $column) {
            $key = $column['key'];
            $value = trim((string) ($cells[$key] ?? ''));
            $search .= ' ' . strtolower($value);
            $inner = $value === '' ? '—' : (isset($tones[$key]) || $key === 'stand' ? Presence::pill($value, (string) ($tones[$key] ?? '')) : Html::e($value));
            $cellsHtml .= '<td class="record-list__cell">' . $inner . '</td>';
        }

        $control = self::labelControl((string) ($row['control'] ?? ''), $name);

        return '<tr data-search="' . Html::e($search) . '"><td>' . $nameHtml . '</td>' . $cellsHtml
            . '<td class="record-list__cell record-sheet__control">' . $control . '</td></tr>';
    }

    /**
     * Gives a row control an accessible name that includes the record it belongs to.
     */
    private static function labelControl(string $control, string $recordName): string
    {
        if ($control === '' || str_contains($control, 'aria-label=') || !preg_match('/<(input|select|textarea)\b[^>]*\bname="(owner|step)_\d+"/', $control, $found)) {
            return $control;
        }
        $label = ($found[2] === 'owner' ? 'Owner of ' : 'Next step for ') . $recordName;

        return (string) preg_replace_callback(
            '/<(input|select|textarea)\b/',
            static fn (array $tag): string => '<' . $tag[1] . ' aria-label="' . Html::e($label) . '"',
            $control,
            1
        );
    }

    private static function emptyList(string $title, string $copy): string
    {
        return Blocks::emptyState([
            'illustration' => 'empty-work',
            'title' => $title,
            'copy' => $copy,
        ]);
    }

    /**
     * @param array{id: int, title: string} $item
     */
    private static function plural(int|float $count, string $noun): string
    {
        $count = (int) $count;

        return $count . ' ' . $noun . ($count === 1 ? '' : 's');
    }

    private static function title(array $item): string
    {
        return $item['title'] !== '' ? $item['title'] : ('Record ' . $item['id']);
    }

    private static function itemHref(int $itemId): string
    {
        return self::base() . '&item=' . $itemId;
    }

    private static function base(): string
    {
        $page = Page::current();
        if ($page->inApplication()) {
            return Router::applicationUrl($page->applicationSlug(), $page->navId());
        }

        return Router::page();
    }

    private static function show(string $title, string $help, string $html, string $lead = ''): void
    {
        Page::current()->suggestTitle($title);
        Page::current()->setBody(Blocks::vertical([[
            'title' => $title,
            'html' => '<p class="form-help">' . Html::e($help) . '</p>' . $lead . $html,
            'class' => 'card--work',
        ]]));
    }

    private static function missing(string $copy): void
    {
        Page::current()->setBody(Blocks::vertical([[
            'title' => 'This screen needs a field',
            'html' => Blocks::emptyState([
                'illustration' => 'empty-work',
                'title' => 'This screen needs a field',
                'copy' => $copy,
            ]),
        ]]));
    }

    private static function back(): void
    {
        header('Location: ' . self::base());
        exit;
    }

    /**
     * @param array<int, string> $people
     */
    private static function ownerSelect(int $itemId, int $ownerId, array $people, string $ownerName = ''): string
    {
        $values = ['0'];
        $labels = ['No owner'];
        if ($ownerId > 0 && !isset($people[$ownerId])) {
            $people[$ownerId] = $ownerName !== '' ? $ownerName : 'Owner ' . $ownerId;
        }
        foreach ($people as $id => $name) {
            $values[] = (string) $id;
            $labels[] = $name;
        }

        return Form::select('owner_' . $itemId, $values, $labels, (string) $ownerId);
    }

    /**
     * A screen-specific field remains the first choice. The application's
     * workflow choice comes next, followed by the established name heuristic.
     *
     * @param array<string, mixed> $loaded
     * @param array<string, mixed> $config
     */
    private static function chosen(array $loaded, array $config, string $purpose): ?array
    {
        $picked = RecordQuery::field($loaded['fields'], (int) ($config['field_id'] ?? 0));
        if ($picked !== null) {
            return $picked;
        }
        $workflowPurpose = match ($purpose) {
            'menu' => WorkflowFields::STATUS,
            'step' => WorkflowFields::STEP,
            'date' => WorkflowFields::DUE,
            default => '',
        };
        if ($workflowPurpose !== '') {
            $slug = Page::current()->inApplication() ? Page::current()->applicationSlug() : '';
            $picked = WorkflowFields::resolve($slug, (int) ($loaded['type_id'] ?? 0), $workflowPurpose);
            if ($picked !== null) {
                return $picked;
            }
        }

        return RecordQuery::chosen($loaded['fields'], $config, $purpose);
    }

    /**
     * @return array{0: string, 1: string} text and pill tone for a date a number of days from today
     */
    private static function dueLabel(int $offset): array
    {
        if ($offset < 0) {
            $days = abs($offset);

            return [$days === 1 ? '1 day overdue' : $days . ' days overdue', 'danger'];
        }
        if ($offset === 0) {
            return ['Due today', 'warning'];
        }
        if ($offset === 1) {
            return ['Due tomorrow', 'warning'];
        }

        return ['In ' . $offset . ' days', $offset <= 7 ? 'info' : 'neutral'];
    }

    private static function ageLabel(?\DateTimeImmutable $date, \DateTimeImmutable $today): string
    {
        if ($date === null) {
            return '';
        }
        $day = $date->setTime(0, 0);
        $days = (int) $day->diff($today)->days;
        if ($day > $today) {
            return $days === 1 ? 'In 1 day' : 'In ' . $days . ' days';
        }
        if ($days === 0) {
            return 'Today';
        }

        return $days === 1 ? '1 day' : $days . ' days';
    }
}
