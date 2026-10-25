<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Auth\Csrf;
use Adlexone\Data\SavedSearch;
use Adlexone\Html\Blocks;
use Adlexone\Html\Html;
use Adlexone\Html\Presence;
use Adlexone\Html\Stamp;
use Adlexone\Http\Page;
use Adlexone\Http\Router;
use Adlexone\Http\View;
use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\FieldValidation;

/**
 * The Search tab of an application: one box for words, a few plain filters, and any number of field
 * filters. The whole search lives in the address, so a result page can be shared or bookmarked.
 */
final class ApplicationSearch
{
    private const PAGE_SIZE = 25;

    private const WINDOWS = [
        1 => 'the last 24 hours',
        7 => 'the last 7 days',
        30 => 'the last 30 days',
        90 => 'the last 90 days',
        365 => 'the last 12 months',
    ];

    private const SORTS = ['updated' => 'Updated', 'created' => 'Opened', 'name' => 'Name'];

    private const OPERATORS = [
        'text' => ['CONTAINS' => 'contains', '=' => 'is', 'NOT' => 'is not'],
        'menu' => ['=' => 'is', 'NOT' => 'is not'],
        'number' => ['=' => 'is', '>=' => 'at least', '<=' => 'at most'],
        'date' => ['DATE>=' => 'on or after', 'DATE<=' => 'on or before', 'DATE=' => 'on'],
        'check' => ['TICKED' => 'is ticked', 'UNTICKED' => 'is not ticked'],
    ];

    public static function open(): void
    {
        $slug = Page::current()->applicationSlug();
        $types = ApplicationSettings::allowedItemTypeIds($slug);
        $fields = self::fields($types);

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST' && isset($_POST['search_name'])) {
            self::save($slug, $types, $fields);
            return;
        }

        Page::current()->addScript('search');
        $problems = [];
        $state = self::state($types, $fields, $problems);
        $run = isset($_GET['go']);

        $body = self::form($state, $types, $fields);
        if ($problems !== []) {
            $body .= '<p class="finder__note" role="alert">' . Html::e(implode(' ', $problems)) . '</p>';
        }
        $body .= $run ? self::results($state, $types, $fields) : self::landing($slug);

