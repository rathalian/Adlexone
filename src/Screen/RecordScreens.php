<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Http\Request;

use Adlexone\Application\ApplicationSettings;
use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Application\RecordQuery;
use Adlexone\Application\Rules;
use Adlexone\Application\ScreenCatalog;
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
use Adlexone\Http\Router;
use Adlexone\Http\View;
use Adlexone\Application\Actions;
use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;

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
        $option = (string) (Request::queryValue('option') ?? '');
        if ($option === 'save_steps' && $capability === ScreenCatalog::STEPS) {
            RecordPlan::saveSteps($config);
            return;
        }
        if ($option === 'save_owners' && $capability === ScreenCatalog::OWNERS) {
            RecordPeople::saveOwners($config);
            return;
        }
        if ($option === 'save_intake' && $capability === ScreenCatalog::INTAKE) {
            RecordRegister::saveIntake($config);
            return;
        }

        match ($capability) {
            ScreenCatalog::COUNTS => RecordCensus::counts($config),
            ScreenCatalog::OWNERS => RecordPeople::owners($config),
            ScreenCatalog::STEPS => RecordPlan::steps($config),
            ScreenCatalog::DIARY => RecordPlan::diary($config),
            ScreenCatalog::PARTIES => RecordPeople::parties($config),
            ScreenCatalog::ACTIVITY => RecordRegister::activity($config),
            ScreenCatalog::FILES => RecordRegister::files($config),
            ScreenCatalog::AGE => RecordCensus::age($config),
            ScreenCatalog::REGISTER => RecordRegister::register($config),
            ScreenCatalog::INTAKE => RecordRegister::intake($config),
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
            $open = RecordQuery::strings((string) (Request::postedValue('open_statuses') ?? ''));
            if ($open !== []) {
                $config['open_statuses'] = $open;
            }
        }
        if ($capability === ScreenCatalog::ACTIVITY) {
            $days = (int) (Request::postedValue('days') ?? 0);
            if ($days > 0) {
                $config['days'] = (string) min($days, 365);
            }
            $groupId = (int) (Request::postedValue('group_id') ?? 0);
            if ($groupId > 0 && array_key_exists($groupId, \Adlexone\Application\Rules::groups())) {
                $config['group_id'] = (string) $groupId;
            }
        }
        if ($capability === ScreenCatalog::INTAKE) {
            $value = trim((string) (Request::postedValue('status_value') ?? ''));
            $status = WorkScreen::workStatusField($typeId, $slug);
            if ($value !== '' && $status !== null && in_array($value, $status['values'], true)) {
                $config['status_value'] = $value;
            }
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{type_id: int, ids: list<int>, items: array<int, array{id: int, title: string, created: int, owner_id: int, owner: string}>, fields: list<array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}>}|null
     */
    public static function need(array $config): ?array
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
    public static function openSet(array $loaded, array $config): ?array
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
    public static function statusValues(array $loaded): array
    {
        $status = WorkScreen::workStatusField($loaded['type_id']);
        if ($status === null) {
            return [];
        }

        return RecordQuery::values($loaded['ids'], $status['id']);
    }

    /**
     * @param array{id: int, title: string, owner: string} $item
     * @param array<string, string> $cells
     * @return array<string, mixed>
     */
    public static function recordRow(array $item, array $cells, string $search): array
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
     * @param array<string, list<array<string, mixed>>> $buckets
     * @param list<array{key: string, label: string, wrap?: bool}> $columns
     */
    public static function groupedRecords(array $buckets, array $columns, string $emptyTitle, string $emptyCopy): string
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
    public static function editor(string $column, array $columns, array $groups, string $action, string $submit, string $emptyTitle, string $emptyCopy): string
    {
        $prepared = [];
        $count = 0;
        foreach ($groups as $group) {
            $groupRows = [];
            foreach ($group['rows'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $count++;
                $row['control'] = self::labelControl((string) ($row['control'] ?? ''), (string) ($row['name'] ?? ''));
                $groupRows[] = $row;
            }
            if ($groupRows === []) {
                continue;
            }
            $prepared[] = ['label' => (string) ($group['label'] ?? ''), 'rows' => $groupRows];
        }
        if ($count === 0) {
            return self::emptyList($emptyTitle, $emptyCopy);
        }

        return Blocks::recordList([
            'column' => $column,
            'columns' => $columns,
            'searchLabel' => 'Search',
            'noMatch' => 'No matches.',
            'empty' => $emptyTitle,
            'groups' => $prepared,
            'controlLabel' => 'Update',
            'formAction' => $action,
            'submitLabel' => $submit,
        ]);
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

    public static function emptyList(string $title, string $copy): string
    {
        return Blocks::emptyState([
            'illustration' => 'empty-work',
            'title' => $title,
            'copy' => $copy,
        ]);
    }

    public static function title(array $item): string
    {
        return $item['title'] !== '' ? $item['title'] : ('Record ' . $item['id']);
    }

    public static function itemHref(int $itemId): string
    {
        return self::base() . '&item=' . $itemId;
    }

    public static function base(): string
    {
        $page = Page::current();
        if ($page->inApplication()) {
            return Router::applicationUrl($page->applicationSlug(), $page->navId());
        }

        return Router::page();
    }

    public static function show(string $title, string $help, string $html, string $lead = ''): void
    {
        Page::current()->suggestTitle($title);
        Page::current()->setBody(Blocks::vertical([[
            'title' => $title,
            'html' => '<p class="form-help">' . Html::e($help) . '</p>' . $lead . $html,
            'class' => 'card--work',
        ]]));
    }

    public static function missing(string $copy): void
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

    public static function back(): void
    {
        header('Location: ' . self::base());
        exit;
    }

    /**
     * A screen-specific field remains the first choice. The application's
     * workflow choice comes next, followed by the established name heuristic.
     *
     * @param array<string, mixed> $loaded
     * @param array<string, mixed> $config
     */
    public static function chosen(array $loaded, array $config, string $purpose): ?array
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
}
