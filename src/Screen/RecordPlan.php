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


final class RecordPlan
{


    /**
     * @param array<string, mixed> $config
     */
    public static function steps(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordScreens::chosen($loaded, $config, 'step');
        if ($field === null || !in_array($field['type'], [FieldTypes::TEXT_BOX, FieldTypes::TEXT_AREA], true)) {
            RecordScreens::missing('Add a text field for the next step on the record type.');
            return;
        }
        $open = RecordScreens::openSet($loaded, $config);
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $statusValues = RecordScreens::statusValues($loaded);
        $empty = [];
        $filled = [];
        foreach ($loaded['items'] as $item) {
            if (!RecordQuery::isOpen($open, $statusValues[$item['id']] ?? '')) {
                continue;
            }
            $value = $values[$item['id']] ?? '';
            $row = [
                'name' => RecordScreens::title($item),
                'href' => RecordScreens::itemHref($item['id']),
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
        RecordScreens::show('Next steps', 'Finished records stay off this list. Saving writes ' . $field['label'] . ' and runs the update rules.', RecordScreens::editor(
            'Record',
            [['key' => 'stand', 'label' => 'Status']],
            $groups,
            RecordScreens::base() . '&option=save_steps',
            'Save',
            'No open records',
            'Finished records stay off this list.'
        ), $lead);
    }


    /**
     * @param array<string, mixed> $config
     */
    public static function saveSteps(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordScreens::chosen($loaded, $config, 'step');
        if ($field === null) {
            RecordScreens::back();
            return;
        }
        $open = RecordScreens::openSet($loaded, $config);
        $current = RecordQuery::values($loaded['ids'], $field['id']);
        $statusValues = RecordScreens::statusValues($loaded);
        foreach ($loaded['items'] as $item) {
            if (!RecordQuery::isOpen($open, $statusValues[$item['id']] ?? '')) {
                continue;
            }
            $key = 'step_' . $item['id'];
            if (!array_key_exists($key, Request::postedAll())) {
                continue;
            }
            $next = trim((string) Request::postedValue($key));
            if ($next === ($current[$item['id']] ?? '')) {
                continue;
            }
            RecordQuery::saveField($item['id'], $field['id'], $next);
        }
        RecordScreens::back();
    }


    /**
     * @param array<string, mixed> $config
     */
    public static function diary(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordScreens::chosen($loaded, $config, 'date');
        if ($field === null || !in_array($field['type'], [FieldTypes::DATE, FieldTypes::DATETIME], true)) {
            RecordScreens::missing('Add a date field on the record type.');
            return;
        }
        $open = RecordScreens::openSet($loaded, $config);
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $statusValues = RecordScreens::statusValues($loaded);
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
            $row = RecordScreens::recordRow($item, [
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
        RecordScreens::show('Diary', 'Grouped by ' . $field['label'] . '. Finished records stay off this list.', RecordScreens::groupedRecords(
            $buckets,
            [['key' => 'stand', 'label' => 'Status'], ['key' => 'when', 'label' => $field['label']], ['key' => 'due', 'label' => 'Due']],
            'No open records',
            'Finished records stay off this list.'
        ), $lead);
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
}
