<?php
declare(strict_types=1);

use Adlexone\Application\ApplicationBuilder;
use Adlexone\Application\ApplicationPackage;
use Adlexone\Application\Capabilities;
use Adlexone\Auth\Access;
use Adlexone\Auth\Csrf;
use Adlexone\Auth\Permission;
use Adlexone\FrameOne\Library;
use Adlexone\Http\Router;
use Adlexone\support\Database;
use Adlexone\support\RenderNavigation;
use Adlexone\support\RenderViews;

if (!Access::canAll(Permission::ADMIN_SETTINGS, Permission::ADMIN_ITEMS)) {
    Access::deny();
}

define('BUILDER_URL', 'index.php?manage=builder');
$option = (string) ($_GET['option'] ?? '');

$section = RenderViews::buildURL(BUILDER_URL, 'Builder', 'ic-itemtype-add');
$section .= RenderViews::buildURL('index.php?manage=applications', 'Applications', 'ic-launch');
RenderNavigation::applySectionNav('Builder', $section);
if (!defined('PAGE_TITLE')) {
    define('PAGE_TITLE', 'Create a business application');
}

switch ($option) {
    case 'create':
        createBusinessApplication();
        break;
    case 'import':
        importSolutionPackage();
        break;
    default:
        showBuilderForm();
        break;
}

