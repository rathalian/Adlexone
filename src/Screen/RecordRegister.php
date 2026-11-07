<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Http\Request;

use Adlexone\Application\RecordQuery;
use Adlexone\Application\WorkflowFields;
use Adlexone\Data\GroupMembership;
use Adlexone\Data\ItemFields;
use Adlexone\Html\Blocks;
use Adlexone\Html\Charts;
use Adlexone\Html\Timeline;
use Adlexone\Html\Form;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Http\Page;
use Adlexone\Http\View;
use Adlexone\Application\Actions;
use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;


final class RecordRegister
{


    /**
     * @param array<string, mixed> $config
     */
    public static function activity(array $config): void
    {
        $loaded = RecordScreens::need($config);
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
            $sql = 'SELECT item_id, log_text, create_date, security_id, audience'
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
                static fn (array $line): bool => \Adlexone\Data\GroupMembership::canSeeLogLine((int) ($line['group_id'] ?? 0), (string) ($line['audience'] ?? ''))
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
                    'href' => RecordScreens::itemHref($itemId),
                    'meta' => RecordScreens::title($item),
                    'mark' => $who,
                    'cells' => ['when' => Presence::ago($when)],
                    'titles' => ['when' => $when > 0 ? date((string) SET_DATE_FORMAT, $when) : ''],
                    'search' => $who . ' ' . RecordScreens::title($item),
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
                'href' => RecordScreens::itemHref($itemId),
                'icon' => 'ic-activity',
                'detail' => RecordScreens::title($item),
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
            RecordScreens::show('Activity', $help, $timeline, $lead);
            return;
        }
        RecordScreens::show('Activity', $help, Blocks::recordList([
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
    public static function files(array $config): void
    {
        $loaded = RecordScreens::need($config);
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
                    'href' => RecordScreens::base() . '&option=download_file&id=' . (int) $file['id'],
                    'meta' => RecordScreens::title($item),
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
        RecordScreens::show('Files', 'Files on the records you can open. The download is the one on the record.', Blocks::recordList([
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
    public static function register(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $chosen = self::listedFields($loaded['fields'], $config);
        if ($chosen === []) {
            RecordScreens::missing('Add fields to this record type.');
            return;
        }
        $map = RecordQuery::valueMap($loaded['ids'], array_map(static fn (array $field): int => $field['id'], $chosen));
        $statusValues = RecordScreens::statusValues($loaded);
        $rows = [];
        foreach ($loaded['items'] as $item) {
            $cells = [];
            $search = '';
            foreach ($chosen as $field) {
                $shown = RecordQuery::display($field, $map[$item['id']][$field['id']] ?? '');
                $cells['f' . $field['id']] = $shown;
                $search .= ' ' . $shown;
            }
            $row = RecordScreens::recordRow($item, $cells, $item['owner'] . $search);
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
        RecordScreens::show('Register', 'One row per record.', Blocks::recordList([
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
    public static function intake(array $config): void
    {
        $typeId = RecordQuery::typeId($config);
        if ($typeId <= 0) {
            RecordScreens::missing('Choose a record type for this link under Configure, Applications.');
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
        if ($posted && (string) (Request::queryValue('option') ?? '') === 'save_intake') {
            $errors = self::intakeErrors($typeId);
        }
        $formFields = [];
        $formFields['Name'] = Form::text('item_title', $posted ? (string) (Request::postedValue('item_title') ?? '') : '');
        foreach ($shown as $field) {
            if ($status !== null && $field['id'] === $status['id']) {
                continue;
            }
            $value = $posted ? (string) (Request::postedValue('custom_field_' . $field['id']) ?? '') : '';
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
            RecordScreens::base() . '&option=save_intake',
            $formFields,
            [Form::button('submit', 'submit_button', 'Save')]
            ,
            $options
        ));
    }


    /**
     * @param array<string, mixed> $config
     */
    public static function saveIntake(array $config): void
    {
        $typeId = RecordQuery::typeId($config);
        if ($typeId <= 0 || !ItemScreen::applicationAllowsItemType($typeId)) {
            RecordScreens::missing('Choose a record type for this link under Configure, Applications.');
            return;
        }
        if (self::intakeErrors($typeId) !== []) {
            self::intake($config);
            return;
        }
        $fields = RecordQuery::fields($typeId);
        $status = WorkScreen::workStatusField($typeId);
        $opening = self::openingStatus($config, $status);
        $posted = Request::postedAll();
        MenuOptions::collapseRequest($posted);
        Request::fillPosted($posted);
        $payload = [];
        foreach ($fields as $field) {
            $key = 'custom_field_' . $field['id'];
            if (array_key_exists($key, Request::postedAll()) && !is_array(Request::postedValue($key))) {
                $payload[$key] = (string) Request::postedValue($key);
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
            'item_title' => trim((string) (Request::postedValue('item_title') ?? '')),
        ]);
        ItemFields::saveFromArray($itemId, $payload);
        GroupMembership::setItemGroups($itemId, GroupMembership::idsFromPost(Request::postedAll()));
        Request::fillPosted(array_merge(Request::postedAll(), $payload));
        Actions::executeAction($itemId, 'create_item', false);
        $createdTitle = trim((string) (Request::postedValue('item_title') ?? ''));
        View::flash(($createdTitle !== '' ? $createdTitle : 'The record') . ' created.');
        header('Location: ' . RecordScreens::base() . '&item=' . $itemId);
        exit;
    }


    /**
     * @return array<string, string>
     */
    private static function intakeErrors(int $typeId): array
    {
        $errors = [];
        if (trim((string) (Request::postedValue('item_title') ?? '')) === '') {
            $errors['item_title'] = 'Enter a name.';
        }

        $slug = Page::current()->inApplication() ? Page::current()->applicationSlug() : '';

        return $errors + FieldValidation::postedErrors(Request::postedAll(), $typeId) + WorkflowFields::dueError($typeId, $slug, Request::postedAll());
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
            FieldTypes::MENU => MenuOptions::controls($field['id'], $value, '', Request::postedAll()),
            FieldTypes::CHECK_BOX => Form::checkbox($name, (string) $field['id'], ($value !== '' && $value !== '0') ? (string) $field['id'] : ''),
            FieldTypes::DATE => Form::date($name, $value),
            FieldTypes::DATETIME => Form::datetime($name, $value),
            default => Form::text($name, $value),
        };
    }
}
