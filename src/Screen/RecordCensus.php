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
use Adlexone\Application\Actions;
use Adlexone\Database\Database;
use Adlexone\Data\FieldTypes;
use Adlexone\Data\FieldValidation;
use Adlexone\Data\MenuOptions;


final class RecordCensus
{


    /**
     * @param array<string, mixed> $config
     */
    public static function counts(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordScreens::chosen($loaded, $config, 'menu');
        if ($field === null) {
            RecordScreens::missing('Add a menu field on the record type.');
            return;
        }
        $focus = (string) (Request::queryValue('focus') ?? '');
        $focusValue = (string) (Request::queryValue('value') ?? '');
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
        RecordScreens::show($field['label'], $help, Blocks::recordList([
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
    public static function age(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordQuery::field($loaded['fields'], (int) ($config['field_id'] ?? 0));
        $fromField = $field !== null && in_array($field['type'], [FieldTypes::DATE, FieldTypes::DATETIME], true);
        $open = RecordScreens::openSet($loaded, $config);
        $values = $fromField ? RecordQuery::values($loaded['ids'], $field['id']) : [];
        $statusValues = RecordScreens::statusValues($loaded);
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
            $row = RecordScreens::recordRow($item, [
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
        RecordScreens::show('Ageing', $help, RecordScreens::groupedRecords(
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
            $rows[] = RecordScreens::recordRow($item, [], $item['owner']);
        }
        $label = $value === '' ? 'Not set' : $value;
        RecordScreens::show($field['label'], 'Records with this ' . strtolower($field['label']) . '.', Blocks::recordList([
            'column' => 'Record',
            'columns' => [],
            'searchLabel' => 'Search',
            'primary' => ['href' => RecordScreens::base(), 'label' => 'Counts'],
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

        return RecordScreens::base() . '&focus=value&value=' . rawurlencode($value);
    }


    /**
     * @param array{id: int, label: string, type: string, values: list<string>, plain: string} $field
     */
    private static function workHref(array $field, string $value): string
    {
        if (!Page::current()->inApplication()) {
            return RecordScreens::base();
        }
        $slug = Page::current()->applicationSlug();
        $app = ApplicationStore::findBySlug($slug);
        if ($app === null) {
            return RecordScreens::base();
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

        return RecordScreens::base();
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
     * @param array{id: int, title: string} $item
     */
    private static function plural(int|float $count, string $noun): string
    {
        $count = (int) $count;

        return $count . ' ' . $noun . ($count === 1 ? '' : 's');
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
