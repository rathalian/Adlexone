<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\support\Database;
use Adlexone\support\FieldTypes;
use Adlexone\support\FieldValidation;

/**
 * The fields that drive an application's work surfaces.
 *
 * Explicit choices live with the application. Older applications continue to
 * use the established name-based choices until an administrator saves them.
 */
final class WorkflowFields
{
    public const STATUS = 'status';
    public const STEP = 'step';
    public const DUE = 'due';

    /**
     * @return array{id: int, name: string, plain: string, label: string, type: string, values: list<string>}|null
     */
    public static function resolve(string $slug, int $typeId, string $purpose): ?array
    {
        if ($typeId <= 0) {
            return null;
        }
        $fields = RecordQuery::fields($typeId);
        $configured = RecordQuery::field($fields, self::configuredId($slug, $typeId, $purpose));
        if ($configured !== null && self::accepts($configured['type'], $purpose)) {
            return $configured;
        }

        return RecordQuery::chosen($fields, [], match ($purpose) {
            self::STATUS => 'menu',
            self::STEP => 'step',
            self::DUE => 'date',
            default => '',
        });
    }

    /**
     * @return list<string>|null
     */
    public static function openValues(string $slug, int $typeId, ?array $status): ?array
    {
        $config = self::typeSettings($slug, $typeId);

        return RecordQuery::openValues(['open_statuses' => $config['open_statuses'] ?? []], $status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function typeSettings(string $slug, int $typeId): array
    {
        $all = ApplicationSettings::get($slug)['workflow_fields'] ?? [];
        $value = is_array($all) ? ($all[(string) $typeId] ?? []) : [];

        return is_array($value) ? $value : [];
    }

    /**
     * Statuses that start a wait. The date is asked for when one of these is set.
     *
     * @return list<string>
     */
    public static function waitStatuses(string $slug, int $typeId): array
    {
        $block = $slug !== '' ? self::typeSettings($slug, $typeId) : [];
        if ($block === []) {
            $block = self::workflowForType($typeId);
        }
        if (array_key_exists('wait_statuses', $block) && is_array($block['wait_statuses'])) {
            return self::strings($block['wait_statuses']);
        }

        return self::inferWaits($typeId);
    }

    /**
     * Menu values for one record type, when the shared Status field carries more than this type uses.
     *
     * @return list<string>|null
     */
    public static function scopedStatusValues(int $typeId, int $fieldId): ?array
    {
        if ($typeId <= 0 || $fieldId <= 0) {
            return null;
        }
        $block = self::workflowForType($typeId);
        if ((int) ($block['status_field_id'] ?? 0) !== $fieldId) {
            return null;
        }
        $values = self::strings($block['status_values'] ?? []);

        return $values === [] ? null : $values;
    }

    /**
     * The due date is required when the posted status starts a wait and the record type has a due-date field.
     *
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    public static function dueError(int $typeId, string $slug, array $post): array
    {
        $status = self::resolve($slug, $typeId, self::STATUS);
        $due = self::resolve($slug, $typeId, self::DUE);
        if ($status === null || $due === null) {
            return [];
        }
        $value = trim((string) ($post['custom_field_' . $status['id']] ?? ''));
        if (!in_array($value, self::waitStatuses($slug, $typeId), true)) {
            return [];
        }
        $raw = trim((string) ($post['custom_field_' . $due['id']] ?? ''));
        if (FieldValidation::parseDate($raw, false) === null) {
            return ['custom_field_' . $due['id'] => 'Enter ' . $due['label'] . '.'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $settings
     * @param list<string> $openStatuses
     * @param list<string> $statusValues
     * @param list<string> $waitStatuses
     * @return array<string, mixed>
     */
    public static function store(
        array $settings,
        int $typeId,
        int $statusFieldId,
        int $stepFieldId,
        int $dueFieldId,
        array $openStatuses,
        array $statusValues = [],
        array $waitStatuses = []
    ): array {
        $workflow = is_array($settings['workflow_fields'] ?? null) ? $settings['workflow_fields'] : [];
        $previous = $workflow[(string) $typeId] ?? [];
        $previous = is_array($previous) ? $previous : [];
        $next = [
            'status_field_id' => $statusFieldId,
            'step_field_id' => $stepFieldId,
            'due_field_id' => $dueFieldId,
            'open_statuses' => array_values(array_unique(array_filter(array_map('trim', $openStatuses)))),
        ];
        $keptValues = $statusValues !== [] ? $statusValues : ($previous['status_values'] ?? null);
        if (is_array($keptValues)) {
            $next['status_values'] = self::strings($keptValues);
        }
        if ($waitStatuses !== [] || array_key_exists('wait_statuses', $previous)) {
            $next['wait_statuses'] = $waitStatuses !== []
                ? self::strings($waitStatuses)
                : self::strings($previous['wait_statuses'] ?? []);
        }
        $workflow[(string) $typeId] = $next;
        $settings['workflow_fields'] = $workflow;

        return $settings;
    }

    private static function configuredId(string $slug, int $typeId, string $purpose): int
    {
        if ($slug === '') {
            return 0;
        }
        $key = match ($purpose) {
            self::STATUS => 'status_field_id',
            self::STEP => 'step_field_id',
            self::DUE => 'due_field_id',
            default => '',
        };

        return $key !== '' ? (int) (self::typeSettings($slug, $typeId)[$key] ?? 0) : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private static function workflowForType(int $typeId): array
    {
        if ($typeId <= 0 || !Database::tableExists('applications') || !Database::columnExists('applications', 'settings_json')) {
            return [];
        }
        $needle = (string) $typeId;
        foreach (Database::select('applications', ['settings_json']) as $row) {
            $settings = json_decode((string) ($row['settings_json'] ?? ''), true);
            if (!is_array($settings)) {
                continue;
            }
            $block = $settings['workflow_fields'][$needle] ?? null;
            if (is_array($block)) {
                return $block;
            }
        }

        return [];
    }

    /**
     * A status starts a wait when the rule that sets it writes a next step about waiting.
     *
     * @return list<string>
     */
    private static function inferWaits(int $typeId): array
    {
        if ($typeId <= 0 || !Database::tableExists('application_rules') || !Database::tableExists('application_rule_steps')) {
            return [];
        }
        $waits = [];
        $rules = Database::select(
            'application_rules',
            ['id', 'match_value'],
            'item_type_id = ? AND operator = ?',
            [$typeId, Rules::OP_BECOMES]
        );
        foreach ($rules as $rule) {
            $match = trim((string) ($rule['match_value'] ?? ''));
            if ($match === '') {
                continue;
            }
            $steps = Database::select(
                'application_rule_steps',
                ['action_value'],
                'rule_id = ?',
                [(int) $rule['id']]
            );
            foreach ($steps as $step) {
                if (stripos((string) ($step['action_value'] ?? ''), 'waiting') !== false) {
                    $waits[$match] = $match;
                }
            }
        }

        return array_values($waits);
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function strings(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $values
        ), static fn (string $value): bool => $value !== '')));
    }

    private static function accepts(string $type, string $purpose): bool
    {
        return match ($purpose) {
            self::STATUS => $type === FieldTypes::MENU,
            self::STEP => $type === FieldTypes::TEXT_BOX || $type === FieldTypes::TEXT_AREA,
            self::DUE => $type === FieldTypes::DATE || $type === FieldTypes::DATETIME,
            default => false,
        };
    }
}