function showBuilderForm(string $error = ''): void
{
    $name = htmlspecialchars((string) ($_POST['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $slug = htmlspecialchars((string) ($_POST['slug'] ?? ''), ENT_QUOTES, 'UTF-8');
    $hint = htmlspecialchars((string) ($_POST['hint'] ?? ''), ENT_QUOTES, 'UTF-8');
    $recordType = htmlspecialchars((string) ($_POST['record_type_name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $icon = (string) ($_POST['icon'] ?? 'ic-launch');
    $blueprint = (string) ($_POST['blueprint'] ?? ApplicationBuilder::BLUEPRINT_CASE);
    if (!isset(ApplicationBuilder::blueprints()[$blueprint])) {
        $blueprint = ApplicationBuilder::BLUEPRINT_CASE;
    }

    $defaultScreens = ApplicationBuilder::blueprints()[$blueprint]['screens'];
    $postedScreens = $_POST['screens'] ?? null;
    $selectedScreens = is_array($postedScreens) ? $postedScreens : $defaultScreens;

    $blueprintHtml = '<div class="builder__blueprints" role="radiogroup" aria-label="Blueprint">';
    foreach (ApplicationBuilder::blueprints() as $key => $meta) {
        $checked = $key === $blueprint ? ' checked' : '';
        $active = $key === $blueprint ? ' is-selected' : '';
        $keyEsc = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $labelEsc = htmlspecialchars((string) $meta['label'], ENT_QUOTES, 'UTF-8');
        $hintEsc = htmlspecialchars((string) $meta['hint'], ENT_QUOTES, 'UTF-8');
        $blueprintHtml .= '<label class="builder__blueprint' . $active . '">'
            . '<input type="radio" name="blueprint" value="' . $keyEsc . '"' . $checked . '>'
            . '<span class="builder__blueprint-label">' . $labelEsc . '</span>'
            . '<span class="builder__blueprint-hint">' . $hintEsc . '</span>'
            . '</label>';
    }
    $blueprintHtml .= '</div>';

    $screensHtml = '<div class="builder__screens">';
    foreach (Library::builderScreens() as $cap => $label) {
        $checked = in_array($cap, $selectedScreens, true) ? ' checked' : '';
        $screensHtml .= '<label class="builder__screen">'
            . '<input type="checkbox" name="screens[]" value="' . htmlspecialchars($cap, ENT_QUOTES, 'UTF-8') . '"' . $checked . '>'
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' <span class="builder__screen-origin">FrameOne</span></span>'
            . '</label>';
    }
    $screensHtml .= '</div>';

    $postedGroups = $_POST['groups'] ?? null;
    $selectedGroups = is_array($postedGroups) ? array_map('strval', $postedGroups) : [];
    $groupsHtml = '<div class="builder__screens">';
    foreach (Database::select('groups', ['group_id', 'group_name'], '', [], 'group_name ASC') as $group) {
        $gid = (string) $group['group_id'];
        $isAdmin = strtolower((string) $group['group_name']) === 'administrator' || (int) $group['group_id'] === 3;
        $checked = ($selectedGroups === [] && $isAdmin) || in_array($gid, $selectedGroups, true) ? ' checked' : '';
        $groupsHtml .= '<label class="builder__screen">'
            . '<input type="checkbox" name="groups[]" value="' . htmlspecialchars($gid, ENT_QUOTES, 'UTF-8') . '"' . $checked . '>'
            . '<span>' . htmlspecialchars((string) $group['group_name'], ENT_QUOTES, 'UTF-8') . '</span>'
            . '</label>';
    }
    $groupsHtml .= '</div>';
    $postedLevels = $_POST['perm_levels'] ?? null;
    $selectedLevels = is_array($postedLevels) ? $postedLevels : ['use', 'announce', 'settings'];
    $levelsHtml = '<div class="builder__screens">';
    foreach (['use' => 'Use application', 'announce' => 'Manage announcements', 'settings' => 'Application settings'] as $level => $label) {
        $checked = in_array($level, $selectedLevels, true) ? ' checked' : '';
        $levelsHtml .= '<label class="builder__screen">'
            . '<input type="checkbox" name="perm_levels[]" value="' . htmlspecialchars($level, ENT_QUOTES, 'UTF-8') . '"' . $checked . '>'
            . '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>'
            . '</label>';
    }
    $levelsHtml .= '</div>';

    $iconValues = Capabilities::icons();
    $iconSelect = RenderViews::buildSelectDropdown('icon', $iconValues, $iconValues, $icon);

    $errorHtml = $error !== ''
        ? '<p class="builder__error" role="alert">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>'
        : '';

    $html = '<div class="builder">'
        . '<header class="builder__intro">'
        . '<h1 class="builder__title">Create a business application</h1>'
        . '<p class="builder__lead">FrameOne screens (Work, Create, Search, Announcements, Settings) are wired in the UI — no pack PHP files required.</p>'
        . '</header>'
        . $errorHtml
        . '<form class="builder__form form-block" method="post" action="' . htmlspecialchars(BUILDER_URL . '&option=create', ENT_QUOTES, 'UTF-8') . '">'
        . Csrf::field()
        . '<section class="builder__section">'
        . '<h2 class="builder__heading">1. Application</h2>'
        . '<div class="form-grid">'
        . '<div class="field"><label class="label" for="builder-name">Name</label>'
        . '<input class="text" id="builder-name" name="name" value="' . $name . '" required autocomplete="off" placeholder="Complaints"></div>'
        . '<div class="field"><label class="label" for="builder-slug">Slug</label>'
        . '<input class="text" id="builder-slug" name="slug" value="' . $slug . '" autocomplete="off" placeholder="complaints">'
        . '<p class="form-help">Used in URLs and app permissions (app.{slug}.use).</p></div>'
        . '<div class="field"><label class="label" for="builder-hint">Description</label>'
        . '<input class="text" id="builder-hint" name="hint" value="' . $hint . '" autocomplete="off"></div>'
        . '<div class="field"><label class="label" for="icon">Icon</label>' . $iconSelect . '</div>'
        . '</div></section>'
        . '<section class="builder__section">'
        . '<h2 class="builder__heading">2. Blueprint</h2>'
        . $blueprintHtml
        . '</section>'
        . '<section class="builder__section">'
        . '<h2 class="builder__heading">3. Screens (FrameOne library)</h2>'
        . '<p class="form-help">Pick shared screens for this app. Change later under Applications → Navigation.</p>'
        . $screensHtml
        . '</section>'
        . '<section class="builder__section">'
        . '<h2 class="builder__heading">4. Record type</h2>'
        . '<div class="form-grid">'
        . '<div class="field"><label class="label" for="builder-type">Record type name</label>'
        . '<input class="text" id="builder-type" name="record_type_name" value="' . $recordType . '" autocomplete="off" placeholder="Complaint"></div>'
        . '</div></section>'
        . '<section class="builder__section">'
        . '<h2 class="builder__heading">5. Who can use it</h2>'
        . '<p class="form-help">Groups receive app-scoped permissions. Change later under Security.</p>'
        . $groupsHtml
        . '<p class="form-help" style="margin-top:12px">Permission levels</p>'
        . $levelsHtml
        . '</section>'
        . '<div class="form-actions">'
        . RenderViews::buildFormButton('submit', 'submit_button', 'Create application')
        . '<a class="btn btn--sm" href="index.php?manage=applications">Expert setup</a>'
        . '</div></form>'
        . '<section class="builder__section" style="margin-top:28px">'
        . '<h2 class="builder__heading">Import solution package</h2>'
        . '<form class="form-block" method="post" action="' . htmlspecialchars(BUILDER_URL . '&option=import', ENT_QUOTES, 'UTF-8') . '" enctype="multipart/form-data">'
        . Csrf::field()
        . '<div class="form-grid">'
        . '<div class="field"><label class="label" for="package">JSON package</label>'
        . '<input class="text" type="file" id="package" name="package" accept="application/json,.json" required></div>'
        . '<div class="field"><label class="label" for="import-slug">Slug override (optional)</label>'
        . '<input class="text" id="import-slug" name="slug" autocomplete="off"></div>'
        . '</div><div class="form-actions">'
        . RenderViews::buildFormButton('submit', 'submit_button', 'Import')
        . '</div></form></section></div>'
        . '<script>'
        . '(function(){var g=document.querySelector(".builder__blueprints");if(!g)return;'
        . 'g.addEventListener("change",function(e){if(!e.target||e.target.name!=="blueprint")return;'
        . 'g.querySelectorAll(".builder__blueprint").forEach(function(el){el.classList.toggle("is-selected",!!el.querySelector("input:checked"));});});'
        . '})();</script>';

    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function createBusinessApplication(): void
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
            (string) ($_POST['icon'] ?? 'ic-launch'),
            (string) ($_POST['blueprint'] ?? ''),
            (string) ($_POST['record_type_name'] ?? ''),
            array_map('strval', $screens),
            array_map('intval', $groups),
            array_map('strval', $levels)
        );
    } catch (\InvalidArgumentException $e) {
        showBuilderForm($e->getMessage());
        return;
    } catch (\Throwable) {
        showBuilderForm('Could not create the application. Check the name and try again.');
        return;
    }

    header('Location: ' . Router::applicationUrl($created['slug']));
    exit;
}

function importSolutionPackage(): void
{
    $tmp = (string) ($_FILES['package']['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        showBuilderForm('Choose a JSON solution package to import.');
        return;
    }
    $raw = file_get_contents($tmp);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        showBuilderForm('The package is not valid JSON.');
        return;
    }
    try {
        $created = ApplicationPackage::import($data, (string) ($_POST['slug'] ?? ''));
    } catch (\InvalidArgumentException $e) {
        showBuilderForm($e->getMessage());
        return;
    } catch (\Throwable) {
        showBuilderForm('Could not import the package.');
        return;
    }
    header('Location: ' . Router::applicationUrl($created['slug']));
    exit;
}
