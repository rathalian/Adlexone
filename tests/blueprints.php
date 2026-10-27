<?php
declare(strict_types=1);

/**
 * Each worked blueprint is held to the same shape as Publishing.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Application\ApplicationBuilder;
use Adlexone\Application\Rules;
use Adlexone\Application\ScreenCatalog;
use Adlexone\support\FieldTypes;

$failed = 0;
function blueprintCheck(bool $ok, string $message): void
{
    global $failed;
    echo ($ok ? 'ok  ' : 'FAIL ') . $message . "\n";
    $failed += $ok ? 0 : 1;
}

/** @param array<string, mixed> $blueprint */
function statusValues(array $blueprint): array
{
    foreach ($blueprint['fields'] as $field) {
        if (($field['name'] ?? '') === 'Status') {
            return array_values($field['values'] ?? []);
        }
    }

    return [];
}

/** @param array<string, mixed> $blueprint */
function nextStepFor(array $blueprint, string $status): ?string
{
    foreach ($blueprint['rules'] as $rule) {
        if (($rule['match'] ?? '') !== $status || ($rule['operator'] ?? '') !== Rules::OP_BECOMES) {
            continue;
        }
        foreach ($rule['steps'] as $step) {
            if (($step['outcome'] ?? '') === Rules::OUTCOME_SET_FIELD && ($step['field'] ?? '') === 'Next step') {
                return (string) $step['value'];
            }
        }
    }

    return null;
}

$blueprints = ApplicationBuilder::blueprints();
$keys = array_keys($blueprints);
blueprintCheck($keys[0] === ApplicationBuilder::BLUEPRINT_PUBLISHING, 'Publishing leads the blueprint list');
blueprintCheck($keys[array_key_last($keys)] === ApplicationBuilder::BLUEPRINT_BLANK, 'Blank is the last blueprint');

$showcaseMethod = new ReflectionMethod(ApplicationBuilder::class, 'showcase');
$showcaseMethod->setAccessible(true);
/** @var array<string, array<string, mixed>> $showcase */
$showcase = $showcaseMethod->invoke(null);

foreach ($blueprints as $key => $blueprint) {
    $label = (string) $blueprint['label'];
    if ($key === ApplicationBuilder::BLUEPRINT_BLANK) {
        blueprintCheck((string) $blueprint['guide'] === '', 'Blank has no How to use guide');
        blueprintCheck($blueprint['fields'] === [], 'Blank has no fields');
        continue;
    }

    $statuses = statusValues($blueprint);
    blueprintCheck($statuses !== [], $label . ' has statuses');
    blueprintCheck(trim((string) $blueprint['hint']) !== '', $label . ' has a description');
    blueprintCheck(trim((string) $blueprint['record']) !== '', $label . ' has a record type');
    blueprintCheck(trim((string) $blueprint['guide']) !== '', $label . ' has a How to use guide');
    $closed = $blueprint['closed'];
    blueprintCheck($closed !== [], $label . ' names its closed statuses');
    foreach ($closed as $ending) {
        blueprintCheck(in_array($ending, $statuses, true), $label . ' closed status ' . $ending . ' is a status');
    }
    foreach ($statuses as $index => $status) {
        blueprintCheck(str_contains((string) $blueprint['guide'], $status), $label . ' guide explains ' . $status);
        if ($index === 0) {
            continue;
        }
        $matched = false;
        foreach ($blueprint['rules'] as $rule) {
            if (($rule['operator'] ?? '') === Rules::OP_BECOMES && ($rule['match'] ?? '') === $status) {
                $matched = true;
            }
        }
        blueprintCheck($matched, $label . ' has a rule for ' . $status);
    }

    $creates = 0;
    $announces = false;
    foreach ($blueprint['rules'] as $rule) {
        if (($rule['event'] ?? '') === Rules::EVENT_CREATE) {
            $creates++;
            $stamps = false;
            foreach ($rule['steps'] as $step) {
                if (($step['outcome'] ?? '') === Rules::OUTCOME_STAMP) {
                    $stamps = true;
                }
            }
            blueprintCheck($stamps, $label . ' stamps a date when a record is created');
        }
        if (($rule['event'] ?? '') === Rules::EVENT_ATTACHMENT) {
            blueprintCheck(($rule['operator'] ?? '') !== Rules::OP_ALWAYS, $label . ' file rule is limited to a status');
        }
        foreach ($rule['steps'] as $step) {
            if (($step['outcome'] ?? '') === Rules::OUTCOME_ANNOUNCE) {
                $announces = true;
            }
        }
    }
    blueprintCheck($creates === 1, $label . ' has one create rule');
    $screens = $blueprint['screens'];
    blueprintCheck(in_array(ScreenCatalog::DIARY, $screens, true), $label . ' includes the diary');
    blueprintCheck(
        $announces === in_array(ScreenCatalog::ANNOUNCEMENTS, $screens, true),
        $label . ' has the wall only when a rule posts a notice'
    );
    $due = false;
    foreach ($blueprint['fields'] as $field) {
        if (($field['type'] ?? '') !== FieldTypes::DATE) {
            continue;
        }
        $plain = strtolower((string) $field['name']);
        if (str_contains($plain, 'due') || !str_ends_with($plain, ' on')) {
            $due = true;
        }
    }
    blueprintCheck($due, $label . ' has a date the diary can use');

    $search = $blueprint['search'];
    blueprintCheck(
        is_array($search) && in_array((string) ($search['value'] ?? ''), $statuses, true),
        $label . ' saved search matches a status'
    );
}

$partyNames = ['writer', 'client', 'applicant', 'place', 'insurer', 'reported by'];
foreach ($showcase as $slug => $spec) {
    $blueprint = $blueprints[$spec['blueprint']];
    $label = (string) $blueprint['label'];
    $closed = $blueprint['closed'];
    $party = '';
    foreach ($blueprint['fields'] as $field) {
        $plain = strtolower((string) ($field['name'] ?? ''));
        if (in_array($plain, $partyNames, true)) {
            $party = (string) $field['name'];
            break;
        }
    }
    blueprintCheck($party !== '', $label . ' has a party field');
    $counts = [];
    $finished = 0;
    foreach ($spec['cards'] as $card) {
        $fields = $card['fields'];
        $status = (string) ($fields['Status'] ?? '');
        blueprintCheck(in_array($status, statusValues($blueprint), true), $slug . ' sample "' . $card['title'] . '" uses a real status');
        $next = (string) ($fields['Next step'] ?? '');
        $expected = nextStepFor($blueprint, $status);
        if (in_array($status, $closed, true)) {
            $finished++;
            blueprintCheck($next === '', $slug . ' finished sample "' . $card['title'] . '" has no next step');
        } elseif ($expected !== null) {
            blueprintCheck($next === $expected, $slug . ' sample "' . $card['title'] . '" next step matches its rule');
        } else {
            blueprintCheck($next === '', $slug . ' sample "' . $card['title'] . '" has no next step before the first move');
        }
        $name = trim((string) ($fields[$party] ?? ''));
        if ($name !== '') {
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
    }
    blueprintCheck($finished >= 1, $label . ' showcase includes a finished record');
    blueprintCheck(max($counts ?: [0]) >= 2, $label . ' showcase gives one party more than one record');
}

echo $failed === 0 ? "all checks passed\n" : "{$failed} checks failed\n";
exit($failed === 0 ? 0 : 1);
