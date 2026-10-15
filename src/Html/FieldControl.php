<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\support\FieldTypes;

/**
 * Maps a stored field type to a control.
 *
 * Built-in types cover the plain inputs. Menus and read-only displays stay in the
 * item screen because they need more than a name and a value. Register a type to
 * add one, or to replace a plain input, without editing that screen.
 */
final class FieldControl
{
    /** @var array<string, callable(string, mixed, array<string, mixed>): string> */
    private static array $custom = [];

    /**
     * @param callable(string, mixed, array<string, mixed>): string $render
     */
    public static function register(string $type, callable $render): void
    {
        self::$custom[FieldTypes::normalise($type)] = $render;
    }

    /**
     * @param array<string, mixed> $field
     */
    public static function render(string $type, string $name, mixed $value, array $field = []): ?string
    {
        $type = FieldTypes::normalise($type);
        if (isset(self::$custom[$type])) {
            return (string) (self::$custom[$type])($name, $value, $field);
        }

        $rows = defined('SET_FORM_FIELD_HEIGHT') ? (string) SET_FORM_FIELD_HEIGHT : '3';

        return match ($type) {
            FieldTypes::TEXT_BOX, 'URL', 'dynamicURL', 'workerField' => Form::text($name, $value),
            'password' => Form::password($name, (string) $value),
            'hidden' => Form::hidden($name, $value),
            FieldTypes::TEXT_AREA => Form::textarea($name, $value, $rows),
            default => null,
        };
    }
}
