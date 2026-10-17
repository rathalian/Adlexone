<?php
declare(strict_types=1);

namespace Adlexone\Html;

/**
 * Escaped form markup. Class names are the contract with layouts/css/base.css.
 * Raw $attributes strings are a legacy passthrough for screens that still
 * attach their own validation handlers.
 */
final class Form
{
    /**
     * @param array<string, string> $fields label => control HTML
     * @param list<string> $buttons
     * @param array{method?: string, name?: string, id?: string, enctype?: string} $options
     */
    public static function form(string $title, string $action, array $fields, array $buttons, array $options = []): string
    {
        $html = self::open(
            $action,
            (string) ($options['method'] ?? 'post'),
            (string) ($options['name'] ?? ''),
            (string) ($options['id'] ?? ''),
            (string) ($options['enctype'] ?? 'application/x-www-form-urlencoded')
        );
        $html .= self::fields($fields);
        $html .= self::close($buttons);

        $block = [
            'title' => $title,
            'html' => $html,
            'class' => 'card--sheet',
        ];
        if (!empty($options['disclosure'])) {
            $block['disclosure'] = true;
        }

        return Blocks::vertical([$block]);
    }

    public static function open(
        string $action,
        string $method,
        string $name = '',
        string $id = '',
        string $enctype = 'application/x-www-form-urlencoded'
    ): string {
        $html = Html::open('form', [
            'action' => $action,
            'method' => $method,
            'enctype' => $enctype,
            'name' => $name,
            'id' => $id,
        ]);
        if (strtolower($method) === 'post' && class_exists(\Adlexone\Auth\Csrf::class)) {
            $html .= \Adlexone\Auth\Csrf::field();
        }

        return $html;
    }

    /**
     * @param list<string> $buttons
     */
    public static function close(array $buttons): string
    {
        $html = '<div class="form-actions">';
        foreach ($buttons as $button) {
            $html .= $button;
        }

        return $html . '</div></form>';
    }

    public static function section(string $text): string
    {
        return Html::tag('div', ['class' => 'form-section'], Html::e($text));
    }

