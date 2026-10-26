<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Application\ApplicationBuilder;
use Adlexone\Application\ApplicationPackage;
use Adlexone\Application\Capabilities;
use Adlexone\Auth\Access;
use Adlexone\Auth\Csrf;
use Adlexone\Auth\Permission;
use Adlexone\Application\ScreenCatalog;
use Adlexone\Http\Router;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\Http\Page;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Html\Html;

/**
 * Screen formerly loaded from legacy/.
 */
final class BuilderScreen
{
    public static function open(): void
    {
        if (!Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
            Access::deny();
        }

        define('BUILDER_URL', '?manage=builder');
        $option = (string) ($_GET['option'] ?? '');

        RenderNavigation::applyConfigureNav([['label' => 'Create an application', 'href' => '?manage=builder']]);
        Page::current()->suggestTitle('Create an application');

        switch ($option) {
            case 'create':
                self::createBusinessApplication();
                break;
            case 'import':
                self::importSolutionPackage();
                break;
            default:
                self::showBuilderForm();
                break;
        }
    }
    public static function showBuilderForm(string $error = ''): void
    {
        $h = static fn (string $value): string => Html::e($value);

        $nameRaw = (string) ($_POST['name'] ?? '');
        $slugRaw = (string) ($_POST['slug'] ?? '');
        $hintRaw = (string) ($_POST['hint'] ?? '');
        $recordRaw = (string) ($_POST['record_type_name'] ?? '');
        $icon = (string) ($_POST['icon'] ?? 'ic-grid');
        $blueprint = (string) ($_POST['blueprint'] ?? ApplicationBuilder::BLUEPRINT_MATTERS);
        $blueprints = ApplicationBuilder::blueprints();
        if (!isset($blueprints[$blueprint])) {
            $blueprint = ApplicationBuilder::BLUEPRINT_MATTERS;
        }
        if (($_POST['icon'] ?? '') === '') {
            $icon = (string) ($blueprints[$blueprint]['icon'] ?? 'ic-grid');
        }
        $icon = Capabilities::renamedIcons()[$icon] ?? $icon;
        if (!in_array($icon, Capabilities::icons(), true)) {
            $icon = 'ic-grid';
        }

        $postedScreens = $_POST['screens'] ?? null;
        $selectedScreens = is_array($postedScreens) ? $postedScreens : $blueprints[$blueprint]['screens'];

        $sprite = $h(\Adlexone\Theme\Theme::spriteHref());
        $art = \Adlexone\Theme\Theme::illustrationHref('empty-builder');

        $screenMeta = [
            ScreenCatalog::WORK => ['hint' => 'List or board', 'icon' => 'ic-work'],
            ScreenCatalog::CREATE => ['hint' => 'Add one', 'icon' => 'ic-plus'],
            ScreenCatalog::COUNTS => ['hint' => 'How many in each status', 'icon' => 'ic-counts'],
            ScreenCatalog::OWNERS => ['hint' => 'Who has the work', 'icon' => 'ic-owners'],
            ScreenCatalog::STEPS => ['hint' => 'The next action', 'icon' => 'ic-steps'],
            ScreenCatalog::DIARY => ['hint' => 'Due dates', 'icon' => 'ic-diary'],
            ScreenCatalog::PARTIES => ['hint' => 'Grouped by a name', 'icon' => 'ic-parties'],
            ScreenCatalog::ACTIVITY => ['hint' => 'Recent log lines', 'icon' => 'ic-activity'],
            ScreenCatalog::FILES => ['hint' => 'Files on the records', 'icon' => 'ic-files'],
            ScreenCatalog::AGE => ['hint' => 'How long they have been open', 'icon' => 'ic-ageing'],
            ScreenCatalog::REGISTER => ['hint' => 'The records in columns', 'icon' => 'ic-register'],
            ScreenCatalog::INTAKE => ['hint' => 'A short report form', 'icon' => 'ic-intake'],
            ScreenCatalog::SEARCH_ADVANCED => ['hint' => 'Search every field', 'icon' => 'ic-search'],
            ScreenCatalog::SEARCH_LIST => ['hint' => 'Saved searches', 'icon' => 'ic-saved'],
            ScreenCatalog::ANNOUNCEMENTS => ['hint' => 'The notice on the wall', 'icon' => 'ic-announce'],
            ScreenCatalog::SETTINGS => ['hint' => 'How this application is set up', 'icon' => 'ic-sliders'],
        ];
        $levelMeta = [
            'use' => ['label' => 'Open', 'hint' => 'Open the work and add to it'],
            'settings' => ['label' => 'Settings', 'hint' => 'Change how the application works'],
        ];

        $clientBlueprints = [];
        $blueprintHtml = '<div class="builder__blueprints" role="radiogroup" aria-label="Blueprint">';
        foreach ($blueprints as $key => $meta) {
            $checked = $key === $blueprint ? ' checked' : '';
            $active = $key === $blueprint ? ' is-selected' : '';
            $fieldNames = [];
            $clientFields = [];
            foreach ($meta['fields'] as $field) {
                $fieldName = trim((string) ($field['name'] ?? ''));
                if ($fieldName === '') {
                    continue;
                }
                $fieldNames[] = $fieldName;
                $values = [];
                foreach ($field['values'] ?? [] as $value) {
                    $value = trim((string) $value);
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }
                $clientFields[] = ['name' => $fieldName, 'values' => $values];
            }
            $clientBlueprints[$key] = [
                'hint' => (string) $meta['hint'],
                'record' => (string) ($meta['record'] ?? ''),
                'icon' => (string) ($meta['icon'] ?? 'ic-grid'),
                'screens' => array_values($meta['screens']),
                'fields' => $clientFields,
                'rule' => (string) ($meta['rule'] ?? ''),
            ];
            $fieldsLine = $fieldNames === [] ? 'Add your own fields afterwards' : implode(' · ', $fieldNames);
            $blueprintHtml .= '<label class="builder__blueprint' . $active . '">'
                . '<input type="radio" name="blueprint" value="' . $h($key) . '"' . $checked . '>'
                . '<span class="builder__blueprint-label">' . $h((string) $meta['label']) . '</span>'
                . '<span class="builder__blueprint-hint">' . $h((string) $meta['hint']) . '</span>'
                . '<span class="builder__blueprint-fields">' . $h($fieldsLine) . '</span>'
                . '</label>';
        }
        $blueprintHtml .= '</div>';

        $screensHtml = '<div class="builder__choices">';
        foreach (ScreenCatalog::builderScreens() as $cap => $label) {
            $meta = $screenMeta[$cap] ?? ['hint' => '', 'icon' => 'ic-grid'];
            $checked = in_array($cap, $selectedScreens, true) ? ' checked' : '';
            $active = $checked !== '' ? ' is-selected' : '';
            $screensHtml .= '<label class="builder__choice' . $active . '">'
                . '<input type="checkbox" name="screens[]" value="' . $h($cap) . '"' . $checked . '>'
                . '<span class="builder__choice-mark" aria-hidden="true"><svg class="icon"><use href="' . $sprite . '#' . $h($meta['icon']) . '"></use></svg></span>'
                . '<span class="builder__choice-copy"><span class="builder__choice-label">' . $h($label) . '</span>'
                . '<span class="builder__choice-hint">' . $h($meta['hint']) . '</span></span>'
                . '</label>';
        }
        $screensHtml .= '</div>';

        $postedGroups = $_POST['groups'] ?? null;
        $selectedGroups = is_array($postedGroups) ? array_map('strval', $postedGroups) : [];
        $groupRows = Database::select('groups', ['group_id', 'group_name'], '', [], 'group_name ASC');
        $groupsHtml = '<div class="builder__choices builder__choices--compact">';
        $selectedGroupNames = [];
        foreach ($groupRows as $group) {
            $gid = (string) $group['group_id'];
            $isAdmin = strtolower((string) $group['group_name']) === 'administrator' || (int) $group['group_id'] === 3;
            $on = ($selectedGroups === [] && $isAdmin) || in_array($gid, $selectedGroups, true);
            if ($on) {
                $selectedGroupNames[] = (string) $group['group_name'];
            }
            $groupsHtml .= '<label class="builder__choice' . ($on ? ' is-selected' : '') . '">'
                . '<input type="checkbox" name="groups[]" value="' . $h($gid) . '"' . ($on ? ' checked' : '') . '>'
                . '<span class="builder__choice-label">' . $h((string) $group['group_name']) . '</span>'
                . '</label>';
        }
        $groupsHtml .= '</div>';

        $postedLevels = $_POST['perm_levels'] ?? null;
        $selectedLevels = is_array($postedLevels) ? $postedLevels : ['use', 'settings'];
        $levelsHtml = '<div class="builder__choices">';
        $selectedLevelLabels = [];
        foreach ($levelMeta as $level => $meta) {
            $on = in_array($level, $selectedLevels, true);
            if ($on) {
                $selectedLevelLabels[] = $meta['label'];
            }
            $levelsHtml .= '<label class="builder__choice' . ($on ? ' is-selected' : '') . '">'
                . '<input type="checkbox" name="perm_levels[]" value="' . $h($level) . '"' . ($on ? ' checked' : '') . '>'
                . '<span class="builder__choice-copy"><span class="builder__choice-label">' . $h($meta['label']) . '</span>'
                . '<span class="builder__choice-hint">' . $h($meta['hint']) . '</span></span>'
                . '</label>';
        }
        $levelsHtml .= '</div>';

        $iconsHtml = '<div class="builder__icons" role="radiogroup" aria-labelledby="builder-icon-label">';
        foreach (Capabilities::icons() as $iconId) {
            $active = $iconId === $icon ? ' is-selected' : '';
            $label = Capabilities::iconLabel($iconId);
            $iconsHtml .= '<label class="builder__icon' . $active . '" title="' . $h($label) . '">'
                . '<input type="radio" name="icon" value="' . $h($iconId) . '"' . ($iconId === $icon ? ' checked' : '') . '>'
                . '<span class="builder__icon-mark" aria-hidden="true"><svg class="icon"><use href="' . $sprite . '#' . $h($iconId) . '"></use></svg></span>'
                . '<span class="builder__icon-name">' . $h($label) . '</span>'
                . '</label>';
        }
        $iconsHtml .= '</div>';

        $previewName = trim($nameRaw) !== '' ? trim($nameRaw) : 'New application';
        $previewSlug = trim($slugRaw) !== '' ? ApplicationBuilder::slugify($slugRaw, $nameRaw) : ApplicationBuilder::slugify('', $nameRaw);
        if ($previewSlug === '') {
            $previewSlug = 'complaints';
        }
        $previewHint = trim($hintRaw) !== '' ? trim($hintRaw) : (string) $blueprints[$blueprint]['hint'];
        $previewType = trim($recordRaw) !== ''
            ? trim($recordRaw)
            : ((string) ($blueprints[$blueprint]['record'] ?? '') !== ''
                ? (string) $blueprints[$blueprint]['record']
                : (trim($nameRaw) !== '' ? trim($nameRaw) . ' record' : 'Record'));
        $previewFields = '';
        foreach ($clientBlueprints[$blueprint]['fields'] as $field) {
            $values = $field['values'] === [] ? '' : '<span class="builder__field-values">' . $h(implode(', ', $field['values'])) . '</span>';
            $previewFields .= '<li><span class="builder__field-name">' . $h($field['name']) . '</span>' . $values . '</li>';
        }
        if ($previewFields === '') {
            $previewFields = '<li>You will add fields after the application exists.</li>';
        }
        $previewTabs = '';
        foreach (ScreenCatalog::builderScreens() as $cap => $label) {
            if (in_array($cap, $selectedScreens, true)) {
                $previewTabs .= '<span class="builder__tab">' . $h($label) . '</span>';
            }
        }
        if ($previewTabs === '') {
            $previewTabs = '<span class="builder__tab">Work</span>';
        }
        $accessLine = ($selectedGroupNames === [] ? 'Administrators' : implode(', ', $selectedGroupNames))
            . ' · '
            . ($selectedLevelLabels === [] ? 'Open and settings' : implode(', ', $selectedLevelLabels));
        $rule = $clientBlueprints[$blueprint]['rule'];
        $ruleRow = $rule === ''
            ? ''
            : '<div id="builder-preview-rule-row"><dt>Rule</dt><dd id="builder-preview-rule">' . $h($rule) . '</dd></div>';

        $payload = $h((string) json_encode([
            'sprite' => \Adlexone\Theme\Theme::spriteHref(),
            'blueprints' => $clientBlueprints,
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));

        $errorHtml = $error !== ''
            ? '<p class="builder__error" role="alert">' . $h($error) . '</p>'
            : '';
        $artHtml = $art !== ''
            ? '<img class="builder__art" src="' . $h($art) . '" alt="" width="104" height="78">'
            : '';

        $html = '<div class="builder" data-builder="' . $payload . '">'
            . '<header class="builder__intro">'
            . $artHtml
            . '<div><p class="builder__title">Create an application</p>'
            . '<p class="builder__lead">Choose a blueprint. The screens and fields can be changed later.</p></div>'
            . '</header>'
            . $errorHtml
            . '<form id="builder-form" class="builder__form builder__panel" method="post" action="' . $h(BUILDER_URL . '&option=create') . '">'
            . Csrf::field()
            . '<section class="builder__section">'
            . '<h2 class="builder__heading">Application</h2>'
            . '<div class="builder__identity">'
            . '<div class="field"><label class="label" for="builder-name">Name</label>'
            . '<input class="text builder__name" id="builder-name" name="name" value="' . $h($nameRaw) . '" required autocomplete="off" placeholder="Complaints"></div>'
            . '<div class="field"><label class="label" for="builder-slug">Link</label>'
            . '<div class="builder__link"><span class="builder__link-prefix">?application=</span>'
            . '<input class="text" id="builder-slug" name="slug" value="' . $h($slugRaw) . '" autocomplete="off" placeholder="complaints" spellcheck="false"></div>'
            . '<p class="form-help">This follows the name until you change it.</p></div>'
            . '<div class="field field--wide"><label class="label" for="builder-hint">Description</label>'
            . '<input class="text" id="builder-hint" name="hint" value="' . $h($hintRaw) . '" autocomplete="off" placeholder="What this application is for"></div>'
            . '<div class="field field--wide"><span class="label" id="builder-icon-label">Icon</span>' . $iconsHtml . '</div>'
            . '</div></section>'
            . '<section class="builder__section">'
            . '<h2 class="builder__heading">Blueprint</h2>'
            . '<p class="form-help">A blueprint sets the fields, the screens, and one rule. The screens can still be changed.</p>'
            . $blueprintHtml
            . '</section>'
            . '<section class="builder__section">'
            . '<h2 class="builder__heading">Screens</h2>'
            . '<p class="form-help">These become the tabs along the top of the application.</p>'
            . $screensHtml
            . '</section>'
            . '<section class="builder__section">'
            . '<h2 class="builder__heading">Record</h2>'
            . '<div class="field"><label class="label" for="builder-type">Record type name</label>'
            . '<input class="text" id="builder-type" name="record_type_name" value="' . $h($recordRaw) . '" autocomplete="off" placeholder="' . $h((string) ($blueprints[$blueprint]['record'] ?? 'Job')) . '">'
            . '<p class="form-help">Leave this blank to use the blueprint name.</p></div>'
            . '</section>'
            . '<section class="builder__section">'
            . '<h2 class="builder__heading">Who can open it</h2>'
            . '<p class="form-help">Change this later under Security.</p>'
            . $groupsHtml
            . '<p class="builder__subhead">What they can do</p>'
            . $levelsHtml
            . '</section>'
            . '<div class="form-actions">'
            . Form::button('submit', 'submit_button', 'Create this application')
            . '</div></form>'
            . '<details class="builder__preview builder__panel" open>'
            . '<summary class="builder__kicker">This application</summary>'
            . '<div class="builder__app">'
            . '<span class="builder__app-icon" id="builder-preview-icon"><svg class="icon" aria-hidden="true"><use href="' . $sprite . '#' . $h($icon) . '"></use></svg></span>'
            . '<div class="builder__app-copy"><p class="builder__app-name" id="builder-preview-name">' . $h($previewName) . '</p>'
            . '<p class="builder__app-hint" id="builder-preview-hint">' . $h($previewHint) . '</p></div></div>'
            . '<p class="builder__app-url" id="builder-preview-url">?application=' . $h($previewSlug) . '</p>'
            . '<div class="builder__tabs" id="builder-preview-tabs">' . $previewTabs . '</div>'
            . '<dl class="builder__facts">'
            . '<div><dt>Record</dt><dd id="builder-preview-type">' . $h($previewType) . '</dd></div>'
            . '<div><dt>Fields</dt><dd><ul class="builder__fields" id="builder-preview-fields">' . $previewFields . '</ul></dd></div>'
            . $ruleRow
            . '<div><dt>Who can open it</dt><dd id="builder-preview-access">' . $h($accessLine) . '</dd></div>'
            . '</dl>'
            . '</details>'
            . '<details class="builder__import builder__panel">'
            . '<summary>Import a JSON package</summary>'
            . '<p class="form-help">Bring in an application that was saved as a solution package.</p>'
            . '<form method="post" action="' . $h(BUILDER_URL . '&option=import') . '" enctype="multipart/form-data">'
            . Csrf::field()
            . '<div class="builder__identity">'
            . '<div class="field"><label class="label" for="package">Package file</label>'
            . '<input class="text" type="file" id="package" name="package" accept="application/json,.json" required></div>'
            . '<div class="field"><label class="label" for="import-slug">Address override</label>'
            . '<input class="text" id="import-slug" name="slug" autocomplete="off" spellcheck="false" placeholder="Optional"></div>'
            . '</div><div class="form-actions">'
            . Form::button('submit', 'submit_button', 'Import')
            . '</div></form></details></div>';
        Page::current()->addScript('builder');

        Page::current()->setBody($html);
    }

    public static function createBusinessApplication(): void
    {
        $screens = $_POST['screens'] ?? [];
        if (!is_array($screens)) {
            $screens = [];
        }
        $groups = $_POST['groups'] ?? [];
        if (!is_array($groups)) {
            $groups = [];
        }
        $levels = $_POST['perm_levels'] ?? [];
        if (!is_array($levels)) {
            $levels = [];
        }
        try {
            $created = ApplicationBuilder::create(
                (string) ($_POST['name'] ?? ''),
                (string) ($_POST['slug'] ?? ''),
                (string) ($_POST['hint'] ?? ''),
                (string) ($_POST['icon'] ?? 'ic-grid'),
                (string) ($_POST['blueprint'] ?? ''),
                (string) ($_POST['record_type_name'] ?? ''),
                array_map('strval', $screens),
                array_map('intval', $groups),
                array_map('strval', $levels)
            );
        } catch (\InvalidArgumentException $e) {
            self::showBuilderForm($e->getMessage());
            return;
        } catch (\Throwable) {
            self::showBuilderForm('Could not create the application. Check the name and try again.');
            return;
        }

        header('Location: ' . Router::applicationUrl($created['slug']));
        exit;
    }

    public static function importSolutionPackage(): void
    {
        $tmp = (string) ($_FILES['package']['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            self::showBuilderForm('Choose a JSON solution package to import.');
            return;
        }
        $raw = file_get_contents($tmp);
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            self::showBuilderForm('The package is not valid JSON.');
            return;
        }
        try {
            $created = ApplicationPackage::import($data, (string) ($_POST['slug'] ?? ''));
        } catch (\InvalidArgumentException $e) {
            self::showBuilderForm($e->getMessage());
            return;
        } catch (\Throwable) {
            self::showBuilderForm('Could not import the package.');
            return;
        }
        header('Location: ' . Router::applicationUrl($created['slug']));
        exit;
    }
}