        Page::current()->setBody(Blocks::vertical([
            ['title' => 'Search', 'html' => $body, 'class' => 'card--finder'],
        ]));
    }

    /**
     * Address of a saved search that was made on this screen, or an empty string for any other kind.
     *
     * @param array<string, mixed> $criteria
     */
    public static function savedHref(array $criteria): string
    {
        $state = $criteria['finder'] ?? null;
        if (!is_array($state) || !Page::current()->inApplication()) {
            return '';
        }

        return self::href($state, true);
    }

    /**
     * @param list<int> $types
     * @return list<array{id: int, label: string, kind: string, values: list<string>, group: string}>
     */
    private static function fields(array $types): array
    {
        $out = [];
        foreach ($types as $typeId) {
            $type = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId]);
            $group = trim((string) ($type['item_type_name'] ?? ''));
            foreach (RecordQuery::fields($typeId) as $field) {
                $kind = self::kind($field['type']);
                if ($kind === '') {
                    continue;
                }
                $out[] = [
                    'id' => $field['id'],
                    'label' => $field['label'],
                    'kind' => $kind,
                    'values' => $field['values'],
                    'group' => $group,
                ];
            }
        }

        return $out;
    }

    private static function kind(string $type): string
    {
        return match ($type) {
            FieldTypes::MENU => 'menu',
            FieldTypes::NUMBER => 'number',
            FieldTypes::DATE, FieldTypes::DATETIME => 'date',
            FieldTypes::CHECK_BOX => 'check',
            'password', 'hidden', 'workerField', 'workerFieldMenu', 'fieldSeparator' => '',
            default => 'text',
        };
    }

    /**
     * What was asked for, cleaned so that every part can be trusted and repeated in an address.
     *
     * @param list<int> $types
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     * @param list<string> $problems
     * @return array<string, mixed>
     */
    private static function state(array $types, array $fields, array &$problems): array
    {
        $text = static fn (string $key): string => is_scalar($_GET[$key] ?? null) ? trim((string) $_GET[$key]) : '';

        $state = ['q' => mb_substr($text('q'), 0, 200)];

        $kind = (int) $text('sk');
        if ($kind > 0 && count($types) > 1 && in_array($kind, $types, true)) {
            $state['sk'] = $kind;
        }

        $owner = $text('so');
        if ($owner === 'me' || $owner === 'none' || (ctype_digit($owner) && (int) $owner > 0)) {
            $state['so'] = $owner;
        }

        $group = (int) $text('sg');
        if ($group > 0) {
            $state['sg'] = $group;
        }

        foreach (['sc', 'su'] as $key) {
            $days = (int) $text($key);
            if (isset(self::WINDOWS[$days])) {
                $state[$key] = $days;
            }
        }

        $filters = [];
        $posted = $_GET['f'] ?? [];
        foreach (is_array($posted) ? $posted : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $field = self::field($fields, (int) (is_scalar($row['field'] ?? null) ? $row['field'] : 0));
            if ($field === null) {
                continue;
            }
            $ops = self::OPERATORS[$field['kind']];
            $op = is_scalar($row['op'] ?? null) ? (string) $row['op'] : '';
            if (!isset($ops[$op])) {
                $op = (string) array_key_first($ops);
            }
            $value = is_scalar($row['value'] ?? null) ? trim((string) $row['value']) : '';
            if ($field['kind'] === 'check') {
                $value = '';
            } elseif ($value === '') {
                continue;
            } elseif ($field['kind'] === 'date') {
                $day = FieldValidation::parseDate($value);
                if ($day === null) {
                    $problems[] = $field['label'] . ' was left out because "' . $value . '" is not a date.';
                    continue;
                }
                $value = $day->format('Y-m-d');
            } elseif ($field['kind'] === 'number' && !is_numeric($value)) {
                $problems[] = $field['label'] . ' was left out because "' . $value . '" is not a number.';
                continue;
            }
            $filters[] = ['field' => $field['id'], 'op' => $op, 'value' => $value];
            if (count($filters) >= 12) {
                break;
            }
        }
        if ($filters !== []) {
            $state['f'] = $filters;
        }

        $sort = $text('ss');
        if (isset(self::SORTS[$sort]) && $sort !== 'updated') {
            $state['ss'] = $sort;
        }
        $sortKey = (string) ($state['ss'] ?? 'updated');
        $dir = $text('sd');
        if (($dir === 'asc' || $dir === 'desc') && $dir !== self::defaultDirection($sortKey)) {
            $state['sd'] = $dir;
        }

        return $state;
    }

    private static function defaultDirection(string $sort): string
    {
        return $sort === 'name' ? 'asc' : 'desc';
    }

    /**
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     * @return array{id: int, label: string, kind: string, values: list<string>, group: string}|null
     */
    private static function field(array $fields, int $id): ?array
    {
        foreach ($fields as $field) {
            if ($field['id'] === $id) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private static function criteria(array $state): array
    {
        $filters = [];
        $add = static function (string $field, string $op, string $value) use (&$filters): void {
            $filters[] = ['field' => $field, 'op' => $op, 'value' => $value, 'andor' => 'AND'];
        };

        if (($state['q'] ?? '') !== '') {
            $add('q', 'CONTAINS', (string) $state['q']);
        }
        if (isset($state['sk'])) {
            $add('item_type_id', '=', (string) $state['sk']);
        }
        if (isset($state['so'])) {
            $owner = (string) $state['so'];
            $add('user_security', '=', $owner === 'me' ? 'session_user' : ($owner === 'none' ? '0' : $owner));
        }
        if (isset($state['sg'])) {
            $add('group_id', '=', (string) $state['sg']);
        }
        if (isset($state['sc'])) {
            $add('create_date_days', '>=', (string) $state['sc']);
        }
        if (isset($state['su'])) {
            $add('core_log_updated_days', '>=', (string) $state['su']);
        }
        foreach ($state['f'] ?? [] as $row) {
            $add('custom_field_' . $row['field'], (string) $row['op'], (string) $row['value']);
        }

        return ['version' => 1, 'filters' => $filters, 'display' => [], 'finder' => $state];
    }

    /**
     * @param array<string, mixed> $state
     * @param list<int> $types
     * @return list<int>
     */
    private static function matches(array $state, array $types): array
    {
        $inner = SavedSearch::compile(self::criteria($state), 'item_id');
        $where = 'item_id IN (' . $inner . ')';
        if ($types !== []) {
            $where .= ' AND item_type_id IN (' . implode(',', array_map('intval', $types)) . ')';
        }
        $sort = (string) ($state['ss'] ?? 'updated');
        $column = match ($sort) {
            'created' => 'create_date',
            'name' => 'item_title COLLATE NOCASE',
            default => 'COALESCE(core_log_updated, create_date)',
        };
        $dir = strtoupper((string) ($state['sd'] ?? self::defaultDirection($sort)));
        $allowed = array_flip(WorkScreen::applicationAccessibleItemIds());

        $ids = [];
        foreach (Database::rows("SELECT item_id FROM items WHERE {$where} ORDER BY {$column} {$dir}, item_id DESC") as $row) {
            $id = (int) $row['item_id'];
            if (isset($allowed[$id])) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private static function baseUrl(): string
    {
        $slug = Page::current()->applicationSlug();
        $app = ApplicationStore::findBySlug($slug);
        if ($app !== null) {
            foreach (ApplicationStore::navigation((int) $app['application_id']) as $link) {
                if (ScreenCatalog::resolve((string) ($link['capability'] ?? '')) === ScreenCatalog::SEARCH_ADVANCED && Capabilities::linkVisible($link)) {
                    return Router::applicationUrl($slug, (int) $link['nav_id']);
                }
            }
        }

        return Router::continueUrl('search');
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function href(array $state, bool $run = true, int $page = 1): string
    {
        $query = ['option' => 'show_item_search'];
        foreach (['q', 'sk', 'so', 'sg', 'sc', 'su', 'ss', 'sd'] as $key) {
            if (isset($state[$key]) && $state[$key] !== '') {
                $query[$key] = $state[$key];
            }
        }
        foreach (array_values($state['f'] ?? []) as $i => $row) {
            $query['f'][$i] = ['field' => $row['field'], 'op' => $row['op'], 'value' => $row['value']];
        }
        if ($run) {
            $query['go'] = '1';
        }
        if ($page > 1) {
            $query['sp'] = $page;
        }

        return self::baseUrl() . '&' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, mixed> $state
     * @param list<int> $types
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     */
    private static function form(array $state, array $types, array $fields): string
    {
        $hidden = '';
        $base = self::baseUrl();
        parse_str((string) parse_url($base, PHP_URL_QUERY), $fixed);
        $fixed['option'] = 'show_item_search';
        foreach (['ss', 'sd'] as $key) {
            if (isset($state[$key])) {
                $fixed[$key] = $state[$key];
            }
        }
        foreach ($fixed as $name => $value) {
            if (is_scalar($value)) {
                $hidden .= '<input type="hidden" name="' . Html::e((string) $name) . '" value="' . Html::e((string) $value) . '">';
            }
        }

        $run = isset($_GET['go']);
        $q = '<div class="finder__bar">'
            . '<label class="finder__sr" for="finder-q">Search</label>'
            . '<input id="finder-q" class="input finder__q" type="search" name="q" value="' . Html::e((string) $state['q']) . '"'
            . ' placeholder="Search names, numbers, field values and notes" autocomplete="off"' . ($run ? '' : ' autofocus') . '>'
            . '<button class="btn btn--primary finder__go" type="submit" name="go" value="1">Search</button>'
            . '</div>';

        $filters = '';
        if (count($types) > 1) {
            $options = ['' => 'Any kind'];
            foreach ($types as $typeId) {
                $options[(string) $typeId] = self::typeName($typeId);
            }
            $filters .= self::pick('Kind', 'sk', $options, (string) ($state['sk'] ?? ''));
        }
        $owners = ['' => 'Anyone', 'me' => 'Me', 'none' => 'Nobody yet'];
        foreach (Database::select('users', ['user_id', 'user_name', 'first_name', 'last_name'], 'ORDER BY user_name ASC') as $user) {
            $owners[(string) (int) $user['user_id']] = RecordQuery::displayName($user);
        }
        $filters .= self::pick('Assigned to', 'so', $owners, (string) ($state['so'] ?? ''));
        $groups = ['' => 'Any group'];
        foreach (Database::select('groups', ['group_id', 'group_name'], 'ORDER BY group_name ASC') as $group) {
            $groups[(string) (int) $group['group_id']] = (string) $group['group_name'];
        }
        if (count($groups) > 1) {
            $filters .= self::pick('Shared with', 'sg', $groups, (string) ($state['sg'] ?? ''));
        }
        $windows = ['' => 'Any time'];
        foreach (self::WINDOWS as $days => $label) {
            $windows[(string) $days] = ucfirst((string) preg_replace('/^the /', '', $label));
        }
        $filters .= self::pick('Opened', 'sc', $windows, (string) ($state['sc'] ?? ''));
        $filters .= self::pick('Updated', 'su', $windows, (string) ($state['su'] ?? ''));

        $rows = '';
        $template = '';
        $more = '';
        if ($fields !== []) {
            foreach (array_values($state['f'] ?? []) as $i => $row) {
                $rows .= self::row((string) $i, $fields, $row);
            }
            $template = '<template data-finder-template>' . self::row('__N__', $fields, null) . '</template>';
            $more = '<button type="button" class="btn btn--quiet btn--sm finder__add" data-finder-add>+ Add a field filter</button>'
                . '<noscript><div class="finder__rows">' . self::row((string) count($state['f'] ?? []), $fields, null) . '</div></noscript>';
        }

        $ops = [];
        foreach (self::OPERATORS as $kind => $list) {
            foreach ($list as $value => $label) {
                $ops[$kind][] = [$value, $label];
            }
        }
        $data = json_encode(array_map(static fn (array $f): array => [
            'id' => $f['id'], 'kind' => $f['kind'], 'values' => $f['values'],
        ], $fields), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $opsData = json_encode($ops, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return '<form class="finder" method="get" data-finder data-fields="' . Html::e((string) $data) . '" data-ops="' . Html::e((string) $opsData) . '">'
            . $hidden . $q
            . '<div class="finder__filters">' . $filters . '</div>'
            . '<div class="finder__rows" data-finder-rows>' . $rows . '</div>'
            . '<div class="finder__more">' . $more . '</div>'
            . $template
            . '</form>';
    }

    /**
     * @param array<string, string> $options
     */
    private static function pick(string $label, string $name, array $options, string $selected): string
    {
        $html = '<label class="finder__filter"><span class="finder__label">' . Html::e($label) . '</span>'
            . '<select class="select" name="' . Html::e($name) . '">';
        foreach ($options as $value => $text) {
            $html .= '<option value="' . Html::e((string) $value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . Html::e($text) . '</option>';
        }

        return $html . '</select></label>';
    }

    /**
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     * @param array{field: int, op: string, value: string}|null $row
     */
    private static function row(string $index, array $fields, ?array $row): string
    {
        $field = $row !== null ? self::field($fields, (int) $row['field']) : null;
        $kind = $field['kind'] ?? 'text';
        $name = 'f[' . $index . ']';

        $grouped = count(array_unique(array_column($fields, 'group'))) > 1;
        $choose = '<select class="select finder__field" name="' . $name . '[field]" aria-label="Field" data-finder-field>'
            . '<option value="">Choose a field</option>';
        $current = null;
        foreach ($fields as $option) {
            if ($grouped && $option['group'] !== $current) {
                $choose .= $current !== null ? '</optgroup>' : '';
                $choose .= '<optgroup label="' . Html::e($option['group']) . '">';
                $current = $option['group'];
            }
            $choose .= '<option value="' . $option['id'] . '"' . ($field !== null && $field['id'] === $option['id'] ? ' selected' : '') . '>'
                . Html::e($option['label']) . '</option>';
        }
        $choose .= ($grouped && $current !== null ? '</optgroup>' : '') . '</select>';

        $op = '<select class="select finder__op" name="' . $name . '[op]" aria-label="Condition" data-finder-op>';
        foreach (self::OPERATORS[$kind] as $value => $label) {
            $op .= '<option value="' . Html::e($value) . '"' . ($row !== null && $row['op'] === $value ? ' selected' : '') . '>' . Html::e($label) . '</option>';
        }
        $op .= '</select>';

        $value = $row['value'] ?? '';
        $control = '<span class="finder__value" data-finder-value>';
        if ($kind === 'menu') {
            $control .= '<select class="select" name="' . $name . '[value]" aria-label="Value">';
            foreach ($field['values'] ?? [] as $option) {
                $control .= '<option value="' . Html::e($option) . '"' . ($option === $value ? ' selected' : '') . '>' . Html::e($option) . '</option>';
            }
            $control .= '</select>';
        } elseif ($kind === 'check') {
            $control .= '<input type="hidden" name="' . $name . '[value]" value="">';
        } else {
            $type = $kind === 'number' ? 'number' : ($kind === 'date' ? 'date' : 'text');
            $step = $kind === 'number' ? ' step="any"' : '';
            $control .= '<input class="input" type="' . $type . '"' . $step . ' name="' . $name . '[value]" value="' . Html::e($value) . '" aria-label="Value" autocomplete="off">';
        }
        $control .= '</span>';

        return '<div class="finder__row" data-finder-row>' . $choose . $op . $control
            . '<button type="button" class="finder__remove" data-finder-remove aria-label="Remove this filter">&times;</button></div>';
    }

    private static function typeName(int $typeId): string
    {
        $row = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId]);

        return trim((string) ($row['item_type_name'] ?? '')) ?: 'Record';
    }

    private static function landing(string $slug): string
    {
        $saved = self::savedFor($slug);
        $html = '<div class="finder__landing">'
            . '<div class="finder__guide"><h3>Find a record</h3>'
            . '<p>Search a name, reference, field value, or note. Add a field filter when the wording alone is not specific enough.</p>'
            . '<p class="finder__examples"><strong>Examples</strong> Barton · waiting on us · follow up the draft</p></div>';
        if ($saved !== []) {
            $html .= '<h3 class="finder__heading">Saved searches</h3><ul class="finder__saved">';
            foreach ($saved as $row) {
                $html .= '<li><a class="finder__savedlink" href="' . Html::e(self::savedLink($row)) . '">' . Html::e((string) $row['search_name'])
                    . '</a>' . ((string) $row['user'] === 'all' ? '<span class="finder__shared">Shared</span>' : '') . '</li>';
            }
            $html .= '</ul>';
        } else {
            $html .= '<p class="finder__hint">A search you save is listed here and on the Saved tab.</p>';
        }

        return $html . '</div>';
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function savedLink(array $row): string
    {
        return Router::continueUrl('search') . '&search=' . rawurlencode((string) $row['search_id'])
            . ((string) $row['user'] === 'all' ? '&global=1' : '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function savedFor(string $slug): array
    {
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            return [];
        }

        return Database::rows(
            "SELECT search_id, search_name, user FROM saved_searches WHERE application = ? AND (user = ? OR user = 'all') ORDER BY search_name ASC LIMIT 12",
            [ApplicationStore::scopeKey($app), (string) ($_SESSION['access_user_id'] ?? '')]
        );
    }

    /**
     * @param array<string, mixed> $state
     * @param list<int> $types
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     */
    private static function results(array $state, array $types, array $fields): string
    {
        $ids = self::matches($state, $types);
        $total = count($ids);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($pages, max(1, (int) (is_scalar($_GET['sp'] ?? null) ? $_GET['sp'] : 1)));
        $shown = array_slice($ids, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        $count = $total === 1 ? '<strong>1 record</strong> matches.' : '<strong>' . number_format($total) . ' records</strong> match.';
        $head = '<div class="finder__summary"><p class="finder__count" role="status">' . $count . '</p>'
            . '<div class="finder__chips">' . self::chips($state, $fields) . '</div></div>';

        if ($total === 0) {
            $body = Blocks::emptyState([
                'illustration' => 'empty-search',
                'title' => 'No records match',
                'copy' => 'Remove a filter or use fewer words. Only records you can open are searched.',
                'action' => '<a class="btn btn--primary" href="' . Html::e(self::baseUrl() . '&option=show_item_search') . '">Clear the search</a>',
            ]);

            return '<section class="finder__results">' . $head . $body . '</section>';
        }

        $tools = '<div class="finder__tools">' . self::sortLinks($state) . self::saveForm($state) . '</div>';

        return '<section class="finder__results">' . $head . $tools
            . self::table($shown, $state, $types, $fields)
            . self::pager($state, $page, $pages, $total)
            . '</section>';
    }

    /**
     * @param array<string, mixed> $state
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     */
    private static function chips(array $state, array $fields): string
    {
        $chips = [];
        $without = static function (array $state, string $key, ?int $index = null): array {
            if ($index !== null) {
                unset($state['f'][$index]);
                $state['f'] = array_values($state['f']);
                if ($state['f'] === []) {
                    unset($state['f']);
                }
            } else {
                unset($state[$key]);
            }

            return $state;
        };

        if (($state['q'] ?? '') !== '') {
            $chips[] = ['"' . $state['q'] . '"', $without($state, 'q')];
        }
        if (isset($state['sk'])) {
            $chips[] = [self::typeName((int) $state['sk']), $without($state, 'sk')];
        }
        if (isset($state['so'])) {
            $owner = (string) $state['so'];
            if ($owner === 'me') {
                $label = 'Assigned to me';
            } elseif ($owner === 'none') {
                $label = 'Not assigned';
            } else {
                $user = Database::first('users', ['user_name', 'first_name', 'last_name'], 'user_id = ?', [(int) $owner]);
                $label = 'Assigned to ' . ($user !== null ? RecordQuery::displayName($user) : 'someone');
            }
            $chips[] = [$label, $without($state, 'so')];
        }
        if (isset($state['sg'])) {
            $group = Database::first('groups', ['group_name'], 'group_id = ?', [(int) $state['sg']]);
            $chips[] = ['Shared with ' . (string) ($group['group_name'] ?? 'a group'), $without($state, 'sg')];
        }
        if (isset($state['sc'])) {
            $chips[] = ['Opened in ' . self::WINDOWS[$state['sc']], $without($state, 'sc')];
        }
        if (isset($state['su'])) {
            $chips[] = ['Updated in ' . self::WINDOWS[$state['su']], $without($state, 'su')];
        }
        foreach (array_values($state['f'] ?? []) as $i => $row) {
            $field = self::field($fields, (int) $row['field']);
            if ($field === null) {
                continue;
            }
            $value = $row['value'];
            if ($field['kind'] === 'date') {
                $stamp = strtotime($value);
                $value = $stamp !== false ? Stamp::day($stamp) : $value;
            }
            $label = $field['label'] . ' ' . self::OPERATORS[$field['kind']][$row['op']] . ($value !== '' ? ' ' . $value : '');
            $chips[] = [$label, $without($state, 'f', $i)];
        }

        $html = '';
        foreach ($chips as [$label, $rest]) {
            $html .= '<a class="finder__chip" href="' . Html::e(self::href($rest)) . '" title="Remove this filter">'
                . '<span>' . Html::e($label) . '</span><span class="finder__chipx" aria-hidden="true">&times;</span>'
                . '<span class="finder__sr">Remove</span></a>';
        }
        if (count($chips) > 1) {
            $html .= '<a class="finder__clear" href="' . Html::e(self::baseUrl() . '&option=show_item_search') . '">Clear all</a>';
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function sortLinks(array $state): string
    {
        $active = (string) ($state['ss'] ?? 'updated');
        $dir = (string) ($state['sd'] ?? self::defaultDirection($active));
        $html = '<nav class="finder__sort" aria-label="Sort the results"><span class="finder__label">Sort by</span>';
        foreach (self::SORTS as $key => $label) {
            $next = $state;
            unset($next['ss'], $next['sd']);
            $arrow = '';
            if ($key !== 'updated') {
                $next['ss'] = $key;
            }
            if ($key === $active) {
                $flip = $dir === 'asc' ? 'desc' : 'asc';
                if ($flip !== self::defaultDirection($key)) {
                    $next['sd'] = $flip;
                }
                $arrow = $dir === 'asc' ? ' &uarr;' : ' &darr;';
            }
            $html .= '<a class="finder__sortlink' . ($key === $active ? ' is-active' : '') . '"'
                . ($key === $active ? ' aria-current="true"' : '') . ' href="' . Html::e(self::href($next)) . '">' . Html::e($label) . $arrow . '</a>';
        }

        return $html . '</nav>';
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function saveForm(array $state): string
    {
        $scope = '';
        if ((int) ($_SESSION['access_role_id'] ?? 5) <= 1) {
            $scope = '<label class="finder__filter"><span class="finder__label">Visible to</span>'
                . '<select class="select" name="security"><option value="mine">Only me</option><option value="all">Everyone with access</option></select></label>';
        }

        return '<details class="finder__save"><summary class="btn btn--sm">Save this search</summary>'
            . '<form class="finder__saveform" method="post" action="' . Html::e(self::href($state)) . '">'
            . Csrf::field()
            . '<label class="finder__filter finder__filter--wide"><span class="finder__label">Name</span>'
            . '<input class="input" type="text" name="search_name" maxlength="80" required placeholder="For example, Open claims this month"></label>'
            . $scope
            . '<button class="btn btn--primary btn--sm" type="submit">Save</button>'
            . '</form></details>';
    }

    /**
     * @param list<int> $ids
     * @param array<string, mixed> $state
     * @param list<int> $types
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     */
    private static function table(array $ids, array $state, array $types, array $fields): string
    {
        $in = implode(',', array_map('intval', $ids));
        $items = [];
        foreach (Database::rows("SELECT item_id, item_title, create_date, core_log_updated, item_type_id FROM items WHERE item_id IN ({$in})") as $row) {
            $items[(int) $row['item_id']] = $row;
        }
        $facts = WorkScreen::workCardFacts($ids);
        $excerpts = self::excerpts($ids, (string) $state['q'], $fields);

        $statusFields = [];
        $statusValues = [];
        foreach (array_unique(array_map(static fn (array $row): int => (int) $row['item_type_id'], $items)) as $typeId) {
            $status = WorkScreen::workStatusField($typeId);
            if ($status !== null && $status['values'] !== []) {
                $statusFields[$typeId] = $status;
                foreach (RecordQuery::values($ids, $status['id']) as $itemId => $value) {
                    $statusValues[$itemId] = $value;
                }
            }
        }

        $base = Router::continueUrl('items');
        $rows = [];
        foreach ($ids as $id) {
            $row = $items[$id] ?? null;
            if ($row === null) {
                continue;
            }
            $fact = $facts[$id] ?? ['owner' => '', 'detail' => '', 'next' => '', 'log' => ''];
            $record = Blocks::itemRecord($row, $base . '&item=' . $id, Blocks::itemActions($id, $base));
            $bits = array_values(array_filter([
                trim((string) ($fact['detail'] ?? '')),
                trim((string) ($fact['next'] ?? '')) !== '' ? trim((string) $fact['next']) : trim((string) ($fact['log'] ?? '')),
            ]));
            $record['meta'] = implode(' · ', $bits);
            if (isset($excerpts[$id])) {
                $record['meta'] = $excerpts[$id]['text'];
                $record['metaHtml'] = $excerpts[$id]['html'];
            }
            $status = $statusValues[$id] ?? '';
            $record['cells'] = [
                'stand' => $status,
                'owner' => trim((string) ($fact['owner'] ?? '')),
                'opened' => Presence::ago((int) $row['create_date']),
                'updated' => Presence::ago((int) ($row['core_log_updated'] ?: $row['create_date'])),
            ];
            $record['titles'] = [
                'opened' => Stamp::dayTime((int) $row['create_date']),
                'updated' => Stamp::dayTime((int) ($row['core_log_updated'] ?: $row['create_date'])),
            ];
            if ($status !== '') {
                $record['tones']['stand'] = Presence::tone($status);
            }
            $rows[] = $record;
        }

        $columns = [];
        if ($statusFields !== []) {
            $first = reset($statusFields);
            $name = ucfirst(WorkScreen::plainFieldName((string) $first['name']));
            $columns[] = ['key' => 'stand', 'label' => count($statusFields) === 1 ? ($name !== '' ? $name : 'Status') : 'Status'];
        }
        $columns[] = ['key' => 'owner', 'label' => 'Assigned to'];
        $columns[] = ['key' => 'opened', 'label' => 'Opened'];
        $columns[] = ['key' => 'updated', 'label' => 'Updated'];

        return Blocks::recordList([
            'column' => count($types) === 1 ? self::typeName($types[0]) : 'Record',
            'columns' => $columns,
            'searchLabel' => 'Narrow these results',
            'groups' => [['rows' => $rows]],
        ]);
    }

    /**
     * Where the first word was found, for records whose name does not already contain it.
     *
     * @param list<int> $ids
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     * @return array<int, array{text: string, html: string}>
     */
    private static function excerpts(array $ids, string $q, array $fields): array
    {
        $terms = SavedSearch::terms($q);
        if ($terms === [] || $ids === []) {
            return [];
        }
        $term = $terms[0];
        $in = implode(',', array_map('intval', $ids));
        $like = '%' . Database::escapeLike($term) . '%';
        $labels = [];
        foreach ($fields as $field) {
            $labels[$field['id']] = $field['label'];
        }

        $found = [];
        foreach (Database::rows(
            "SELECT item_id, custom_field_id, value FROM item_field_values WHERE item_id IN ({$in}) AND value LIKE ? ESCAPE '\\' ORDER BY custom_field_id",
            [$like]
        ) as $row) {
            $id = (int) $row['item_id'];
            if (!isset($found[$id]) && isset($labels[(int) $row['custom_field_id']])) {
                $found[$id] = [$labels[(int) $row['custom_field_id']], (string) $row['value']];
            }
        }
        foreach (Database::rows(
            "SELECT item_id, log_text FROM core_log WHERE item_id IN ({$in}) AND log_text LIKE ? ESCAPE '\\' ORDER BY log_item_sequence DESC",
            [$like]
        ) as $row) {
            $id = (int) $row['item_id'];
            if (!isset($found[$id])) {
                $found[$id] = ['Note', (string) $row['log_text']];
            }
        }

        $out = [];
        foreach ($found as $id => [$label, $text]) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));
            $at = mb_stripos($text, $term);
            if ($at === false) {
                continue;
            }
            $start = max(0, $at - 40);
            $before = ($start > 0 ? '…' : '') . mb_substr($text, $start, $at - $start);
            $hit = mb_substr($text, $at, mb_strlen($term));
            $afterRaw = mb_substr($text, $at + mb_strlen($term), 70);
            $after = $afterRaw . (mb_strlen($text) > $at + mb_strlen($term) + 70 ? '…' : '');
            $out[$id] = [
                'text' => $label . ': ' . $before . $hit . $after,
                'html' => '<span class="finder__where">' . Html::e($label) . '</span> ' . Html::e($before)
                    . '<mark>' . Html::e($hit) . '</mark>' . Html::e($after),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $state
     */
    private static function pager(array $state, int $page, int $pages, int $total): string
    {
        $from = ($page - 1) * self::PAGE_SIZE + 1;
        $to = min($total, $page * self::PAGE_SIZE);
        $html = '<nav class="finder__pager" aria-label="Pages"><span class="finder__range">Showing ' . $from . '–' . $to . ' of ' . number_format($total) . '</span>';
        if ($pages > 1) {
            $html .= '<span class="finder__steps">';
            $html .= $page > 1
                ? '<a class="btn btn--sm" rel="prev" href="' . Html::e(self::href($state, true, $page - 1)) . '">Previous</a>'
                : '';
            $html .= $page < $pages
                ? '<a class="btn btn--sm" rel="next" href="' . Html::e(self::href($state, true, $page + 1)) . '">Next</a>'
                : '';
            $html .= '</span>';
        }

        return $html . '</nav>';
    }

    /**
     * @param list<int> $types
     * @param list<array{id: int, label: string, kind: string, values: list<string>, group: string}> $fields
     */
    private static function save(string $slug, array $types, array $fields): void
    {
        $problems = [];
        $state = self::state($types, $fields, $problems);
        $name = trim((string) ($_POST['search_name'] ?? ''));
        $back = self::href($state);

        $app = ApplicationStore::findBySlug($slug);
        $userId = (string) ($_SESSION['access_user_id'] ?? '');
        if ($name === '') {
            View::flash('Name the search before you save it.', 'danger');
        } elseif ($app === null || $userId === '') {
            View::flash('This search could not be saved.', 'danger');
        } elseif (Database::rows('SELECT search_id FROM saved_searches WHERE search_name = ? AND user = ?', [$name, $userId]) !== []) {
            View::flash('You already have a saved search called "' . $name . '". Choose another name.', 'danger');
        } else {
            $criteria = self::criteria($state);
            $everyone = (string) ($_POST['security'] ?? '') === 'all' && (int) ($_SESSION['access_role_id'] ?? 5) <= 1;
            Database::insert('saved_searches', [
                'user' => $everyone ? 'all' : $userId,
                'search_name' => mb_substr($name, 0, 80),
                'search_description' => '',
                'saved_search_sql' => SavedSearch::compile($criteria),
                'application' => ApplicationStore::scopeKey($app),
                'criteria_json' => SavedSearch::encode($criteria),
            ]);
            View::flash('Saved "' . mb_substr($name, 0, 80) . '".');
        }

        header('Location: ' . Router::absolute($back));
        exit;
    }
}
