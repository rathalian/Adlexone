<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\Http\Page;
use Adlexone\support\Database;
use Adlexone\Text\Wording;
use Adlexone\Theme\Theme;

/**
 * Page pieces: cards, lists, links, empty states.
 * Behavior for lists lives in layouts/js/app.js, opted in with data-record-list.
 */
final class Blocks
{
    public static function sideButton(string $queryString, string $buttonText, string|false $navBarTitle = false, bool $navBarDivider = false): string
    {
        return
            ($navBarDivider ? '<div class="side-divider"></div>' : '') .
            '<div class="side-group">' .
            (!empty($navBarTitle) ? '<div class="side-title side-title--spaced">' . Html::e($navBarTitle) . '</div>' : '') .
            '<a class="navbtn" href="' . Html::e($queryString) . '">' . Html::e($buttonText) . '</a>' .
            '</div>';
    }

    public static function sideCard(string $navigationButtons): string
    {
        return '<aside id="sidebar" class="sidebar" aria-label="Primary">'
            . '<nav class="side-actions">'
            . $navigationButtons
            . '</nav></aside>';
    }

    /**
     * @param list<array{title?: string, html?: string, render?: callable, class?: string}> $bodyBlocks
     */
    public static function vertical(array $bodyBlocks): string
    {
        $titles = [];
        foreach ($bodyBlocks as $block) {
            $rawTitle = trim((string) ($block['title'] ?? ''));
            if ($rawTitle !== '') {
                $titles[] = $rawTitle;
            }
        }
        $page = Page::current();
        $liftTitle = count($bodyBlocks) === 1
            && count($titles) === 1
            && !$page->hasTitle()
            && !$page->hasSectionNav();
        if ($liftTitle) {
            $page->setTitle(trim(html_entity_decode(strip_tags($titles[0]), ENT_QUOTES, 'UTF-8')));
        }

        $html = '<div class="grid">';
        foreach ($bodyBlocks as $block) {
            $rawTitle = trim((string) ($block['title'] ?? ''));
            $title = $rawTitle !== '' ? Html::e($rawTitle) : '';
            $repeatsSection = self::titleRepeatsSection($rawTitle);
            $extraClass = trim((string) ($block['class'] ?? ''));
            $disclosure = !empty($block['disclosure']);
            if ($disclosure) {
                $extraClass = trim($extraClass . ' card--disclosure');
            }
            $html .= '<' . ($disclosure ? 'details' : 'section')
                . ' class="card span-2' . ($extraClass !== '' ? ' ' . Html::e($extraClass) : '') . '"'
                . '>';
            if ($title !== '' && !$liftTitle && !$repeatsSection) {
                if ($disclosure) {
                    $html .= '<summary class="card-title">' . $title . '</summary>';
                } else {
                    $kicker = trim((string) ($block['kicker'] ?? ''));
                    $badge = trim((string) ($block['badge'] ?? ''));
                    $mark = trim((string) ($block['mark'] ?? ''));
                    $facts = [];
                    foreach ($block['facts'] ?? [] as $fact) {
                        $fact = trim((string) $fact);
                        if ($fact !== '') {
                            $facts[] = $fact;
                        }
                    }
                    if ($kicker !== '' || $badge !== '' || $mark !== '' || $facts !== []) {
                        $html .= '<div class="identity">';
                        if ($mark !== '') {
                            $html .= Presence::mark($mark);
                        }
                        $html .= '<div class="identity__copy">';
                        if ($kicker !== '') {
                            $html .= '<p class="card-kicker">' . Html::e($kicker) . '</p>';
                        }
                        $html .= '<div class="card-title">' . $title . '</div>';
                        if ($facts !== []) {
                            $html .= '<p class="identity__facts">' . Html::e(implode(' · ', $facts)) . '</p>';
                        }
                        $html .= '</div>';
                        if ($badge !== '') {
                            $html .= Presence::pill($badge);
                        }
                        $html .= '</div>';
                    } else {
                        $html .= '<div class="card-title">' . $title . '</div>';
                    }
                }
            }
            $html .= '<div class="form-block">';
            if (!empty($block['render']) && is_callable($block['render'])) {
                ($block['render'])();
            } elseif (isset($block['html'])) {
                $html .= $block['html'];
            }
            $html .= '</div></' . ($disclosure ? 'details' : 'section') . '>';
        }

        return $html . '</div>';
    }

