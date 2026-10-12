<?php
declare(strict_types=1);

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Rules;
use Adlexone\FrameOne\AppAccess;
use Adlexone\FrameOne\AppSettings;
use Adlexone\support\Database;
use Adlexone\support\RenderViews;

/**
 * FrameOne per-app settings (default item type for Create / Advanced search shortcuts).
 */
function openFrameOneAppSettings(): void
{
    $slug = AppAccess::slug();
    if ($slug === '') {
        RenderViews::buildResponse('Open settings from inside an application.');
        return;
    }
    if (!AppAccess::canSettings()) {
        \Adlexone\Auth\Access::deny();
    }

    switch ((string) ($_GET['option'] ?? 'settings')) {
        case 'update_settings':
            updateFrameOneAppSettings($slug);
            break;
        case 'new_rule':
            showFrameOneRuleForm($slug, null);
            break;
        case 'edit_rule':
            showFrameOneRuleForm($slug, (int) ($_GET['rule_id'] ?? 0));
            break;
        case 'add_rule':
            saveFrameOneRule($slug, 0);
            break;
        case 'update_rule':
            saveFrameOneRule($slug, (int) ($_POST['rule_id'] ?? 0));
            break;
        case 'delete_rule':
            deleteFrameOneRule($slug);
            break;
        case 'settings':
        default:
            showFrameOneAppSettings($slug);
            break;
    }
}

function showFrameOneAppSettings(string $slug): void
{
    $valueArray = [''];
    $displayArray = ['— none —'];
    foreach (Database::select('item_types', ['item_type_id', 'item_type_name'], '', [], 'item_type_name ASC') as $row) {
        $valueArray[] = (string) $row['item_type_id'];
        $displayArray[] = (string) $row['item_type_name'];
    }
    $current = AppSettings::defaultItemTypeId($slug);

    $html = RenderViews::buildForm(
        'Application settings',
        MAN_BASE_URL . '&option=update_settings',
        [
            'Default item type' => RenderViews::buildSelectDropdown('default_item_type_id', $valueArray, $displayArray, $current),
        ],
        [
            RenderViews::buildFormButton('submit', 'submit_button', 'Save'),
            RenderViews::buildFormButton('reset', 'reset', 'Reset'),
        ]
    );
    $html .= frameOneRulesHtml($slug);
    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function frameOneRulesHtml(string $slug): string
{
    $app = ApplicationStore::findBySlug($slug);
    if ($app === null) {
        return '';
    }
    $applicationId = (int) $app['application_id'];
    $typeIds = AppSettings::allowedItemTypeIds($slug);
    $fields = Rules::fieldChoices($typeIds);
    $events = Rules::events();
    $operators = Rules::operators();
    $outcomes = Rules::outcomes();

    $grouped = [];
    foreach (Rules::forApplication($applicationId) as $rule) {
        $outcome = (string) $rule['outcome'];
        $grouped[$outcomes[$outcome] ?? $outcome][] = $rule;
    }

    $listGroups = [];
    foreach ($grouped as $outcomeLabel => $rows) {
        usort($rows, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));
        $listRows = [];
        foreach ($rows as $rule) {
            $name = (string) $rule['name'];
            $id = (int) $rule['id'];
            $when = $events[(string) $rule['event']] ?? (string) $rule['event'];
            $condition = (string) $rule['operator'] === Rules::OP_ALWAYS
                ? 'Always'
                : ($fields[(string) $rule['field_key']] ?? (string) $rule['field_key'])
                    . ' ' . strtolower($operators[(string) $rule['operator']] ?? (string) $rule['operator'])
                    . ' ' . (string) $rule['match_value'];
            $description = $when . '. ' . $condition . '.';
            if ((int) $rule['enabled'] !== 1) {
                $description = 'Off. ' . $description;
            }
            $listRows[] = [
                'name' => $name,
                'href' => MAN_BASE_URL . '&option=edit_rule&rule_id=' . $id,
                'meta' => $outcomeLabel,
                'cells' => [
                    'description' => $description,
                ],
                'search' => $description,
                'actions' => [[
                    'href' => MAN_BASE_URL . '&option=delete_rule&rule_id=' . $id,
                    'label' => TXT_315,
                    'tone' => 'danger',
                    'confirm' => $name . "\n" . TXT_400,
                ]],
            ];
        }
        $listGroups[] = ['label' => $outcomeLabel, 'rows' => $listRows];
    }

    $list = RenderViews::buildRecordList([
        'column' => TXT_299,
        'columns' => [
            ['key' => 'description', 'label' => TXT_153, 'wrap' => true],
        ],
        'searchLabel' => TXT_3,
        'primary' => $typeIds === [] ? null : [
            'href' => MAN_BASE_URL . '&option=new_rule',
            'label' => 'Add a rule',
        ],
        'empty' => 'No rules yet.',
        'noMatch' => TXT_688,
        'groups' => $listGroups,
    ]);

    return RenderViews::buildVerticalCards([['title' => 'Rules', 'html' => $list]]);
}