    /**
     * @param array<mixed, mixed> $labelFormElementArray
     */
    public static function fields(array $labelFormElementArray): string
    {
        $html = '<div class="form-grid">';
        $fieldIndex = 0;
        foreach ($labelFormElementArray as $label => $element) {
            $fieldIndex++;
            $labelText = trim((string) $label);
            if ($labelText === '') {
                $html .= $element;
                continue;
            }
            [$element, $controlId] = self::associateControlId((string) $element, 'field-' . $fieldIndex);
            $html .= '<div class="field">';
            $html .= Html::tag('label', [
                'class' => 'label',
                'for' => $controlId !== '' ? $controlId : null,
            ], Html::e($labelText));
            $html .= $element;
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * onclick value for a confirm dialog. Prefer data-confirm on new markup.
     */
    public static function confirm(string $message): string
    {
        $message = str_replace('\\n', "\n", $message);

        return Html::e(
            'return confirm(' . json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ')');
    }

    public static function confirmData(string $message): string
    {
        $message = str_replace('\\n', "\n", $message);
        if ($message === '') {
            return '';
        }

        return ' data-confirm="' . Html::e($message) . '"';
    }

    /**
     * @param list<mixed>|null $values
     * @param list<mixed>|null $displayValues
     */
    public static function select(string $name, ?array $values, ?array $displayValues, mixed $selectedValue, string $attributes = ''): string
    {
        $html = Html::open('select', ['name' => $name, 'class' => 'select'], $attributes);
        if (empty($values)) {
            $html .= Html::tag('option', ['value' => '', 'selected' => true], Html::e(defined('TXT_366') ? TXT_366 : ''));
        } else {
            foreach ($values as $i => $value) {
                $html .= Html::tag('option', [
                    'value' => $value,
                    'selected' => ($value == $selectedValue) ? true : null,
                ], Html::e($displayValues[$i] ?? ''));
            }
        }

        return $html . '</select>';
    }

    public static function text(string $name, mixed $value = '', string $placeholder = '', bool $readOnly = false): string
    {
        return Html::tag('input', [
            'type' => 'text',
            'name' => $name,
            'value' => $value ?? '',
            'placeholder' => $placeholder,
            'class' => 'input',
            'readonly' => $readOnly ? true : null,
        ]);
    }

    public static function file(string $name, string $maximumSize): string
    {
        return Html::tag('input', ['name' => 'MAX_FILE_SIZE', 'value' => $maximumSize, 'type' => 'hidden'])
            . Html::tag('input', ['name' => $name, 'type' => 'file', 'class' => 'input input-file']);
    }

    public static function date(string $nameID, string $value, string $dateFormat): string
    {
        $day = self::text('day_' . $nameID, $value);
        $month = self::text('month_' . $nameID, $value);
        $year = self::text('year_' . $nameID, $value);
        $separator = '-';

        return match ($dateFormat) {
            'dmY' => "{$day}{$separator}{$month}{$separator}{$year} " . (defined('TXT_382') ? TXT_382 : ''),
            'mdY' => "{$month}{$separator}{$day}{$separator}{$year} " . (defined('TXT_383') ? TXT_383 : ''),
            default => "{$year}{$separator}{$month}{$separator}{$year} " . (defined('TXT_382') ? TXT_382 : ''),
        };
    }

    public static function password(string $name, string $value): string
    {
        return Html::tag('input', [
            'type' => 'password',
            'name' => $name,
            'value' => $value,
            'class' => 'input',
        ]);
    }

    public static function textarea(string $name, mixed $value, string $rows = '3', bool $readOnly = false): string
    {
        return Html::tag('textarea', [
            'name' => $name,
            'rows' => $rows,
            'class' => 'input',
            'readonly' => $readOnly ? 'readonly' : null,
        ], Html::e($value));
    }

    public static function hidden(string $name, mixed $value): string
    {
        return Html::tag('input', ['name' => $name, 'type' => 'hidden', 'value' => $value ?? '']);
    }

    public static function button(string $type, string $name = '', string $value = '', string $attributes = '', string $variant = ''): string
    {
        if ($variant === '') {
            $variant = $type === 'reset' ? 'secondary' : 'primary';
        }
        $class = match ($variant) {
            'primary' => 'btn btn--primary',
            'quiet' => 'btn btn--quiet',
            'danger' => 'btn btn--danger',
            default => 'btn',
        };

        return Html::tag('input', [
            'type' => $type,
            'name' => $name,
            'value' => $value,
            'class' => $class,
        ], null, $attributes);
    }

    public static function checkbox(string $name, string $value, string $selectedValue, string $label = '', string $attributes = ''): string
    {
        $input = Html::tag('input', [
            'name' => $name,
            'type' => 'checkbox',
            'value' => $value,
            'class' => 'checkbox',
            'checked' => ($value == $selectedValue) ? true : null,
        ], null, $attributes);

        return Html::tag('label', ['class' => 'checkbox'], $input . ' ' . Html::e($label));
    }

    public static function radio(string $name, string $value, string $selectedValue, string $attributes = ''): string
    {
        return Html::tag('input', [
            'name' => $name,
            'type' => 'radio',
            'value' => $value,
            'class' => 'radio',
            'checked' => ($value == $selectedValue) ? true : null,
        ], null, $attributes);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function associateControlId(string $element, string $fallbackId): array
    {
        static $sequence = 0;
        $sequence++;
        $fallbackId .= '-' . $sequence;
        if (preg_match_all('/<(input|select|textarea)\b([^>]*)>/', $element, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $index => $full) {
                $tag = $matches[1][$index][0];
                $attrs = $matches[2][$index][0];
                if ($tag === 'input' && preg_match('/\btype\s*=\s*"hidden"/i', $attrs) === 1) {
                    continue;
                }
                if (preg_match('/\bid="([^"]+)"/', $attrs, $idMatch) === 1) {
                    return [$element, $idMatch[1]];
                }
                $id = Html::e($fallbackId);
                $replacement = '<' . $tag . ' id="' . $id . '"' . $attrs . '>';
                $element = substr_replace($element, $replacement, $full[1], strlen($full[0]));

                return [$element, $fallbackId];
            }
        }

        return [$element, ''];
    }
}
