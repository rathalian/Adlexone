<?php
declare(strict_types=1);

/**
 * Checks explicit workflow fields and the inference retained for older applications.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Application\WorkflowFields;
use Adlexone\support\Database;

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inlay-workflow-' . getmypid() . '.sqlite';
@unlink($tmp);
define('DSN', 'sqlite:' . $tmp);

$failed = 0;
function workflowCheck(bool $ok, string $message): void
{
    global $failed;
    echo ($ok ? 'ok  ' : 'FAIL ') . $message . "\n";
    $failed += $ok ? 0 : 1;
}

Database::exec('CREATE TABLE applications (slug TEXT PRIMARY KEY, settings_json TEXT)');
Database::exec('CREATE TABLE custom_fields (custom_field_id INTEGER PRIMARY KEY, custom_field_name TEXT, field_type TEXT, enabled TEXT)');
Database::exec('CREATE TABLE item_type_custom_fields (item_type_id INTEGER, custom_field_id INTEGER, custom_field_order INTEGER)');
Database::exec('CREATE TABLE custom_field_menu_values (menu_value_id INTEGER PRIMARY KEY, custom_field_id INTEGER, menu_value TEXT)');

Database::exec("INSERT INTO custom_fields VALUES
    (1, 'Kind', 'menu', 'Yes'),
    (2, 'Stage', 'menu', 'Yes'),
    (3, 'Action required', 'textBox', 'Yes'),
    (4, 'Response by', 'date', 'Yes'),
    (5, 'Status', 'menu', 'Yes'),
    (6, 'Next step', 'textArea', 'Yes'),
    (7, 'Due', 'date', 'Yes')");
foreach ([1, 2, 3, 4, 5, 6, 7] as $order => $id) {
    Database::exec('INSERT INTO item_type_custom_fields VALUES (10, ' . $id . ', ' . ($order + 1) . ')');
}
Database::exec("INSERT INTO custom_field_menu_values VALUES
    (1, 1, 'Advice'), (2, 1, 'Property'),
    (3, 2, 'Queued'), (4, 2, 'Doing'), (5, 2, 'Finished'),
    (6, 5, 'Open'), (7, 5, 'Closed')");

$settings = WorkflowFields::store(
    ['default_item_type_id' => '10'],
    10,
    2,
    3,
    4,
    ['Queued', 'Doing']
);
Database::exec("INSERT INTO applications VALUES ('matters', '" . Database::escape((string) json_encode($settings)) . "')");
Database::exec("INSERT INTO applications VALUES ('legacy', '{\"default_item_type_id\":\"10\"}')");

workflowCheck(WorkflowFields::resolve('matters', 10, WorkflowFields::STATUS)['id'] === 2, 'explicit status overrides a field named Status');
workflowCheck(WorkflowFields::resolve('matters', 10, WorkflowFields::STEP)['id'] === 3, 'explicit next step may use another text field');
workflowCheck(WorkflowFields::resolve('matters', 10, WorkflowFields::DUE)['id'] === 4, 'explicit due date overrides a field named Due');
workflowCheck(
    WorkflowFields::openValues('matters', 10, WorkflowFields::resolve('matters', 10, WorkflowFields::STATUS)) === ['Queued', 'Doing'],
    'explicit open statuses are retained'
);
workflowCheck(WorkflowFields::resolve('legacy', 10, WorkflowFields::STATUS)['id'] === 5, 'legacy application still infers Status');
workflowCheck(WorkflowFields::resolve('legacy', 10, WorkflowFields::STEP)['id'] === 6, 'legacy application still infers Next step');
workflowCheck(WorkflowFields::resolve('legacy', 10, WorkflowFields::DUE)['id'] === 7, 'legacy application still prefers a due field');

@unlink($tmp);
echo $failed === 0 ? "all checks passed\n" : "{$failed} checks failed\n";
exit($failed === 0 ? 0 : 1);
