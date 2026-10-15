<?php
declare(strict_types=1);

namespace Adlexone\Html;

/**
 * Escaped tags. Empty strings are kept. Null and false are omitted. True is a boolean attribute.
 */
final class Html
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function tag(string $name, array $attributes = [], ?string $innerHtml = null, string $raw = ''): string
    {
        $attr = self::attributes($attributes, $raw);
        if ($innerHtml === null) {
            return '<' . $name . $attr . '>';
        }

        return '<' . $name . $attr . '>' . $innerHtml . '</' . $name . '>';
    }

    /**
     * Opening tag. Pair it with a literal close when the body is built separately.
     *
     * @param array<string, mixed> $attributes
     */
    public static function open(string $name, array $attributes = [], string $raw = ''): string
    {
        return self::tag($name, $attributes, null, $raw);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private static function attributes(array $attributes, string $raw): string
    {
        $attr = '';
        foreach ($attributes as $key => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $attr .= ' ' . $key;
                continue;
            }
            $attr .= ' ' . $key . '="' . self::e($value) . '"';
        }
        if ($raw !== '') {
            $attr .= ' ' . $raw;
        }

        return $attr;
    }
}
