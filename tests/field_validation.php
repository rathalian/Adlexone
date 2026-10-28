<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Html\FieldControl;
use Adlexone\support\FieldTypes;
use Adlexone\support\FieldValidation;

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

if (!defined('SET_DATE_FORMAT')) {
    define('SET_DATE_FORMAT', 'd-m-Y, h:i A');
}

check(FieldTypes::impliedValidation(FieldTypes::DATE) === 'date', 'date type implies date validation');
check(FieldTypes::impliedValidation(FieldTypes::EMAIL) === 'email', 'email type implies email validation');
check(FieldTypes::impliedValidation(FieldTypes::NUMBER) === 'numeric', 'number type implies numeric validation');
check(FieldTypes::impliedValidation(FieldTypes::TEXT_BOX) === '', 'text type has no implied validation');
check(FieldTypes::control(FieldTypes::TEXT_BOX, 'date') === FieldTypes::DATE, 'text plus date validation uses a date control');
check(FieldTypes::control(FieldTypes::DATE, '') === FieldTypes::DATE, 'date type uses a date control');
check(FieldTypes::label(FieldTypes::DATETIME) === 'Date and time', 'datetime label');

$dateField = ['field_type' => FieldTypes::DATE, 'custom_field_name' => 'Closed on', 'required' => 'No'];
check(FieldValidation::kind($dateField) === 'date', 'kind from date type');
check(FieldValidation::error('2026-10-20', $dateField) === null, 'ISO date is valid');
check(FieldValidation::error('20-10-2026', $dateField) === null, 'day-month-year date is valid');
check(FieldValidation::error('not-a-date', $dateField) !== null, 'junk date is rejected');
check(FieldValidation::error('', $dateField) === null, 'empty optional date is allowed');
$dateField['required'] = 'Yes';
check(FieldValidation::error('', $dateField) !== null, 'empty required date is rejected');

$emailField = ['field_type' => FieldTypes::EMAIL, 'custom_field_name' => 'Contact', 'required' => 'No'];
check(FieldValidation::error('records@health.example.com', $emailField) === null, 'email is valid');
check(FieldValidation::error('not-an-email', $emailField) !== null, 'junk email is rejected');

$numberField = ['field_type' => FieldTypes::NUMBER, 'custom_field_name' => 'Amount', 'required' => 'No'];
check(FieldValidation::error('12.50', $numberField) === null, 'decimal number is valid');
check(FieldValidation::error('twelve', $numberField) !== null, 'words are not a number');

$urlField = ['field_type' => 'URL', 'custom_field_name' => 'Site', 'required' => 'No'];
check(FieldValidation::kind($urlField) === 'url', 'link type implies url validation');
check(FieldValidation::error('https://health.example.com', $urlField) === null, 'https link is valid');
check(FieldValidation::error('health.example.com', $urlField) === null, 'host without scheme is accepted');
check(FieldValidation::error('not a link', $urlField) !== null, 'plain words are not a link');

$textDate = ['field_type' => FieldTypes::TEXT_BOX, 'validation_type' => 'date', 'custom_field_name' => 'Due', 'required' => 'No'];
check(FieldValidation::kind($textDate) === 'date', 'text field with date validation');
check(FieldValidation::toDateInput('20-10-2026') === '2026-10-20', 'day-month-year becomes ISO for the picker');
check(FieldValidation::toDateInput('2026-10-20') === '2026-10-20', 'ISO stays ISO');
check(FieldValidation::display($dateField, '2026-10-20') === '20-10-2026', 'date display uses the site format');

$markup = FieldControl::render(FieldTypes::DATE, 'custom_field_9', '20-10-2026', $dateField);
check(is_string($markup) && str_contains($markup, 'type="date"'), 'date field renders a date picker');
check(is_string($markup) && str_contains($markup, 'value="2026-10-20"'), 'date picker value is ISO');

$emailMarkup = FieldControl::render(FieldTypes::EMAIL, 'custom_field_10', 'records@health.example.com', $emailField);
check(is_string($emailMarkup) && str_contains($emailMarkup, 'type="email"'), 'email field renders an email control');

$numberMarkup = FieldControl::render(FieldTypes::NUMBER, 'custom_field_11', '12.5', $numberField);
check(is_string($numberMarkup) && str_contains($numberMarkup, 'type="number"'), 'number field renders a number control');

$textDateMarkup = FieldControl::render(FieldTypes::TEXT_BOX, 'custom_field_12', '2026-10-20', $textDate);
check(is_string($textDateMarkup) && str_contains($textDateMarkup, 'type="date"'), 'text plus date validation still opens a date picker');

$markup = (new \Adlexone\support\FieldCheck())->withTitle()->onclick();
check(preg_match('/data-field-check="([^"]*)"/', $markup, $match) === 1, 'field check is a data attribute');
check(!str_contains($markup, 'onclick='), 'field check does not use an event handler');
$payload = json_decode(html_entity_decode($match[1] ?? '', ENT_QUOTES), true);
check(is_array($payload) && in_array('item_title', $payload['names'] ?? [], true), 'field check names the title field');

if ($failed > 0) {
    fwrite(STDERR, $failed . " checks failed\n");
    exit(1);
}
echo "all checks passed\n";
