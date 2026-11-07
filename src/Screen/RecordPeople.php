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


final class RecordPeople
{


    /**
     * @param array<string, mixed> $config
     */
    public static function owners(array $config): void
    {
        $loaded = RecordScreens::need($config);
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
                    'name' => RecordScreens::title($item),
                    'href' => RecordScreens::itemHref($item['id']),
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
        RecordScreens::show('Owners', 'Records with no owner are listed first. Saving sets the owner and runs the update rules.', RecordScreens::editor(
            'Record',
            [],
            $blocks,
            RecordScreens::base() . '&option=save_owners',
            'Save',
            'No records yet',
            'Create the first record.'
        ), $lead);
    }


    /**
     * @param array<string, mixed> $config
     */
    public static function saveOwners(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $allowed = array_map('strval', array_keys(Rules::people()));
        $allowed[] = '0';
        foreach ($loaded['items'] as $item) {
            $key = 'owner_' . $item['id'];
            if (!array_key_exists($key, Request::postedAll())) {
                continue;
            }
            $next = trim((string) Request::postedValue($key));
            if (!in_array($next, $allowed, true) || (int) $next === $item['owner_id']) {
                continue;
            }
            RecordQuery::saveOwner($item['id'], (int) $next);
        }
        RecordScreens::back();
    }


    /**
     * @param array<string, mixed> $config
     */
    public static function parties(array $config): void
    {
        $loaded = RecordScreens::need($config);
        if ($loaded === null) {
            return;
        }
        $field = RecordQuery::chosen($loaded['fields'], $config, 'party');
        if ($field === null) {
            RecordScreens::missing('Add a text field for the party on the record type.');
            return;
        }
        $values = RecordQuery::values($loaded['ids'], $field['id']);
        $step = RecordScreens::chosen($loaded, [], 'step');
        $steps = $step === null ? [] : RecordQuery::values($loaded['ids'], $step['id']);
        $statusValues = RecordScreens::statusValues($loaded);
        $groups = [];
        foreach ($loaded['items'] as $item) {
            $party = $values[$item['id']] ?? '';
            $label = $party === '' ? 'No ' . strtolower($field['label']) : $party;
            $groups[$label][] = RecordScreens::recordRow($item, [
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
        RecordScreens::show('Parties', 'Grouped by ' . $field['label'] . '.', $blocks === []
            ? RecordScreens::emptyList('No records yet', 'Create the first record.')
            : Blocks::recordList([
                'column' => 'Record',
                'columns' => $columns,
                'searchLabel' => 'Search',
                'empty' => 'No records yet',
                'groups' => $blocks,
            ]), $lead);
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
}
