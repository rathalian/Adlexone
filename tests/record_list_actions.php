<?php
declare(strict_types=1);

/**
 * Checks record-list actions that stay visible beside an overflow menu.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Html\Blocks;

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL {$message}\n");
};

$html = Blocks::recordList([
    'column' => 'Application',
    'groups' => [[
        'rows' => [[
            'name' => 'Matters',
            'href' => '?manage=applications&option=edit&application_id=1',
            'actions' => [
                ['href' => '', 'label' => 'Up', 'inline' => true, 'disabled' => true],
                ['href' => '?manage=applications&option=move&application_id=1&direction=down', 'label' => 'Down', 'inline' => true],
                ['href' => '?manage=applications&option=nav&application_id=1', 'label' => 'Navigation'],
                ['href' => '?manage=applications&option=export&application_id=1', 'label' => 'Export'],
                ['href' => '?manage=applications&option=delete&application_id=1', 'label' => 'Delete', 'tone' => 'danger'],
            ],
        ]],
    ]],
]);

$check(str_contains($html, 'class="record-list__order-actions"'), 'order actions remain visible beside the menu');
$check(str_contains($html, '<button class="btn btn--sm btn--quiet" type="button" disabled aria-label="Up: Matters">Up</button>'), 'unavailable Up action is a disabled button');
$check(str_contains($html, 'direction=down') && str_contains($html, 'aria-label="Down: Matters">Down</a>'), 'available Down action is a link');
$check(str_contains($html, 'class="rowmenu"'), 'other actions remain in the overflow menu');
$check(!str_contains($html, 'role="menuitem" href=""'), 'disabled order action is not duplicated in the menu');

if ($failed > 0) {
    exit(1);
}
echo "record-list action checks passed\n";
