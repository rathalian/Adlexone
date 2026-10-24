<?php
declare(strict_types=1);

/**
 * Runner checks for rules: conditions, ordered actions, group mail, and cascade.
 * Uses a temporary database so the live file stays untouched.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'inlay-rules-' . getmypid() . '.sqlite';
if (is_file($tmp)) {
    unlink($tmp);
}
define('DSN', 'sqlite:' . $tmp);

use Adlexone\Application\Rules;
use Adlexone\Data\GroupMembership;
use Adlexone\support\Database;

$failed = 0;

function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
}

$closed = ['custom_field_7' => 'Closed'];
$open = ['custom_field_7' => 'Open'];
$rule = [
    'event' => Rules::EVENT_UPDATE,
    'field_key' => 'custom_field_7',
    'operator' => Rules::OP_EQ,
    'match_value' => 'Closed',
];
check(Rules::matchesState($rule, $closed, $open), 'menu value equals Closed');
check(!Rules::matchesState($rule, $open, $open), 'Open does not equal Closed');

$rule['operator'] = Rules::OP_BECOMES;
check(Rules::matchesState($rule, $closed, $open), 'becomes Closed');
check(!Rules::matchesState($rule, $closed, $closed), 'already Closed is not a change');

$rule['operator'] = Rules::OP_FROM_TO;
$rule['match_from'] = 'Open';
check(Rules::matchesState($rule, $closed, $open), 'changes from Open to Closed');
check(!Rules::matchesState($rule, $closed, ['custom_field_7' => 'Waiting']), 'from must match the previous value');

$rule['operator'] = Rules::OP_CHANGED;
check(Rules::matchesState($rule, $closed, $open), 'field changed');
check(!Rules::matchesState($rule, $open, $open), 'unchanged field does not match');

$rule['operator'] = Rules::OP_EMPTY;
check(Rules::matchesState($rule, ['custom_field_7' => ''], $open), 'empty value');
$rule['operator'] = Rules::OP_CONTAINS;
$rule['match_value'] = 'Clos';
check(Rules::matchesState($rule, $closed, $open), 'contains text');

$groups = [
    'event' => Rules::EVENT_UPDATE,
    'field_key' => 'groups',
    'operator' => Rules::OP_CHANGED,
];
$before = ['group_security' => GroupMembership::toDelimited([1])];
$after = ['group_security' => GroupMembership::toDelimited([1, 2])];
check(Rules::matchesState($groups, $after, $before), 'group membership changed');
check(!Rules::matchesState($groups, $before, $before), 'same groups do not match');

Database::exec('CREATE TABLE items (
    item_id INTEGER PRIMARY KEY,
    create_date INTEGER,
    core_log_updated INTEGER,
    item_type_id INTEGER,
    creator_security INTEGER,
    user_security INTEGER,
    item_title TEXT
)');
Database::exec('CREATE TABLE item_field_values (
    item_id INTEGER NOT NULL,
    custom_field_id INTEGER NOT NULL,
    value TEXT NOT NULL DEFAULT "",
    PRIMARY KEY (item_id, custom_field_id)
)');
Database::exec('CREATE TABLE item_type_custom_fields (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_type_id INTEGER,
    custom_field_id INTEGER,
    custom_field_order INTEGER
)');
Database::exec('CREATE TABLE users (
    user_id INTEGER PRIMARY KEY,
    user_name TEXT,
    email TEXT
)');
Database::exec('CREATE TABLE groups (
    group_id INTEGER PRIMARY KEY,
    group_name TEXT
)');
Database::exec('CREATE TABLE user_groups (
    user_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, group_id)
)');
Database::exec('CREATE TABLE item_groups (
    item_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    PRIMARY KEY (item_id, group_id)
)');
Database::exec('CREATE TABLE core_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    item_id INTEGER,
    create_date TEXT,
    item_identifier TEXT,
    log_item_sequence INTEGER,
    log_text TEXT,
    security_id INTEGER,
    role_id INTEGER
)');
Database::exec('CREATE TABLE application_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    application_id INTEGER NOT NULL,
    item_type_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    enabled INTEGER NOT NULL DEFAULT 1,
    event TEXT NOT NULL,
    field_key TEXT NOT NULL DEFAULT "",
    operator TEXT NOT NULL DEFAULT "always",
    match_value TEXT NOT NULL DEFAULT "",
    match_from TEXT NOT NULL DEFAULT "",
    outcome TEXT NOT NULL,
    recipient TEXT NOT NULL DEFAULT "owner",
    address TEXT NOT NULL DEFAULT "",
    subject TEXT NOT NULL DEFAULT "",
    body TEXT NOT NULL DEFAULT "",
    role_id INTEGER NOT NULL DEFAULT 5,
    action_field TEXT NOT NULL DEFAULT "",
    action_value TEXT NOT NULL DEFAULT "",
    skip_actor INTEGER NOT NULL DEFAULT 1,
    min_log_role INTEGER NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0
)');
Database::exec('CREATE TABLE application_rule_steps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rule_id INTEGER NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    outcome TEXT NOT NULL,
    recipient TEXT NOT NULL DEFAULT "owner",
    address TEXT NOT NULL DEFAULT "",
    subject TEXT NOT NULL DEFAULT "",
    body TEXT NOT NULL DEFAULT "",
    role_id INTEGER NOT NULL DEFAULT 5,
    action_field TEXT NOT NULL DEFAULT "",
    action_value TEXT NOT NULL DEFAULT "",
    user_id INTEGER NOT NULL DEFAULT 0,
    group_id INTEGER NOT NULL DEFAULT 0
)');
Database::exec('CREATE TABLE application_rule_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rule_id INTEGER NOT NULL,
    item_id INTEGER NOT NULL,
    ran_at INTEGER NOT NULL,
    status TEXT NOT NULL,
    detail TEXT NOT NULL DEFAULT ""
)');

Database::insert('users', ['user_id' => 1, 'user_name' => 'Ada', 'email' => 'ada@example.com']);
Database::insert('users', ['user_id' => 2, 'user_name' => 'Bo', 'email' => 'bo@example.com']);
Database::insert('groups', ['group_id' => 5, 'group_name' => 'Support']);
Database::insert('user_groups', ['user_id' => 1, 'group_id' => 5]);
Database::insert('user_groups', ['user_id' => 2, 'group_id' => 5]);
Database::insert('item_type_custom_fields', ['item_type_id' => 1, 'custom_field_id' => 7, 'custom_field_order' => 1]);
Database::insert('items', [
    'item_id' => 1,
    'create_date' => time(),
    'item_type_id' => 1,
    'creator_security' => 1,
    'user_security' => 1,
    'item_title' => 'Ticket',
]);
Database::insert('item_field_values', ['item_id' => 1, 'custom_field_id' => 7, 'value' => 'Open']);

$logRule = Rules::add(1, [
    'item_type_id' => 1,
    'name' => 'Log when closed',
    'event' => Rules::EVENT_UPDATE,
    'field_key' => 'custom_field_7',
    'operator' => Rules::OP_BECOMES,
    'match_value' => 'Closed',
    'sort_order' => 10,
    'steps' => [[
        'outcome' => Rules::OUTCOME_LOG,
        'body' => 'ITEM_TITLE is closed',
    ]],
]);
$setRule = Rules::add(1, [
    'item_type_id' => 1,
    'name' => 'Close it',
    'event' => Rules::EVENT_UPDATE,
    'operator' => Rules::OP_ALWAYS,
    'sort_order' => 20,
    'steps' => [[
        'outcome' => Rules::OUTCOME_SET_FIELD,
        'action_field' => 'custom_field_7',
        'action_value' => 'Closed',
    ]],
]);
$emailRule = Rules::add(1, [
    'item_type_id' => 1,
    'name' => 'Email the new owner',
    'event' => Rules::EVENT_UPDATE,
    'operator' => Rules::OP_ALWAYS,
    'sort_order' => 30,
    'skip_actor' => 1,
    'steps' => [
        [
            'outcome' => Rules::OUTCOME_SET_OWNER,
            'action_value' => '2',
        ],
        [
            'outcome' => Rules::OUTCOME_EMAIL,
            'recipient' => Rules::TO_OWNER,
            'subject' => 'Hello ITEM_TITLE',
            'body' => 'Owner is ITEM_OWNER',
        ],
    ],
]);
$groupRule = Rules::add(1, [
    'item_type_id' => 1,
    'name' => 'Email the owner groups',
    'event' => Rules::EVENT_UPDATE,
    'operator' => Rules::OP_ALWAYS,
    'sort_order' => 40,
    'skip_actor' => 1,
    'steps' => [[
        'outcome' => Rules::OUTCOME_EMAIL,
        'recipient' => Rules::TO_OWNER_GROUPS,
        'subject' => 'Group note',
        'body' => 'ITEM_ID',
    ]],
]);

$_SESSION['access_user_id'] = 1;
$_POST = [];
Rules::run(1, 'update_item');

$status = Database::first('item_field_values', ['value'], 'item_id = ? AND custom_field_id = ?', [1, 7]);
check((string) ($status['value'] ?? '') === 'Closed', 'cascade set the field to Closed');

$item = Database::first('items', ['user_security'], 'item_id = ?', [1]);
check((int) ($item['user_security'] ?? 0) === 2, 'set owner ran before the email in the same rule');

$log = Database::first('core_log', ['log_text'], 'item_id = ?', [1]);
check((string) ($log['log_text'] ?? '') === 'Ticket is closed', 'the earlier rule saw the field become Closed');
$logs = Database::select('core_log', ['id'], 'item_id = ?', [1]);
check(count($logs) === 1, 'cascade stopped after the field stopped changing');

$emailRun = Database::first('application_rule_runs', ['detail'], 'rule_id = ?', [$emailRule]);
check(str_contains((string) ($emailRun['detail'] ?? ''), 'bo@example.com'), 'email used the new owner');
check(!str_contains((string) ($emailRun['detail'] ?? ''), 'ada@example.com'), 'the person who saved was left off the email');

$groupRun = Database::first('application_rule_runs', ['detail'], 'rule_id = ?', [$groupRule]);
$groupDetail = (string) ($groupRun['detail'] ?? '');
check(str_contains($groupDetail, 'bo@example.com'), 'owner group members are included');
check(!str_contains($groupDetail, 'ada@example.com'), 'the actor is skipped for group mail');

$preview = Rules::preview($logRule, 1, 1);
check(($preview['matches'] ?? null) === false, 'try against the stored record does not invent a change');

Database::exec('CREATE TABLE announcements (
    id INTEGER PRIMARY KEY,
    time INTEGER,
    message TEXT,
    type TEXT,
    subject TEXT,
    application TEXT
)');
Database::exec('CREATE TABLE applications (
    application_id INTEGER PRIMARY KEY,
    slug TEXT
)');
Database::insert('applications', ['application_id' => 1, 'slug' => 'complaints']);
Database::insert('item_type_custom_fields', ['item_type_id' => 1, 'custom_field_id' => 8, 'custom_field_order' => 2]);
Database::insert('items', [
    'item_id' => 2,
    'create_date' => time(),
    'item_type_id' => 1,
    'creator_security' => 1,
    'user_security' => 1,
    'item_title' => 'Complaint',
]);
$stampRule = Rules::add(1, [
    'item_type_id' => 1,
    'name' => 'Stamp and post',
    'event' => Rules::EVENT_UPDATE,
    'operator' => Rules::OP_ALWAYS,
    'sort_order' => 50,
    'steps' => [
        ['outcome' => Rules::OUTCOME_STAMP, 'action_field' => 'custom_field_8'],
        ['outcome' => Rules::OUTCOME_ANNOUNCE, 'subject' => 'Opened: ITEM_TITLE', 'body' => 'ITEM_TITLE has been opened.'],
    ],
]);
Rules::run(2, 'update_item');
$stamped = Database::first('item_field_values', ['value'], 'item_id = ? AND custom_field_id = ?', [2, 8]);
check((string) ($stamped['value'] ?? '') === Rules::today(), 'stamp writes today\'s date');
$notice = Database::first('announcements', ['subject', 'message', 'application'], 'application = ?', ['complaints']);
check((string) ($notice['subject'] ?? '') === 'Opened: Complaint', 'a notice is posted for the application');
check((string) ($notice['message'] ?? '') === 'Complaint has been opened.', 'the notice uses the record title');
$stampRun = Database::first('application_rule_runs', ['detail'], 'rule_id = ?', [$stampRule]);
check(str_contains((string) ($stampRun['detail'] ?? ''), 'Stamped'), 'the run says the date was stamped');
check(str_contains((string) ($stampRun['detail'] ?? ''), 'Posted a notice'), 'the run says the notice was posted');

if ($failed > 0) {
    fwrite(STDERR, $failed . " check(s) failed\n");
    exit(1);
}
echo "all checks passed\n";
