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
    $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

    $nameRaw = (string) ($_POST['name'] ?? '');
    $slugRaw = (string) ($_POST['slug'] ?? '');
    $hintRaw = (string) ($_POST['hint'] ?? '');
    $recordRaw = (string) ($_POST['record_type_name'] ?? '');
    $icon = (string) ($_POST['icon'] ?? 'ic-launch');
    $blueprint = (string) ($_POST['blueprint'] ?? ApplicationBuilder::BLUEPRINT_CASE);
    $blueprints = ApplicationBuilder::blueprints();
    if (!isset($blueprints[$blueprint])) {
        $blueprint = ApplicationBuilder::BLUEPRINT_CASE;
    }
    if (!in_array($icon, Capabilities::icons(), true)) {
        $icon = 'ic-launch';
    }

    $postedScreens = $_POST['screens'] ?? null;
    $selectedScreens = is_array($postedScreens) ? $postedScreens : $blueprints[$blueprint]['screens'];

    $sprite = $h(\Adlexone\Theme\Theme::spriteHref());
    $art = \Adlexone\Theme\Theme::illustrationHref('empty-builder');

    $iconLabels = [
        'ic-launch' => 'Launch',
        'ic-servicecentre' => 'Service',
        'ic-knowledgebase' => 'Knowledge',
        'ic-search' => 'Search',
        'ic-time' => 'Time',
        'ic-announcements' => 'News',
        'ic-create-ticket' => 'New',
        'ic-itemtype-add' => 'Add',
        'ic-quick-search' => 'Quick find',
        'ic-my-ticket-searches' => 'My work',
        'ic-new-article' => 'Article',
        'ic-article-search' => 'Find article',
        'ic-settings' => 'Settings',
        'ic-item-mgmt' => 'Records',
        'ic-manage-fields' => 'Fields',
    ];

    $screenMeta = [
        Library::WORK => ['hint' => 'Records in progress', 'icon' => 'ic-search'],
        Library::CREATE => ['hint' => 'Add a record', 'icon' => 'ic-itemtype-add'],
        Library::SEARCH_LIST => ['hint' => 'Find records', 'icon' => 'ic-my-ticket-searches'],
        Library::ANNOUNCEMENTS => ['hint' => 'Notes for the team', 'icon' => 'ic-announcements'],
        Library::SETTINGS => ['hint' => 'How the app is set up', 'icon' => 'ic-settings'],
    ];
    $levelMeta = [
        'use' => ['label' => 'Use application', 'hint' => 'Work with its records'],
        'announce' => ['label' => 'Announcements', 'hint' => 'Post notes for the team'],
        'settings' => ['label' => 'Settings', 'hint' => 'Change how the app works'],
    ];
    $ruleCopy = [
        ApplicationBuilder::BLUEPRINT_CASE => 'Emails the owner when priority becomes High.',
        ApplicationBuilder::BLUEPRINT_HELPDESK => 'Emails the owner when priority becomes High.',
        ApplicationBuilder::BLUEPRINT_CRM => 'Adds a note when stage becomes Customer.',
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
            'screens' => array_values($meta['screens']),
            'fields' => $clientFields,
            'rule' => $ruleCopy[$key] ?? '',
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
    foreach (Library::builderScreens() as $cap => $label) {
        $meta = $screenMeta[$cap] ?? ['hint' => '', 'icon' => 'ic-launch'];
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
    $selectedLevels = is_array($postedLevels) ? $postedLevels : ['use', 'announce', 'settings'];
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
        $label = $iconLabels[$iconId] ?? $iconId;
        $iconsHtml .= '<label class="builder__icon' . $active . '">'
            . '<input type="radio" name="icon" value="' . $h($iconId) . '"' . ($iconId === $icon ? ' checked' : '') . '>'
            . '<span class="builder__icon-mark" aria-hidden="true"><svg class="icon"><use href="' . $sprite . '#' . $h($iconId) . '"></use></svg></span>'
            . '<span class="builder__icon-name">' . $h($label) . '</span>'
            . '</label>';
    }
    $iconsHtml .= '</div>';

    $previewName = trim($nameRaw) !== '' ? trim($nameRaw) : 'Your application';
    $previewSlug = trim($slugRaw) !== '' ? ApplicationBuilder::slugify($slugRaw, $nameRaw) : ApplicationBuilder::slugify('', $nameRaw);
    if ($previewSlug === '') {
        $previewSlug = 'your-app';
    }
    $previewHint = trim($hintRaw) !== '' ? trim($hintRaw) : (string) $blueprints[$blueprint]['hint'];
    $previewType = trim($recordRaw) !== '' ? trim($recordRaw) : (trim($nameRaw) !== '' ? trim($nameRaw) . ' record' : 'Record');
    $previewFields = '';
    foreach ($clientBlueprints[$blueprint]['fields'] as $field) {
        $values = $field['values'] === [] ? '' : '<span class="builder__field-values">' . $h(implode(', ', $field['values'])) . '</span>';
        $previewFields .= '<li><span class="builder__field-name">' . $h($field['name']) . '</span>' . $values . '</li>';
    }
    if ($previewFields === '') {
        $previewFields = '<li>You will add fields after the application exists.</li>';
    }
    $previewTabs = '';
    foreach (Library::builderScreens() as $cap => $label) {
        if (in_array($cap, $selectedScreens, true)) {
            $previewTabs .= '<span class="builder__tab">' . $h($label) . '</span>';
        }
    }
    if ($previewTabs === '') {
        $previewTabs = '<span class="builder__tab">Work</span>';
    }
    $accessLine = ($selectedGroupNames === [] ? 'Administrators' : implode(', ', $selectedGroupNames))
        . ' · '
        . ($selectedLevelLabels === [] ? 'Use, announcements, and settings' : implode(', ', $selectedLevelLabels));
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
        . '<div><p class="builder__title">Create a business application</p>'
        . '<p class="builder__lead">Name it, choose a starting shape, and decide who can open it. Screens, fields, and access can be changed later.</p></div>'
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
        . '<div class="builder__link"><span class="builder__link-prefix">application/</span>'
        . '<input class="text" id="builder-slug" name="slug" value="' . $h($slugRaw) . '" autocomplete="off" placeholder="complaints" spellcheck="false"></div>'
        . '<p class="form-help">Follows the name until you change it.</p></div>'
        . '<div class="field field--wide"><label class="label" for="builder-hint">Description</label>'
        . '<input class="text" id="builder-hint" name="hint" value="' . $h($hintRaw) . '" autocomplete="off" placeholder="What this application is for"></div>'
        . '<div class="field field--wide"><span class="label" id="builder-icon-label">Icon</span>' . $iconsHtml . '</div>'
        . '</div></section>'
        . '<section class="builder__section">'
        . '<h2 class="builder__heading">Blueprint</h2>'
        . '<p class="form-help">A starting shape. Choosing one sets the screens and fields. You can still adjust the screens.</p>'
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
        . '<input class="text" id="builder-type" name="record_type_name" value="' . $h($recordRaw) . '" autocomplete="off" placeholder="Complaint">'
        . '<p class="form-help">Leave this blank and it becomes the application name plus “record”.</p></div>'
        . '</section>'
        . '<section class="builder__section">'
        . '<h2 class="builder__heading">Who can open it</h2>'
        . '<p class="form-help">Change this later under Security.</p>'
        . $groupsHtml
        . '<p class="builder__subhead">What they can do</p>'
        . $levelsHtml
        . '</section>'
        . '<div class="form-actions">'
        . RenderViews::buildFormButton('submit', 'submit_button', 'Create application')
        . '<a class="btn" href="index.php?manage=applications">Open Applications</a>'
        . '</div></form>'
        . '<aside class="builder__preview builder__panel" aria-live="polite">'
        . '<p class="builder__kicker">This application</p>'
        . '<div class="builder__app">'
        . '<span class="builder__app-icon" id="builder-preview-icon"><svg class="icon" aria-hidden="true"><use href="' . $sprite . '#' . $h($icon) . '"></use></svg></span>'
        . '<div class="builder__app-copy"><p class="builder__app-name" id="builder-preview-name">' . $h($previewName) . '</p>'
        . '<p class="builder__app-hint" id="builder-preview-hint">' . $h($previewHint) . '</p></div></div>'
        . '<p class="builder__app-url" id="builder-preview-url">index.php?application=' . $h($previewSlug) . '</p>'
        . '<div class="builder__tabs" id="builder-preview-tabs">' . $previewTabs . '</div>'
        . '<dl class="builder__facts">'
        . '<div><dt>Record</dt><dd id="builder-preview-type">' . $h($previewType) . '</dd></div>'
        . '<div><dt>Fields</dt><dd><ul class="builder__fields" id="builder-preview-fields">' . $previewFields . '</ul></dd></div>'
        . $ruleRow
        . '<div><dt>Who can open it</dt><dd id="builder-preview-access">' . $h($accessLine) . '</dd></div>'
        . '</dl>'
        . '<button type="submit" form="builder-form" class="btn btn--primary builder__create">Create application</button>'
        . '</aside>'
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
        . RenderViews::buildFormButton('submit', 'submit_button', 'Import')
        . '</div></form></details></div>'
        . '<script>'
        . '(function(){'
        . 'var root=document.querySelector("[data-builder]");'
        . 'if(!root)return;'
        . 'var data=JSON.parse(root.getAttribute("data-builder")||"{}");'
        . 'var form=document.getElementById("builder-form");'
        . 'if(!form)return;'
        . 'var nameEl=document.getElementById("builder-name");'
        . 'var slugEl=document.getElementById("builder-slug");'
        . 'var hintEl=document.getElementById("builder-hint");'
        . 'var typeEl=document.getElementById("builder-type");'
        . 'var slugDirty=slugEl&&slugEl.value.trim()!=="";'
        . 'var typeDirty=typeEl&&typeEl.value.trim()!=="";'
        . 'function slugify(v){return String(v||"").toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");}'
        . 'function paint(sel){root.querySelectorAll(sel).forEach(function(el){var input=el.querySelector("input");el.classList.toggle("is-selected",!!(input&&input.checked));});}'
        . 'function chosenBlueprint(){var input=form.querySelector("input[name=blueprint]:checked");return input?input.value:"case";}'
        . 'function text(id,value){var el=document.getElementById(id);if(el)el.textContent=value;}'
        . 'function render(){'
        . 'paint(".builder__blueprint");paint(".builder__icon");paint(".builder__choice");'
        . 'var bp=data.blueprints&&data.blueprints[chosenBlueprint()]||{hint:"",screens:[],fields:[],rule:""};'
        . 'var name=(nameEl&&nameEl.value.trim())||"";'
        . 'if(typeEl&&typeEl.value.trim()===""){typeEl.placeholder=name?name+" record":"Complaint";}'
        . 'var slug=(slugEl&&slugEl.value.trim())||"";'
        . 'var hint=(hintEl&&hintEl.value.trim())||bp.hint||"";'
        . 'var type=(typeEl&&typeEl.value.trim())||(name?name+" record":"Record");'
        . 'var address=slugify(slug||name)||"your-app";'
        . 'text("builder-preview-name",name||"Your application");'
        . 'text("builder-preview-hint",hint);'
        . 'text("builder-preview-url","index.php?application="+address);'
        . 'text("builder-preview-type",type);'
        . 'var icon=form.querySelector("input[name=icon]:checked");'
        . 'var use=document.querySelector("#builder-preview-icon use");'
        . 'if(use&&icon&&data.sprite){use.setAttribute("href",data.sprite+"#"+icon.value);}'
        . 'var tabs=document.getElementById("builder-preview-tabs");'
        . 'if(tabs){tabs.textContent="";form.querySelectorAll("input[name=\\"screens[]\\"]:checked").forEach(function(input){'
        . 'var label=input.closest("label");var nameNode=label&&label.querySelector(".builder__choice-label");'
        . 'var tab=document.createElement("span");tab.className="builder__tab";tab.textContent=nameNode?nameNode.textContent:"Screen";tabs.appendChild(tab);});'
        . 'if(!tabs.childNodes.length){var fallback=document.createElement("span");fallback.className="builder__tab";fallback.textContent="Work";tabs.appendChild(fallback);}}'
        . 'var fields=document.getElementById("builder-preview-fields");'
        . 'if(fields){fields.textContent="";'
        . 'if(!bp.fields||!bp.fields.length){var empty=document.createElement("li");empty.textContent="You will add fields after the application exists.";fields.appendChild(empty);}'
        . 'else{bp.fields.forEach(function(field){var li=document.createElement("li");var strong=document.createElement("span");strong.className="builder__field-name";strong.textContent=field.name;li.appendChild(strong);'
        . 'if(field.values&&field.values.length){var vals=document.createElement("span");vals.className="builder__field-values";vals.textContent=field.values.join(", ");li.appendChild(vals);}fields.appendChild(li);});}}'
        . 'var ruleRow=document.getElementById("builder-preview-rule-row");'
        . 'if(bp.rule){if(!ruleRow){var facts=root.querySelector(".builder__facts");var access=document.getElementById("builder-preview-access");'
        . 'ruleRow=document.createElement("div");ruleRow.id="builder-preview-rule-row";ruleRow.innerHTML="<dt>Rule</dt><dd id=\\"builder-preview-rule\\"></dd>";'
        . 'if(facts&&access&&access.parentNode){facts.insertBefore(ruleRow,access.parentNode);}else if(facts){facts.appendChild(ruleRow);}}'
        . 'text("builder-preview-rule",bp.rule);ruleRow.hidden=false;}else if(ruleRow){ruleRow.hidden=true;}'
        . 'var groups=[];form.querySelectorAll("input[name=\\"groups[]\\"]:checked").forEach(function(input){var label=input.closest("label");var node=label&&label.querySelector(".builder__choice-label");groups.push(node?node.textContent:"");});'
        . 'var levels=[];form.querySelectorAll("input[name=\\"perm_levels[]\\"]:checked").forEach(function(input){var label=input.closest("label");var node=label&&label.querySelector(".builder__choice-label");levels.push(node?node.textContent:"");});'
        . 'text("builder-preview-access",(groups.filter(Boolean).join(", ")||"Administrators")+" · "+(levels.filter(Boolean).join(", ")||"Use, announcements, and settings"));'
        . '}'
        . 'if(nameEl)nameEl.addEventListener("input",function(){if(!slugDirty&&slugEl)slugEl.value=slugify(nameEl.value);render();});'
        . 'if(slugEl)slugEl.addEventListener("input",function(){slugDirty=slugEl.value.trim()!=="";if(!slugDirty&&nameEl)slugEl.value=slugify(nameEl.value);render();});'
        . 'if(hintEl)hintEl.addEventListener("input",render);'
        . 'if(typeEl)typeEl.addEventListener("input",function(){typeDirty=typeEl.value.trim()!=="";render();});'
        . 'form.addEventListener("change",function(e){if(e.target&&e.target.name==="blueprint"){var bp=data.blueprints&&data.blueprints[e.target.value];'
        . 'if(bp){form.querySelectorAll("input[name=\\"screens[]\\"]").forEach(function(input){input.checked=bp.screens.indexOf(input.value)!==-1;});}}render();});'
        . 'render();'
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