    /**
     * @param list<array{title?: string, html?: string, full?: bool}> $blocks
     */
    public static function horizontal(array $blocks, ?int $columns = null): string
    {
        $modeClass = $columns === null ? '-auto' : '-fixed';
        $styleAttr = $columns === null ? '' : ' style="--cols: ' . (int) $columns . ';"';
        $html = '<section class="launchpad-grid ' . $modeClass . '"' . $styleAttr . '>';
        foreach ($blocks as $block) {
            $title = Html::e($block['title'] ?? '');
            $body = $block['html'] ?? '';
            $span = !empty($block['full']) ? ' card -full' : ' card';
            $html .= '<article class="' . $span . '">';
            if ($title !== '') {
                $html .= '<div class="card-title">' . $title . '</div>';
            }
            $html .= '<div class="card__body">' . $body . '</div></article>';
        }

        return $html . '</section>';
    }

    public static function separator(string $label = '', string $class = '', string $style = ''): string
    {
        $classAttr = $class !== '' ? ' ' . Html::e($class) : '';
        $styleAttr = $style !== '' ? ' style="' . Html::e($style) . '"' : '';
        if ($label === '') {
            return '<hr class="rv-separator' . $classAttr . '"' . $styleAttr . ' />';
        }
        $labelEsc = Html::e($label);

        return '<div class="rv-separator-block' . $classAttr . '"' . $styleAttr . ' role="separator" aria-label="' . $labelEsc . '">'
            . '<span class="rv-separator-line" aria-hidden="true"></span>'
            . '<span class="rv-separator-label">' . $labelEsc . '</span>'
            . '<span class="rv-separator-line" aria-hidden="true"></span>'
            . '</div>';
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function itemListColumns(): array
    {
        return [
            ['key' => 'type', 'label' => TXT_119],
            ['key' => 'opened', 'label' => TXT_103],
        ];
    }

    /**
     * @return list<array{href: string, label: string, tone?: string, confirm?: string, target?: string}>
     */
    public static function itemActions(int $itemId, string $base): array
    {
        $actions = [];
        $role = (int) ($_SESSION['access_role_id'] ?? 5);
        $inApplication = \Adlexone\Http\Page::current()->inApplication();
        $record = $base . '&item=' . $itemId;
        if ($role <= 4) {
            $actions[] = [
                'href' => $inApplication ? $record . '&sheet=notes' : $record . '&option=log_entry',
                'label' => $inApplication ? 'Notes' : TXT_246,
            ];
            $actions[] = [
                'href' => $inApplication ? $record . '&sheet=files' : $record . '&option=show_attachments',
                'label' => $inApplication ? 'Files' : TXT_389,
            ];
        }
        if ($role <= 3) {
            $actions[] = [
                'href' => $inApplication ? $record . '&sheet=access' : $record . '&option=change_security',
                'label' => $inApplication ? 'Who can see this' : TXT_28,
            ];
        }
        if ($role <= 5) {
            $actions[] = [
                'href' => \Adlexone\Http\Router::manageUrl('print', 'item=' . $itemId),
                'label' => TXT_625,
                'target' => '_blank',
            ];
        }
        if ($role <= 2) {
            $actions[] = [
                'href' => $base . '&item=' . $itemId . '&option=delete_item',
                'label' => TXT_315,
                'tone' => 'danger',
                'confirm' => TXT_400,
            ];
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array{href: string, label: string, tone?: string, confirm?: string, target?: string}> $actions
     * @return array{name: string, href: string, meta: string, cells: array{type: string, opened: string}, actions: array}
     */
    public static function itemRecord(array $item, string $href, array $actions): array
    {
        static $typeNames = [];
        $itemId = (int) ($item['item_id'] ?? 0);
        $title = trim((string) ($item['item_title'] ?? ''));
        if ($title === '') {
            $title = (string) TXT_357;
        }
        $typeId = (string) ($item['item_type_id'] ?? '');
        if (!array_key_exists($typeId, $typeNames)) {
            $typeRow = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId]);
            $typeNames[$typeId] = (string) ($typeRow['item_type_name'] ?? '');
        }

        return [
            'name' => $title,
            'href' => $href,
            'meta' => Wording::get('LA_102', 'TXT_102') . ' ' . $itemId,
            'cells' => [
                'type' => $typeNames[$typeId],
                'opened' => date(SET_DATE_FORMAT, (int) ($item['create_date'] ?? 0)),
            ],
            'actions' => $actions,
        ];
    }

    /**
     * @param array{illustration?: string, title?: string, copy?: string, action?: string} $opts
     */
    public static function emptyState(array $opts): string
    {
        $illustration = trim((string) ($opts['illustration'] ?? ''));
        $title = trim((string) ($opts['title'] ?? ''));
        $copy = trim((string) ($opts['copy'] ?? ''));
        $action = (string) ($opts['action'] ?? '');
        $src = $illustration !== '' ? Theme::illustrationHref($illustration) : '';

        $html = '<div class="empty-state">';
        if ($src !== '') {
            $html .= '<img class="empty-state__art" src="' . Html::e($src) . '" alt="" width="160" height="120" loading="lazy">';
        }
        if ($title !== '') {
            $html .= '<p class="empty-state__title">' . Html::e($title) . '</p>';
        }
        if ($copy !== '') {
            $html .= '<p class="empty-state__copy">' . Html::e($copy) . '</p>';
        }
        if ($action !== '') {
            $html .= '<div class="empty-state__actions">' . $action . '</div>';
        }

        return $html . '</div>';
    }

    /**
     * @param array<string, mixed> $list
     */
    public static function recordList(array $list): string
    {
        static $sequence = 0;
        $sequence++;
        $id = 'record-list-' . $sequence;

        $column = (string) ($list['column'] ?? (defined('TXT_151') ? TXT_151 : ''));
        $columns = [];
        foreach ($list['columns'] ?? [] as $columnDef) {
            if (!is_array($columnDef)) {
                continue;
            }
            $key = trim((string) ($columnDef['key'] ?? ''));
            $label = trim((string) ($columnDef['label'] ?? ''));
            if ($key === '' || $label === '') {
                continue;
            }
            $columns[] = [
                'key' => $key,
                'label' => $label,
                'wrap' => !empty($columnDef['wrap']),
                'sortHref' => trim((string) ($columnDef['sortHref'] ?? '')),
                'ariaSort' => trim((string) ($columnDef['ariaSort'] ?? '')),
            ];
        }
        $searchLabel = (string) ($list['searchLabel'] ?? (defined('TXT_3') ? TXT_3 : ''));
        $emptyText = (string) ($list['empty'] ?? (defined('TXT_115') ? TXT_115 : ''));
        $noMatch = (string) ($list['noMatch'] ?? (defined('TXT_689') ? TXT_689 : ''));
        $groups = $list['groups'] ?? [];
        $primary = self::recordListPrimary($list['primary'] ?? null);

        $rows = [];
        $hasActions = false;
        $asMenu = false;
        foreach ($groups as $groupIndex => $group) {
            $groupRows = [];
            foreach ($group['rows'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $row['actions'] = self::recordListExtraActions($row);
                $groupRows[] = $row;
                $rows[] = $row;
                if ($row['actions'] !== []) {
                    $hasActions = true;
                }
                if (count($row['actions']) >= 2) {
                    $asMenu = true;
                }
            }
            $groups[$groupIndex]['rows'] = $groupRows;
        }

        $toolbar = (string) ($list['toolbar'] ?? '');
        if ($rows === []) {
            $bar = $primary . $toolbar;
            $emptyIllustration = trim((string) ($list['emptyIllustration'] ?? ''));
            $emptyTitle = trim((string) ($list['emptyTitle'] ?? ''));
            $emptyBody = $emptyIllustration !== ''
                ? self::emptyState([
                    'illustration' => $emptyIllustration,
                    'title' => $emptyTitle !== '' ? $emptyTitle : $emptyText,
                    'copy' => (string) ($list['emptyCopy'] ?? ''),
                    'action' => (string) ($list['emptyAction'] ?? ''),
                ])
                : '<p class="record-list__empty">' . Html::e($emptyText) . '</p>';

            return '<div class="record-list">'
                . ($bar !== '' ? '<div class="record-list__bar">' . $bar . '</div>' : '')
                . $emptyBody
                . '</div>';
        }

        $selectName = trim((string) ($list['selectName'] ?? ''));
        $colCount = 1 + count($columns) + ($hasActions ? 1 : 0) + ($selectName !== '' ? 1 : 0);
        $body = '';
        foreach ($groups as $group) {
            $groupRows = $group['rows'] ?? [];
            if ($groupRows === []) {
                continue;
            }
            $label = trim((string) ($group['label'] ?? ''));
            $body .= '<tbody data-group>';
            if ($label !== '') {
                $body .= '<tr class="record-list__grouphead"><th colspan="' . $colCount . '" scope="rowgroup">'
                    . Html::e($label)
                    . ' <span class="record-list__badge">' . count($groupRows) . '</span></th></tr>';
            }
            foreach ($groupRows as $row) {
                $body .= self::recordListRow($row, $label, $hasActions, $asMenu, $columns, $selectName);
            }
            $body .= '</tbody>';
        }

        $columnHeads = '';
        foreach ($columns as $columnDef) {
            $wrap = !empty($columnDef['wrap']) ? ' record-list__cell--wrap' : '';
            $ariaSort = in_array($columnDef['ariaSort'], ['ascending', 'descending'], true)
                ? ' aria-sort="' . Html::e($columnDef['ariaSort']) . '"'
                : '';
            $label = Html::e($columnDef['label']);
            if ($columnDef['sortHref'] !== '') {
                $label = '<a class="record-list__sort" href="' . Html::e($columnDef['sortHref']) . '">' . $label
                    . '<span class="record-list__sortmark" aria-hidden="true"></span></a>';
            }
            $columnHeads .= '<th class="record-list__cell' . $wrap . '"' . $ariaSort . '>' . $label . '</th>';
        }
        $manageLabel = defined('TXT_388') ? TXT_388 : '';
        $manageHead = $hasActions
            ? '<th class="record-list__manage"><span class="record-list__sr">' . Html::e($manageLabel) . '</span></th>'
            : '';
        $searchId = $id . '-search';

        $selectHead = $selectName !== ''
            ? '<th class="record-list__select"><input type="checkbox" data-select-all aria-label="Select all records"></th>'
            : '';
        $nameSortHref = trim((string) ($list['columnSortHref'] ?? ''));
        $nameAriaSort = trim((string) ($list['columnAriaSort'] ?? ''));
        $nameHead = Html::e($column);
        if ($nameSortHref !== '') {
            $nameHead = '<a class="record-list__sort" href="' . Html::e($nameSortHref) . '">' . $nameHead
                . '<span class="record-list__sortmark" aria-hidden="true"></span></a>';
        }
        $nameSortAttr = in_array($nameAriaSort, ['ascending', 'descending'], true)
            ? ' aria-sort="' . Html::e($nameAriaSort) . '"'
            : '';

        return '<div class="record-list" id="' . Html::e($id) . '" data-record-list>'
            . '<div class="record-list__bar">'
            . '<input id="' . Html::e($searchId) . '" class="input record-list__search" type="search" placeholder="' . Html::e($searchLabel) . '" aria-label="' . Html::e($searchLabel) . '" autocomplete="off">'
            . '<span class="record-list__count" aria-live="polite">' . count($rows) . '</span>'
            . $toolbar
            . $primary
            . '</div>'
            . '<div class="record-list__tablewrap"><table class="table table-hover">'
            . '<thead><tr>' . $selectHead . '<th' . $nameSortAttr . '>' . $nameHead . '</th>' . $columnHeads . $manageHead . '</tr></thead>'
            . $body
            . '</table></div>'
            . '<p class="record-list__nomatch" hidden>' . Html::e($noMatch) . '</p>'
            . '</div>';
    }

    /**
     * @param list<string> $contentElements
     */
    public static function horizontalList(array $contentElements): string
    {
        $html = '<div class="horizontal-content-container">';
        foreach ($contentElements as $element) {
            $html .= '<div class="hcc-item">' . $element . '</div>';
        }

        return $html . '</div>';
    }

    public static function link(
        string $href,
        string $text,
        string $spriteName = '',
        string $class = 'URL',
        string $attributes = '',
        string $target = '_parent',
        string $confirm = ''
    ): string {
        $svg = '';
        if ($spriteName !== '') {
            $svg = '<svg class="icon" aria-hidden="true"><use href="' . Html::e(Theme::spriteHref()) . '#' . Html::e($spriteName) . '"></use></svg>&nbsp;&nbsp;';
        }
        $attr = $attributes !== '' ? ' ' . $attributes : '';
        $attr .= Form::confirmData($confirm);

        return '<a class="link ' . Html::e($class) . '" href="' . Html::e($href) . '"' . $attr
            . ' target="' . Html::e($target) . '">' . $svg . Html::e($text) . '</a>';
    }

    public static function image(string $imageSource, string $enabled = 'Yes', string $altText = '', string $attributes = ''): string
    {
        if ($enabled !== 'Yes') {
            return '';
        }

        return '<img src="' . Html::e($imageSource) . '" alt="' . Html::e($altText) . '"'
            . ($attributes !== '' ? ' ' . $attributes : '') . '>';
    }

    private static function titleRepeatsSection(string $title): bool
    {
        $page = Page::current();
        if (!$page->hasSectionNav() || !$page->hasNavLabel()) {
            return false;
        }
        $plain = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

        return $plain !== '' && strcasecmp($plain, trim($page->navLabel())) === 0;
    }

    /**
     * @param array{href?: string, label?: string}|null $primary
     */
    private static function recordListPrimary(?array $primary): string
    {
        if ($primary === null || ($primary['href'] ?? '') === '' || ($primary['label'] ?? '') === '') {
            return '';
        }

        return '<a class="btn btn--primary btn--sm" href="' . Html::e((string) $primary['href']) . '">'
            . Html::e((string) $primary['label']) . '</a>';
    }

    /**
     * @param array{name?: string, href?: string, actions?: array<int, array<string, mixed>>} $row
     * @return list<array<string, mixed>>
     */
    private static function recordListExtraActions(array $row): array
    {
        $actions = is_array($row['actions'] ?? null) ? $row['actions'] : [];
        $open = (string) ($row['href'] ?? '');
        $extra = [];
        foreach ($actions as $action) {
            if (!is_array($action)) {
                continue;
            }
            if ($open !== '' && (string) ($action['href'] ?? '') === $open) {
                continue;
            }
            $extra[] = $action;
        }

        return $extra;
    }

    /**
     * @param array{name: string, href: string, meta?: string, metaTitle?: string, search?: string, cells?: array<string, string>, actions?: array<int, array{href: string, label: string, tone?: string, confirm?: string, target?: string}>} $row
     * @param list<array{key: string, label: string, wrap?: bool, sortHref?: string, ariaSort?: string}> $columns
     */
    private static function recordListRow(
        array $row,
        string $groupLabel,
        bool $hasActions,
        bool $asMenu,
        array $columns = [],
        string $selectName = ''
    ): string
    {
        $name = (string) ($row['name'] ?? '');
        $meta = (string) ($row['meta'] ?? '');
        $metaHtml = (string) ($row['metaHtml'] ?? '');
        $mark = trim((string) ($row['mark'] ?? ''));
        $search = strtolower($name . ' ' . $groupLabel . ' ' . $meta . ' ' . $mark . ' ' . (string) ($row['search'] ?? ''));
        $nameHtml = '<span class="record-list__name">'
            . ($mark !== '' ? Presence::mark($mark) : '')
            . '<span class="record-list__title"><a class="record-link" href="' . Html::e((string) $row['href']) . '">' . Html::e($name) . '</a>';
        if ($meta !== '') {
            $title = (string) ($row['metaTitle'] ?? '');
            $titleAttr = $title !== '' ? ' title="' . Html::e($title) . '"' : '';
            $nameHtml .= '<div class="record-list__meta"' . $titleAttr . '>' . ($metaHtml !== '' ? $metaHtml : Html::e($meta)) . '</div>';
            $search .= ' ' . strtolower($title);
        }
        $nameHtml .= '</span></span>';

        $cellsHtml = '';
        $cellValues = is_array($row['cells'] ?? null) ? $row['cells'] : [];
        $tones = is_array($row['tones'] ?? null) ? $row['tones'] : [];
        $titles = is_array($row['titles'] ?? null) ? $row['titles'] : [];
        $pillKeys = ['stand', 'status', 'enabled', 'active'];
        foreach ($columns as $columnDef) {
            $key = $columnDef['key'];
            $value = trim((string) ($cellValues[$key] ?? ''));
            $search .= ' ' . strtolower($value);
            $wrap = !empty($columnDef['wrap']) ? ' record-list__cell--wrap' : '';
            $cellTitle = trim((string) ($titles[$key] ?? ''));
            $titleAttr = $cellTitle !== '' ? ' title="' . Html::e($cellTitle) . '"' : '';
            if ($value === '') {
                $inner = '—';
            } elseif (isset($tones[$key]) || in_array($key, $pillKeys, true)) {
                $inner = Presence::pill($value, (string) ($tones[$key] ?? ''));
            } else {
                $inner = Html::e($value);
            }
            $cellsHtml .= '<td class="record-list__cell' . $wrap . '" data-label="' . Html::e((string) ($columnDef['label'] ?? '')) . '"' . $titleAttr . '>' . $inner . '</td>';
        }

        $actionsHtml = '';
        if ($hasActions) {
            $actions = is_array($row['actions'] ?? null) ? $row['actions'] : [];
            foreach ($actions as $action) {
                $search .= ' ' . strtolower((string) ($action['label'] ?? ''));
            }
            $actionsHtml = '<td class="record-list__manage">' . self::recordListActions($actions, $name, $asMenu) . '</td>';
        }

        $select = '';
        if ($selectName !== '' && (int) ($row['itemId'] ?? 0) > 0) {
            $select = '<td class="record-list__select"><input type="checkbox" name="' . Html::e($selectName)
                . '[]" value="' . (int) $row['itemId'] . '" aria-label="Select ' . Html::e($name) . '" data-select-row></td>';
        }

        return '<tr data-search="' . Html::e($search) . '">' . $select . '<td class="record-list__primary">' . $nameHtml . '</td>' . $cellsHtml . $actionsHtml . '</tr>';
    }

    /**
     * @param array<int, array{href?: string, label?: string, tone?: string, confirm?: string, target?: string}> $actions
     */
    private static function recordListActions(array $actions, string $name, bool $asMenu): string
    {
        if ($actions === []) {
            return '';
        }
        $manageLabel = defined('TXT_388') ? TXT_388 : '';
        $buttons = '';
        $splitDanger = false;
        foreach ($actions as $action) {
            $label = (string) ($action['label'] ?? '');
            $confirm = Form::confirmData((string) ($action['confirm'] ?? ''));
            $target = (string) ($action['target'] ?? '');
            $targetAttr = $target !== '' ? ' target="' . Html::e($target) . '"' : '';
            $href = Html::e((string) ($action['href'] ?? ''));
            $aria = ' aria-label="' . Html::e($label . ': ' . $name) . '"';
            if ($asMenu) {
                $danger = ($action['tone'] ?? '') === 'danger';
                if ($danger && !$splitDanger && $buttons !== '') {
                    $buttons .= '<div class="rowmenu__divider" role="separator"></div>';
                    $splitDanger = true;
                }
                $buttons .= '<a class="rowmenu__item' . ($danger ? ' rowmenu__item--danger' : '') . '" role="menuitem" href="' . $href . '"'
                    . $targetAttr . $confirm . $aria . '>'
                    . Html::e($label) . '</a>';
                continue;
            }
            $tone = ($action['tone'] ?? '') === 'danger' ? 'btn--danger' : 'btn--quiet';
            $buttons .= '<a class="btn btn--sm ' . $tone . '" href="' . $href . '"'
                . $targetAttr . $confirm . $aria . '>'
                . Html::e($label) . '</a>';
        }
        if (!$asMenu) {
            return '<span class="record-list__actions">' . $buttons . '</span>';
        }

        return '<details class="rowmenu">'
            . '<summary class="btn btn--sm btn--quiet" aria-label="' . Html::e($manageLabel . ': ' . $name) . '">'
            . Html::e($manageLabel) . '</summary>'
            . '<div class="rowmenu__panel" role="menu">' . $buttons . '</div>'
            . '</details>';
    }

}
