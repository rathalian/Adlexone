<?php
declare(strict_types=1);

namespace Adlexone\Data;

/**
 * Custom field types as stored in custom_fields.field_type.
 *
 * The database stores stable type keys (e.g. 'menu'), not form-builder method names.
 */
final class FieldTypes
{
    public const TEXT_BOX = 'textBox';
    public const TEXT_AREA = 'textArea';
    public const MENU = 'menu';
    public const CHECK_BOX = 'checkBox';
    public const DATE = 'date';
    public const TIME = 'time';
    public const DATETIME = 'datetime';
    public const EMAIL = 'email';
    public const NUMBER = 'number';

    public static function normalise(?string $fieldType): string
    {
        return trim((string) $fieldType);
    }

    /** Name shown on a field list. Stored keys stay as they are. */
    public static function label(?string $fieldType): string
    {
        return match (self::normalise($fieldType)) {
            self::TEXT_BOX => 'Text',
            self::TEXT_AREA => 'Note',
            self::MENU => 'Menu',
            self::CHECK_BOX => 'Checkbox',
            self::DATE => 'Date',
            self::TIME => 'Time',
            self::DATETIME => 'Date and time',
            self::EMAIL => 'Email',
            self::NUMBER => 'Number',
            'password' => 'Password',
            'hidden' => 'Hidden',
            'URL' => 'Link',
            'dynamicURL' => 'Built link',
            'workerField', 'workerFieldMenu' => 'Person',
            default => self::normalise($fieldType),
        };
    }

    /**
     * Types offered when adding or editing a field.
     *
     * @return list<string>
     */
    public static function choices(): array
    {
        return [
            self::TEXT_BOX,
            self::TEXT_AREA,
            self::MENU,
            self::CHECK_BOX,
            self::DATE,
            self::TIME,
            self::DATETIME,
            self::EMAIL,
            self::NUMBER,
            'URL',
            'dynamicURL',
            'password',
            'hidden',
            'workerField',
            'workerFieldMenu',
        ];
    }

    /**
     * Validation kind implied by the field type. Empty when the type is free text
     * and a separate validation_type may still apply.
     */
    public static function impliedValidation(?string $fieldType): string
    {
        return match (self::normalise($fieldType)) {
            self::DATE => 'date',
            self::TIME => 'time',
            self::DATETIME => 'datetime',
            self::EMAIL => 'email',
            self::NUMBER => 'numeric',
            'URL' => 'url',
            default => '',
        };
    }

    /**
     * Control to render: the field type, or the validation kind on a text field.
     */
    public static function control(?string $fieldType, ?string $validationType = ''): string
    {
        $type = self::normalise($fieldType);
        if (self::impliedValidation($type) !== '') {
            return $type;
        }
        return match (self::normaliseKind($validationType)) {
            'date' => self::DATE,
            'time' => self::TIME,
            'datetime' => self::DATETIME,
            'email' => self::EMAIL,
            'numeric' => self::NUMBER,
            'url' => 'URL',
            default => $type,
        };
    }

    public static function normaliseKind(?string $kind): string
    {
        $kind = strtolower(trim((string) $kind));
        return match ($kind) {
            'datetime', 'date_time' => 'datetime',
            'number', 'numeric' => 'numeric',
            'link', 'url' => 'url',
            'null', '' => '',
            default => $kind,
        };
    }

    /**
     * A bound field_type predicate for this type.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function sqlInList(string $fieldType): array
    {
        if ($fieldType === '') {
            return ['1 = 0', []];
        }

        return ['field_type = ?', [$fieldType]];
    }
}
