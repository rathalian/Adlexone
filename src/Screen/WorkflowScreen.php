<?php
namespace Adlexone\Screen;

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Rules;
use Adlexone\Auth\Permission;
use Adlexone\FrameOne\AppSettings;
use Adlexone\support\RenderViews;

/**
 * Screen formerly loaded from legacy/.
 */
final class WorkflowScreen
{
    public static function open(): void
    {
        define('MAN_BASE_URL', 'index.php?manage=workflow');
        define('RULES_HOME_URL', 'index.php?manage=workflow');



        RenderViews::terminateUnlessAllowed(Permission::ADMIN_ACTIONS);

        $slug = trim((string) ($_GET['app'] ?? $_POST['app'] ?? ''));
        $option = (string) ($_GET['option'] ?? '');

        switch ($option) {
        	case 'new_rule':
        		if ($slug === '') {
        			self::showWorkflowAppPicker('new_rule');
        			break;
        		}
        		\Adlexone\Screen\AppSettingsScreen::showFrameOneRuleForm($slug, null);
        		break;
        	case 'edit_rule':
        		\Adlexone\Screen\AppSettingsScreen::showFrameOneRuleForm($slug, (int) ($_GET['rule_id'] ?? 0));
        		break;
        	case 'add_rule':
        		\Adlexone\Screen\AppSettingsScreen::saveFrameOneRule($slug, 0);
        		break;
        	case 'update_rule':
        		\Adlexone\Screen\AppSettingsScreen::saveFrameOneRule($slug, (int) ($_POST['rule_id'] ?? 0));
        		break;
        	case 'delete_rule':
        		\Adlexone\Screen\AppSettingsScreen::deleteFrameOneRule($slug);
        		break;
        	case 'toggle_rule':
        		\Adlexone\Screen\AppSettingsScreen::toggleFrameOneRule($slug);
        		break;
        	case 'duplicate_rule':
        		\Adlexone\Screen\AppSettingsScreen::duplicateFrameOneRule($slug);
        		break;
        	case 'move_rule':
        		\Adlexone\Screen\AppSettingsScreen::moveFrameOneRule($slug);
        		break;
        	case 'try_rule':
        		\Adlexone\Screen\AppSettingsScreen::showFrameOneRuleTry($slug, false);
        		break;
        	case 'run_rule':
        		\Adlexone\Screen\AppSettingsScreen::showFrameOneRuleTry($slug, true);
        		break;
        	default:
        		self::showWorkflowRules();
        		break;
        }
    }
    public static function showWorkflowAppPicker(string $option): void
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

    public static function showWorkflowRules(): void
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
}
