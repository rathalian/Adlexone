	<?php
/**
 * Adlexone FlowIQ License Agreement 1.0
 * 
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where 
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in. 
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions. 
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */

use Adlexone\support\Database;
use Adlexone\support\RenderViews;
use Adlexone\support\RenderNavigation;

/**
 * Workflow links sit in the top navigation card. There is no left sidebar.
 */
RenderNavigation::applySectionNav('Workflow', RenderNavigation::workflowURLs());

/**
 * Controller specific constants
 */
define('ACT_BASE_URL', 'index.php?manage=workflow');
/**
 * Shows secured action options
 */
function showSettingsOptions (): void
{
	$html = RenderViews::outputIfAllowed(RenderViews::buildURL(ACT_BASE_URL . '&option=show_action_packages', TXT_255), \Adlexone\Auth\Permission::ADMIN_ACTIONS);
	$html .= RenderViews::outputIfAllowed('<br>' . RenderViews::buildURL(ACT_BASE_URL . '&option=show_defined_actions', TXT_411), \Adlexone\Auth\Permission::ADMIN_ACTIONS);

	define('BODY_CONTENT', RenderViews::buildVerticalCards([['title' => TXT_128, 'html' => $html]]));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * Creates a form with all available action packages.
 *
 * - Collects `*.actions.php` files from the `actions` directory.
 * - Sorts packages naturally (case-insensitive) and builds label/value arrays.
 * - Uses RenderViews::buildForm to produce a modern, accessible div-based form.
 * - Defines BODY_CONTENT with the returned HTML and includes the main page.
 *
 * @return void
 */
function showactionPackages(): void
{
    // Collect action package files
    $files = glob('actions/*.actions.php') ?: [];

    // Extract package names and sort naturally (case-insensitive)
    $packages = array_map(fn(string $f): string => basename($f, '.actions.php'), $files);
    if (!empty($packages)) {
        sort($packages, SORT_NATURAL | SORT_FLAG_CASE);
    }

    // Build aligned value/display arrays
    $actionPackageArray = $packages;
    $displayNameArray = array_map(fn(string $p): string => str_replace('_', ' ', $p), $packages);

    // If no packages found, provide a single empty option with a friendly label
    if (empty($actionPackageArray)) {
        $actionPackageArray = [0 => ''];
        $displayNameArray   = [0 => TXT_366];
    }

    // Build fields and buttons for the form
    $fields = [
        TXT_250 => RenderViews::buildSelectDropdown('action_package', $actionPackageArray, $displayNameArray, '')
    ];

    $buttons = [
        RenderViews::buildFormButton('submit', 'submit_button', TXT_69)
    ];

    // Render the form using the modern helper and place it into the page
    $html = RenderViews::buildForm(TXT_253, ACT_BASE_URL . '&option=show_package_action_list', $fields, $buttons);

    define('BODY_CONTENT', $html);
    RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showPackageactionList($actionPackage): void
{
	require_once 'actions/' . $actionPackage . '.actions.php';
	$names = (isset($actionName) && is_array($actionName)) ? $actionName : [];
	$descriptions = (isset($actionDescription) && is_array($actionDescription)) ? $actionDescription : [];
	$rows = [];
	foreach ($names as $descriptorName => $value) {
	$rows[] = [
		'name' => (string)$value,
		'href' => ACT_BASE_URL . '&option=new_action&action_package=' . rawurlencode((string)$actionPackage) . '&descriptor_name=' . rawurlencode((string)$descriptorName),
		'cells' => [
			'description' => (string)($descriptions[$descriptorName] ?? ''),
		],
	];
	}
	$html = RenderViews::buildRecordList([
		'column' => TXT_299,
		'columns' => [
			['key' => 'description', 'label' => TXT_153, 'wrap' => true],
		],
		'searchLabel' => TXT_3,
		'empty' => TXT_412,
		'noMatch' => TXT_688,
		'groups' => [['rows' => $rows]],
	]);
	$title = TXT_253 . ' - ' . str_replace('_', ' ', (string)$actionPackage);
	define('BODY_CONTENT', RenderViews::buildVerticalCards([['title' => $title, 'html' => $html]]));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
/**
 * Friendly name and description for one descriptor inside an action package file.
 *
 * @return array{name: string, description: string}
 */
function definedActionDescriptor(string $packageFile, string $function): array
{
	static $cache = [];
	if (!isset($cache[$packageFile])) {
		$actionName = [];
		$actionDescription = [];
		$path = 'actions/' . basename($packageFile);
		if (is_file($path)) {
			require_once $path;
		}
		$cache[$packageFile] = [
			'names' => is_array($actionName) ? $actionName : [],
			'descriptions' => is_array($actionDescription) ? $actionDescription : [],
		];
	}

	$name = (string)($cache[$packageFile]['names'][$function] ?? '');
	$description = (string)($cache[$packageFile]['descriptions'][$function] ?? '');
	if ($name === '') {
		$name = trim((string)preg_replace('/(?<!^)([A-Z])/', ' $1', $function));
	}

	return ['name' => $name, 'description' => $description];
}

function showDefinedactions ()
{
	$columnArray = array('action_id', 'action_name', 'package_file', 'package_function');
	$condition = 'ORDER BY package_file ASC, action_name ASC';
	$result = Database::select('action_definitions', $columnArray, $condition);
	$canDelete = \Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_ACTIONS);

	$groups = [];
	if ($result && count($result) > 0) {
		foreach ($result as $row) {
			$groups[(string)$row['package_file']][] = $row;
		}
	}

	$html = renderDefinedActionsList($groups, $canDelete);
	define('BODY_CONTENT', RenderViews::buildVerticalCards([['title' => TXT_83, 'html' => $html]]));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}

/**
 * Searchable list of defined actions, grouped by package.
 *
 * @param array<string, array<int, array<string, mixed>>> $groups
 */
function renderDefinedActionsList(array $groups, bool $canDelete): string
{
	$listGroups = [];
	foreach ($groups as $packageFile => $rows) {
		usort($rows, static fn(array $a, array $b): int => strcasecmp((string)$a['action_name'], (string)$b['action_name']));
		$packageLabel = str_replace('_', ' ', str_replace('.actions.php', '', $packageFile));
		$listRows = [];
		foreach ($rows as $row) {
			$descriptor = definedActionDescriptor($packageFile, (string)$row['package_function']);
			$actionName = (string)$row['action_name'];
			$id = rawurlencode((string)$row['action_id']);
			$record = [
				'name' => $actionName,
				'href' => ACT_BASE_URL . '&option=defined_action&action_id=' . $id,
				'meta' => $descriptor['name'],
				'cells' => [
					'description' => $descriptor['description'],
				],
				'search' => $descriptor['description'],
			];
			if ($canDelete) {
				$record['actions'] = [[
					'href' => ACT_BASE_URL . '&option=delete_action&action_id=' . $id,
					'label' => TXT_315,
					'tone' => 'danger',
					'confirm' => $actionName . "\n" . TXT_400,
				]];
			}
			$listRows[] = $record;
		}
		$listGroups[] = ['label' => $packageLabel, 'rows' => $listRows];
	}

	return RenderViews::buildRecordList([
		'column' => TXT_299,
		'columns' => [
			['key' => 'description', 'label' => TXT_153, 'wrap' => true],
		],
		'searchLabel' => TXT_3,
		'primary' => [
			'href' => ACT_BASE_URL . '&option=show_action_packages',
			'label' => TXT_255,
		],
		'empty' => TXT_412,
		'noMatch' => TXT_688,
		'groups' => $listGroups,
	]);
}
function showDefinedaction ($actionID)
{
	// Get action details from database and open the update function
	$columnArray = array('package_function', 'action_name', 'package_file');
	$condition = "WHERE action_id = '$actionID'";
	$row = Database::first('action_definitions', $columnArray, $condition);
	$functionName = 'showSetup' . $row['package_function'];
	require_once  'actions/' . $row['package_file'];
	// Run the function to show the update page for actions
	$functionName($actionID);
}
function deleteAction($actionID)
{
	$sql = "DELETE FROM action_definitions WHERE action_id = '$actionID'";
	Database::run($sql);
	showDefinedactions();
}
/**
 * Logic to render the appropriate template or call wrapper functions
 * Option is captured from the value selected via a hyperlink
 */
switch (@$_GET['option']) {
	case 'show_package_action_list' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		showPackageactionList($_POST['action_package']);
		break;
	case 'show_action_packages' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		showactionPackages();
		break;
	case 'new_action' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		require_once  'actions/' . $_GET['action_package'] . '.actions.php';
		$functionName = 'showSetup' . $_GET['descriptor_name'];
		$functionName(@$_GET['action_id']);
		break;
	case 'add_action' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		require_once  'actions/' . $_GET['action_package'] . '.actions.php';
		$functionName = 'addUpdate' . $_GET['descriptor_name'];
		$functionName('', true);
		break;
	case 'show_defined_actions' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		showDefinedactions();
		break;
	case 'defined_action' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		showDefinedaction($_GET['action_id']);
		break;
	case 'update_action' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		require_once  'actions/' . $_GET['action_package'] . '.actions.php';
		$functionName = 'addUpdate' . $_GET['descriptor_name'];
		$functionName($_GET['action_id'], false);
		break;
	case 'delete_action' :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		deleteAction($_GET['action_id']);
		break;
	default :
		RenderViews::terminateUnlessAllowed(\Adlexone\Auth\Permission::ADMIN_ACTIONS);
		showSettingsOptions();
		break;
}
?>