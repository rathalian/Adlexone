<?php
namespace Adlexone\Screen;

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Rules;
use Adlexone\Auth\Permission;
use Adlexone\Application\ApplicationSettings;
use Adlexone\Http\Page;
use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;

/**
 * Screen formerly loaded from legacy/.
 */
final class RulesScreen
{
    public static function open(): void
    {
        define('MAN_BASE_URL', '?manage=rules');
        define('RULES_HOME_URL', '?manage=rules');



        Access::require(Permission::ADMIN_ACTIONS);

        $slug = trim((string) ($_GET['app'] ?? $_POST['app'] ?? ''));
        $option = (string) ($_GET['option'] ?? '');

        switch ($option) {
        	case 'new_rule':
        		if ($slug === '') {
        			self::showAppPicker('new_rule');
        			break;
        		}
        		ApplicationSettingsScreen::showRuleForm($slug, null);
        		break;
        	case 'edit_rule':
        		ApplicationSettingsScreen::showRuleForm($slug, (int) ($_GET['rule_id'] ?? 0));
        		break;
        	case 'add_rule':
        		ApplicationSettingsScreen::saveRule($slug, 0);
        		break;
        	case 'update_rule':
        		ApplicationSettingsScreen::saveRule($slug, (int) ($_POST['rule_id'] ?? 0));
        		break;
        	case 'delete_rule':
        		ApplicationSettingsScreen::deleteRule($slug);
        		break;
        	case 'toggle_rule':
        		ApplicationSettingsScreen::toggleRule($slug);
        		break;
        	case 'duplicate_rule':
        		ApplicationSettingsScreen::duplicateRule($slug);
        		break;
        	case 'move_rule':
        		ApplicationSettingsScreen::moveRule($slug);
        		break;
        	case 'try_rule':
        		ApplicationSettingsScreen::tryRule($slug, false);
        		break;
        	case 'run_rule':
        		ApplicationSettingsScreen::tryRule($slug, true);
        		break;
        	default:
        		self::showList();
        		break;
        }
    }
    public static function showAppPicker(string $option): void
    {
    	$values = [];
    	$labels = [];
    	foreach (ApplicationStore::all() as $app) {
    		$values[] = (string) $app['slug'];
    		$labels[] = (string) $app['name'];
    	}
    	if ($values === []) {
    		View::respond('Create an application before adding a rule.');
    		return;
    	}
    	$html = Form::form(
    		'Add a rule',
    		MAN_BASE_URL . '&option=' . $option,
    		[
    			'Application' => Form::select('app', $values, $labels, $values[0]),
    		],
    		[Form::button('submit', 'submit_button', 'Continue')]
    	);
    	Page::current()->setBody($html);
    }

    public static function showList(): void
    {
    	$groups = [];
    	foreach (ApplicationStore::all() as $app) {
    		$slug = (string) $app['slug'];
    		$applicationId = (int) $app['application_id'];
    		$typeIds = ApplicationSettings::allowedItemTypeIds($slug);
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

    	$html = Blocks::recordList([
    		'column' => 'Rule',
    		'columns' => [
    			['key' => 'description', 'label' => 'What it does', 'wrap' => true],
    		],
    		'searchLabel' => 'Search',
    		'primary' => [
    			'href' => MAN_BASE_URL . '&option=new_rule',
    			'label' => 'Add a rule',
    		],
    		'empty' => 'No rules yet. Add a rule, or create an application from a blueprint.',
    		'emptyIllustration' => 'empty-rules',
    		'emptyTitle' => 'No rules yet',
    		'emptyCopy' => 'A rule runs when a record is created, updated, or gets a note.',
    		'noMatch' => 'No rules match.',
    		'groups' => $groups,
    	]);
    	Page::current()->setBody(Blocks::vertical([[
    		'title' => 'Rules',
    		'html' => $html . '<p class="record-list__meta">Each application runs its own rules when a record is created, updated, logged, or gets a file.</p>',
    	]]));
    }
}
