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

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Rules;
use Adlexone\Auth\Permission;
use Adlexone\FrameOne\AppSettings;
use Adlexone\support\RenderViews;

define('MAN_BASE_URL', 'index.php?manage=workflow');
define('RULES_HOME_URL', 'index.php?manage=workflow');

require_once SET_INSTALL_PATH . 'legacy/inlay_functions/frameone/settings.php';

RenderViews::terminateUnlessAllowed(Permission::ADMIN_ACTIONS);

$slug = trim((string) ($_GET['app'] ?? $_POST['app'] ?? ''));
$option = (string) ($_GET['option'] ?? '');

switch ($option) {
	case 'new_rule':
		if ($slug === '') {
			showWorkflowAppPicker('new_rule');
			break;
		}
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
	case 'toggle_rule':
		toggleFrameOneRule($slug);
		break;
	case 'duplicate_rule':
		duplicateFrameOneRule($slug);
		break;
	case 'move_rule':
		moveFrameOneRule($slug);
		break;
	case 'try_rule':
		showFrameOneRuleTry($slug, false);
		break;
	case 'run_rule':
		showFrameOneRuleTry($slug, true);
		break;
	default:
		showWorkflowRules();
		break;
}

function showWorkflowAppPicker(string $option): void
{
	$values = [];
	$labels = [];
	foreach (ApplicationStore::all() as $app) {
		$values[] = (string) $app['slug'];
		$labels[] = (string) $app['name'];
	}
	if ($values === []) {
		RenderViews::buildResponse('Create an application before adding a rule.');
		return;
	}
	$html = RenderViews::buildForm(
		'Add a rule',
		MAN_BASE_URL . '&option=' . $option,
		[
			'Application' => RenderViews::buildSelectDropdown('app', $values, $labels, $values[0]),
		],
		[RenderViews::buildFormButton('submit', 'submit_button', 'Continue')]
	);
	define('BODY_CONTENT', $html);
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}

function showWorkflowRules(): void
{
	$groups = [];
	foreach (ApplicationStore::all() as $app) {
		$slug = (string) $app['slug'];
		$applicationId = (int) $app['application_id'];
		$typeIds = AppSettings::allowedItemTypeIds($slug);
		$fields = Rules::fieldChoices($typeIds);
		$rows = [];
		foreach (Rules::forApplication($applicationId) as $rule) {
			$id = (int) $rule['id'];
			$description = Rules::summary($rule, $fields);
			if ((int) $rule['enabled'] !== 1) {
				$description = 'Off. ' . $description;
			}
			$rows[] = [
				'name' => (string) $rule['name'],
				'href' => MAN_BASE_URL . '&option=edit_rule&app=' . rawurlencode($slug) . '&rule_id=' . $id,
				'meta' => (int) $rule['enabled'] === 1 ? 'On' : 'Off',
				'cells' => ['description' => $description],
				'search' => $description,
			];
		}
		$groups[] = ['label' => (string) $app['name'], 'rows' => $rows];
	}

	$html = RenderViews::buildRecordList([
		'column' => 'Rule',
		'columns' => [
			['key' => 'description', 'label' => 'What it does', 'wrap' => true],
		],
		'searchLabel' => 'Search',
		'primary' => [
			'href' => MAN_BASE_URL . '&option=new_rule',
			'label' => 'Add a rule',
		],
		'empty' => 'No rules yet. Add one, or create an application from a helpdesk blueprint.',
		'noMatch' => 'No rules match.',
		'groups' => $groups,
	]);
	define('BODY_CONTENT', RenderViews::buildVerticalCards([[
		'title' => 'Rules',
		'html' => $html . '<p class="record-list__meta">Each application runs its own rules when a record is created, updated, logged, or gets a file.</p>',
	]]));
	RenderViews::renderThemePage('main_page_content', SET_THEME);
}