function showFrameOneRuleForm(string $slug, ?int $ruleId): void
{
    $app = ApplicationStore::findBySlug($slug);
    if ($app === null) {
        RenderViews::buildResponse('Open settings from inside an application.');
        return;
    }
    $applicationId = (int) $app['application_id'];
    $rule = null;
    if ($ruleId !== null && $ruleId > 0) {
        $rule = Rules::find($ruleId, $applicationId);
        if ($rule === null) {
            RenderViews::buildResponse('That rule is not on this application.');
            return;
        }
    }

    $typeIds = AppSettings::allowedItemTypeIds($slug);
    $typeValues = [];
    $typeLabels = [];
    foreach ($typeIds as $typeId) {
        $type = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId]);
        $typeValues[] = (string) $typeId;
        $typeLabels[] = (string) ($type['item_type_name'] ?? ('Type ' . $typeId));
    }
    if ($typeValues === []) {
        RenderViews::buildResponse('Add a record type before creating a rule.');
        return;
    }

    $fields = Rules::fieldChoices($typeIds);
    $events = Rules::events();
    $operators = Rules::operators();
    $outcomes = Rules::outcomes();
    $editing = $rule !== null;
    $selectedType = (string) ($rule['item_type_id'] ?? ($typeValues[0] ?? ''));
    $enabled = $editing && (int) $rule['enabled'] !== 1 ? 'No' : 'Yes';

    $formFields = [
        'Name' => RenderViews::buildTextInput('name', (string) ($rule['name'] ?? ''), 'Email the owner when priority is high'),
        'Enabled' => RenderViews::buildSelectDropdown('enabled', ['Yes', 'No'], ['Yes', 'No'], $enabled),
        'Record type' => RenderViews::buildSelectDropdown('item_type_id', $typeValues, $typeLabels, $selectedType),
        'When' => RenderViews::buildSelectDropdown('event', array_keys($events), array_values($events), (string) ($rule['event'] ?? Rules::EVENT_UPDATE)),
        'If' => RenderViews::buildSelectDropdown('operator', array_keys($operators), array_values($operators), (string) ($rule['operator'] ?? Rules::OP_BECOMES))
            . ' ' . RenderViews::buildSelectDropdown('field_key', array_keys($fields), array_values($fields), (string) ($rule['field_key'] ?? ''))
            . ' ' . RenderViews::buildTextInput('match_value', (string) ($rule['match_value'] ?? ''), 'High'),
        'Then' => RenderViews::buildSelectDropdown('outcome', array_keys($outcomes), array_values($outcomes), (string) ($rule['outcome'] ?? Rules::OUTCOME_EMAIL)),
        'Email to' => RenderViews::buildSelectDropdown('recipient', array_keys(Rules::recipients()), array_values(Rules::recipients()), (string) ($rule['recipient'] ?? Rules::TO_OWNER))
            . ' ' . RenderViews::buildTextInput('address', (string) ($rule['address'] ?? ''), 'name@example.com'),
        'Subject' => RenderViews::buildTextInput('subject', (string) ($rule['subject'] ?? ''), 'ITEM_TITLE'),
        'Message' => RenderViews::buildTextArea('body', (string) ($rule['body'] ?? ''), '4'),
    ];
    if ($editing) {
        $formFields[''] = RenderViews::buildHiddenInput('rule_id', (string) $rule['id']);
    }

    define('BODY_CONTENT', RenderViews::buildForm(
        $editing ? 'Edit rule' : 'Add a rule',
        MAN_BASE_URL . '&option=' . ($editing ? 'update_rule' : 'add_rule'),
        $formFields,
        [
            RenderViews::buildFormButton('submit', 'submit_button', $editing ? 'Save' : 'Add rule'),
            RenderViews::buildFormButton('button', '', 'Back', 'onclick="location.href=\'' . MAN_BASE_URL . '&option=settings\'"', 'secondary'),
        ]
    ));
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function saveFrameOneRule(string $slug, int $ruleId): void
{
    $app = ApplicationStore::findBySlug($slug);
    if ($app === null) {
        RenderViews::buildResponse('Open settings from inside an application.');
        return;
    }
    $typeId = (int) ($_POST['item_type_id'] ?? 0);
    if (!AppSettings::allowsItemType($slug, $typeId)) {
        \Adlexone\Auth\Access::deny();
    }
    $applicationId = (int) $app['application_id'];
    if ($ruleId > 0) {
        Rules::update($ruleId, $applicationId, $_POST);
    } else {
        Rules::add($applicationId, $_POST);
    }
    header('Location: ' . MAN_BASE_URL . '&option=settings');
    exit;
}

function deleteFrameOneRule(string $slug): void
{
    $app = ApplicationStore::findBySlug($slug);
    if ($app === null) {
        RenderViews::buildResponse('Open settings from inside an application.');
        return;
    }
    Rules::delete((int) ($_GET['rule_id'] ?? 0), (int) $app['application_id']);
    header('Location: ' . MAN_BASE_URL . '&option=settings');
    exit;
}

function updateFrameOneAppSettings(string $slug): void
{
    $settings = AppSettings::get($slug);
    $settings['default_item_type_id'] = trim((string) ($_POST['default_item_type_id'] ?? ''));
    AppSettings::put($slug, $settings);
    header('Location: ' . MAN_BASE_URL . '&option=settings');
    exit;
}
