<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\support\FieldTypes;

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
     * @param array<string, mixed> $settings
     * @param list<string> $openStatuses
     * @return array<string, mixed>
     */
    public static function store(
        array $settings,
        int $typeId,
        int $statusFieldId,
        int $stepFieldId,
        int $dueFieldId,
        array $openStatuses
    ): array {
        $workflow = is_array($settings['workflow_fields'] ?? null) ? $settings['workflow_fields'] : [];
        $workflow[(string) $typeId] = [
            'status_field_id' => $statusFieldId,
            'step_field_id' => $stepFieldId,
            'due_field_id' => $dueFieldId,
            'open_statuses' => array_values(array_unique(array_filter(array_map('trim', $openStatuses)))),
        ];
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
