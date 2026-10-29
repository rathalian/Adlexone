<?php
declare(strict_types=1);

namespace Adlexone\Html;

use Adlexone\Theme\Theme;

/**
 * Figures for dashboards and summary screens. Every chart is plain HTML or inline SVG,
 * so it prints, works without scripts, and follows the theme and light or dark mode.
 *
 * A segment names its colour with a tone (success, warning, danger, info, neutral)
 * or leaves it blank to take the next series colour.
 */
final class Charts
{
    private const TONES = ['success', 'warning', 'danger', 'info', 'neutral'];

    /**
     * A single figure with a label and a short note.
     *
     * @param array{label: string, value: string|int, note?: string, icon?: string, tone?: string, href?: string, trend?: list<int|float>, delta?: string} $kpi
     */
    public static function kpi(array $kpi): string
    {
        $tone = self::tone((string) ($kpi['tone'] ?? ''));
        $icon = (string) ($kpi['icon'] ?? '');
        $href = (string) ($kpi['href'] ?? '');
        $value = (string) $kpi['value'];
        $numeric = ctype_digit($value) ? ' data-count="' . Html::e($value) . '"' : '';
        $valueClass = !ctype_digit($value) && mb_strlen($value) > 6 ? ' kpi__value--text' : '';
        $trend = $kpi['trend'] ?? [];
        $delta = trim((string) ($kpi['delta'] ?? ''));

        $inner = '<div class="kpi__head">'
            . ($icon !== '' ? '<span class="kpi__icon">' . self::icon($icon) . '</span>' : '')
            . '<span class="kpi__label">' . Html::e((string) $kpi['label']) . '</span></div>'
            . '<div class="kpi__value' . $valueClass . '"' . $numeric . '>' . Html::e($value) . '</div>'
            . '<div class="kpi__foot">'
            . ($delta !== '' ? '<span class="kpi__delta">' . Html::e($delta) . '</span>' : '')
            . ((string) ($kpi['note'] ?? '') !== '' ? '<span class="kpi__note">' . Html::e((string) $kpi['note']) . '</span>' : '')
            . '</div>'
            . (count($trend) > 1 ? '<div class="kpi__trend" aria-hidden="true">' . self::sparkline($trend, 120, 32) . '</div>' : '');

        $class = 'kpi kpi--' . $tone . ($href !== '' ? ' kpi--link' : '');
        if ($href !== '') {
            return '<a class="' . $class . '" href="' . Html::e($href) . '">' . $inner . '</a>';
        }

        return '<div class="' . $class . '">' . $inner . '</div>';
    }

    /**
     * @param list<array<string, mixed>> $kpis
     */
    public static function kpis(array $kpis): string
    {
        if ($kpis === []) {
            return '';
        }
        $html = '';
        foreach ($kpis as $kpi) {
            $html .= self::kpi($kpi);
        }

        return '<div class="kpis">' . $html . '</div>';
    }

