<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * Custom field types as stored in custom_fields.field_type.
 *
 * The database stores stable type keys (e.g. 'menu'), not RenderViews method names, so that renaming a
 * rendering helper never invalidates stored data. Builder-style names written by earlier builds are
 * accepted as aliases and normalised on read.
 */
final class FieldTypes
{
    public const TEXT_BOX = 'textBox';
    public const TEXT_AREA = 'textArea';
    public const MENU = 'menu';
    public const CHECK_BOX = 'checkBox';

    private const ALIASES = [
        'buildTextInput'      => self::TEXT_BOX,
        'buildTextArea'       => self::TEXT_AREA,
        'buildSelectDropdown' => self::MENU,
        'buildCheckBox'       => self::CHECK_BOX,
    ];

    public static function normalise(?string $fieldType): string
    {
        $fieldType = (string)$fieldType;
        return self::ALIASES[$fieldType] ?? $fieldType;
    }

    /**
     * SQL list literal matching a type and any legacy aliases, for use in "field_type IN (...)".
     */
    public static function sqlInList(string $fieldType): string
    {
        $names = array_merge([$fieldType], array_keys(self::ALIASES, $fieldType, true));
        return implode(', ', array_map(fn(string $n): string => "'" . Database::escape($n) . "'", $names));
    }
}