    /**
     * Horizontal bars, one per row, each as a share of the largest value.
     *
     * @param list<array{label: string, value: int|float, href?: string, tone?: string, note?: string}> $rows
     */
    public static function bars(array $rows, string $unit = '', bool $uniform = false): string
    {
        $rows = array_values(array_filter($rows, static fn (array $row): bool => (string) $row['label'] !== ''));
        if ($rows === []) {
            return '';
        }
        $max = 0.0;
        foreach ($rows as $row) {
            $max = max($max, (float) $row['value']);
        }
        $html = '<ul class="bars">';
        foreach ($rows as $index => $row) {
            $value = (float) $row['value'];
            $width = $max > 0 ? max($value > 0 ? 2.0 : 0.0, round($value / $max * 100, 1)) : 0.0;
            $label = Html::e((string) $row['label']);
            if ((string) ($row['href'] ?? '') !== '') {
                $label = '<a href="' . Html::e((string) $row['href']) . '">' . $label . '</a>';
            }
            $html .= '<li class="bars__row ' . ($uniform && !in_array((string) ($row['tone'] ?? ''), self::TONES, true) ? 's0' : self::series($row, $index)) . '">'
                . '<span class="bars__label">' . $label . '</span>'
                . '<span class="bars__track"><span class="bars__fill ' . Measure::width($width) . '"></span></span>'
                . '<span class="bars__value">' . Html::e(self::number($value)) . ($unit !== '' ? ' <small>' . Html::e($unit) . '</small>' : '') . '</span>'
                . '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * One bar split into parts, with a legend underneath.
     *
     * @param list<array{label: string, value: int|float, tone?: string}> $parts
     */
    public static function stack(array $parts): string
    {
        $parts = array_values(array_filter($parts, static fn (array $part): bool => (float) $part['value'] > 0));
        $total = 0.0;
        foreach ($parts as $part) {
            $total += (float) $part['value'];
        }
        if ($total <= 0) {
            return '';
        }
        $shares = Measure::portions(array_map(static fn (array $part): float => (float) $part['value'], $parts));
        $bar = '';
        $legend = '';
        foreach ($parts as $index => $part) {
            $class = self::series($part, $index);
            $title = Html::e($part['label'] . ': ' . self::number((float) $part['value']));
            $bar .= '<span class="stack__part ' . $class . ' ' . Measure::width($shares[$index] / 10) . '" title="' . $title . '"></span>';
            $legend .= '<li class="' . $class . '"><span class="legend__dot"></span>' . Html::e((string) $part['label'])
                . ' <strong>' . Html::e(self::number((float) $part['value'])) . '</strong></li>';
        }

        return '<div class="stack"><div class="stack__bar" role="img" aria-label="' . Html::e(self::summary($parts)) . '">' . $bar . '</div>'
            . '<ul class="legend">' . $legend . '</ul></div>';
    }

    /**
     * @param list<array{label: string, value: int|float, tone?: string}> $parts
     */
    public static function donut(array $parts, string $centre = '', string $caption = ''): string
    {
        $parts = array_values(array_filter($parts, static fn (array $part): bool => (float) $part['value'] > 0));
        $total = 0.0;
        foreach ($parts as $part) {
            $total += (float) $part['value'];
        }
        if ($total <= 0) {
            return '';
        }
        $radius = 42.0;
        $circumference = 2 * M_PI * $radius;
        $offset = 0.0;
        $rings = '';
        $legend = '';
        foreach ($parts as $index => $part) {
            $length = (float) $part['value'] / $total * $circumference;
            $gap = count($parts) > 1 ? 1.5 : 0.0;
            $class = self::series($part, $index);
            $rings .= sprintf(
                '<circle class="donut__ring %s" cx="50" cy="50" r="%s" stroke-dasharray="%s %s" stroke-dashoffset="%s"><title>%s</title></circle>',
                $class,
                $radius,
                round(max(0.0, $length - $gap), 2),
                round($circumference - max(0.0, $length - $gap), 2),
                round(-$offset, 2),
                Html::e($part['label'] . ': ' . self::number((float) $part['value']))
            );
            $offset += $length;
            $legend .= '<li class="' . $class . '"><span class="legend__dot"></span>' . Html::e((string) $part['label'])
                . ' <strong>' . Html::e(self::number((float) $part['value'])) . '</strong></li>';
        }
        $centre = $centre !== '' ? $centre : self::number($total);

        return '<div class="donut"><figure class="donut__figure">'
            . '<svg viewBox="0 0 100 100" role="img" aria-label="' . Html::e(self::summary($parts)) . '">'
            . '<circle class="donut__base" cx="50" cy="50" r="' . $radius . '"/>' . $rings . '</svg>'
            . '<figcaption class="donut__centre"><strong>' . Html::e($centre) . '</strong>'
            . ($caption !== '' ? '<span>' . Html::e($caption) . '</span>' : '') . '</figcaption></figure>'
            . '<ul class="legend legend--stacked">' . $legend . '</ul></div>';
    }

    /**
     * Vertical columns, for a value over a sequence such as weeks or age bands.
     *
     * @param list<array{label: string, value: int|float, tone?: string, href?: string}> $columns
     */
    public static function columns(array $columns, bool $uniform = false): string
    {
        if ($columns === []) {
            return '';
        }
        $max = 0.0;
        foreach ($columns as $column) {
            $max = max($max, (float) $column['value']);
        }
        $html = '<div class="columns" role="list">';
        foreach ($columns as $index => $column) {
            $value = (float) $column['value'];
            $height = $max > 0 ? ($value > 0 ? max(4.0, round($value / $max * 100, 1)) : 0.0) : 0.0;
            $label = Html::e((string) $column['label']);
            $tag = (string) ($column['href'] ?? '') !== '' ? 'a' : 'div';
            $href = $tag === 'a' ? ' href="' . Html::e((string) $column['href']) . '"' : '';
            $html .= '<' . $tag . ' class="columns__col ' . ($uniform ? 's0' : self::series($column, $index)) . '" role="listitem"' . $href
                . ' title="' . Html::e((string) $column['label'] . ': ' . self::number($value)) . '">'
                . '<span class="columns__value">' . ($value > 0 ? Html::e(self::number($value)) : '') . '</span>'
                . '<span class="columns__bar ' . Measure::height($height) . '"></span>'
                . '<span class="columns__label">' . $label . '</span></' . $tag . '>';
        }

        return $html . '</div>';
    }

    /**
     * @param list<int|float> $values
     */
    public static function sparkline(array $values, int $width = 120, int $height = 32): string
    {
        $count = count($values);
        if ($count < 2) {
            return '';
        }
        $min = min($values);
        $max = max($values);
        $span = ($max - $min) ?: 1;
        $pad = 3;
        $points = [];
        foreach (array_values($values) as $index => $value) {
            $x = $pad + ($width - 2 * $pad) * $index / ($count - 1);
            $y = $height - $pad - ($height - 2 * $pad) * (($value - $min) / $span);
            $points[] = round($x, 1) . ',' . round($y, 1);
        }
        $line = implode(' ', $points);
        $area = $line . ' ' . round($width - $pad, 1) . ',' . ($height - $pad) . ' ' . $pad . ',' . ($height - $pad);
        $last = explode(',', $points[$count - 1]);

        return '<svg class="spark" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" focusable="false">'
            . '<polygon class="spark__area" points="' . $area . '"/>'
            . '<polyline class="spark__line" points="' . $line . '" fill="none"/>'
            . '<circle class="spark__dot" cx="' . $last[0] . '" cy="' . $last[1] . '" r="2.4"/></svg>';
    }

    /**
     * A titled block for a chart.
     */
    public static function panel(string $title, string $body, string $note = ''): string
    {
        if ($body === '') {
            return '';
        }

        return '<section class="chartcard"><header class="chartcard__head"><h3 class="chartcard__title">' . Html::e($title) . '</h3>'
            . ($note !== '' ? '<p class="chartcard__note">' . Html::e($note) . '</p>' : '')
            . '</header>' . $body . '</section>';
    }

    /**
     * @param list<string> $panels
     */
    public static function row(array $panels): string
    {
        $panels = array_values(array_filter($panels, static fn (string $panel): bool => $panel !== ''));
        if ($panels === []) {
            return '';
        }

        return '<div class="chartrow chartrow--' . min(3, count($panels)) . '">' . implode('', $panels) . '</div>';
    }

    public static function icon(string $name): string
    {
        return '<svg class="icon" aria-hidden="true"><use href="' . Html::e(Theme::spriteHref()) . '#' . Html::e($name) . '"></use></svg>';
    }

    public static function number(float $value): string
    {
        return $value == floor($value) ? number_format($value, 0) : number_format($value, 1);
    }

    private static function tone(string $tone): string
    {
        return in_array($tone, self::TONES, true) ? $tone : 'brand';
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function series(array $row, int $index): string
    {
        $tone = (string) ($row['tone'] ?? '');
        if (in_array($tone, self::TONES, true)) {
            return 'tone-' . $tone;
        }

        return 's' . ($index % 6);
    }

    /**
     * @param list<array<string, mixed>> $parts
     */
    private static function summary(array $parts): string
    {
        $bits = [];
        foreach ($parts as $part) {
            $bits[] = (string) $part['label'] . ' ' . self::number((float) $part['value']);
        }

        return implode(', ', $bits);
    }
}
