<?php
declare(strict_types=1);

namespace Adlexone\Html;

/**
 * Class names for a calculated size or colour.
 *
 * The page policy allows stylesheets from this site only, so a width, a hue,
 * or an accent is a class in layouts/css/scale.css rather than a style attribute.
 */
final class Measure
{
    /** Deepest menu indent the editor stylesheet provides. */
    public const MAX_DEPTH = 12;

    /** Widest launchpad column count the stylesheet provides. */
    public const MAX_COLUMNS = 12;

    /**
     * A horizontal measure from 0 to 100 percent, kept to a tenth.
     */
    public static function width(float $percent): string
    {
        return 'w-' . self::tenths($percent);
    }

    /**
     * A vertical measure from 0 to 100 percent, kept to a tenth.
     */
    public static function height(float $percent): string
    {
        return 'h-' . self::tenths($percent);
    }

    /**
     * Hue in degrees for a person's initials. The mark stylesheet reads it as --h.
     */
    public static function hue(int $degrees): string
    {
        $degrees %= 360;
        if ($degrees < 0) {
            $degrees += 360;
        }

        return 'hue-' . $degrees;
    }

    /**
     * Indent for one row in the menu editor. Each step is 20 pixels.
     */
    public static function depth(int $depth): string
    {
        return 'menu-depth-' . max(0, min(self::MAX_DEPTH, $depth));
    }

    /**
     * Column count for a fixed launchpad grid.
     */
    public static function columns(int $count): string
    {
        return 'cols-' . max(1, min(self::MAX_COLUMNS, $count));
    }

    /**
     * Shares of a whole, in tenths of a percent, summing to 1000.
     *
     * A zero total returns zeros. Otherwise the tenths add to 1000, so a stacked
     * bar fills the track.
     *
     * @param list<int|float> $values
     * @return list<int>
     */
    public static function portions(array $values): array
    {
        $values = array_values($values);
        $count = count($values);
        if ($count === 0) {
            return [];
        }
        $total = 0.0;
        foreach ($values as $value) {
            $total += (float) $value;
        }
        if ($total <= 0) {
            return array_fill(0, $count, 0);
        }
        $floors = [];
        $fractions = [];
        $used = 0;
        foreach ($values as $index => $value) {
            $exact = (float) $value / $total * 1000;
            $floors[$index] = (int) floor($exact + 1e-9);
            $fractions[$index] = $exact - $floors[$index];
            $used += $floors[$index];
        }
        $left = 1000 - $used;
        arsort($fractions, SORT_NUMERIC);
        foreach (array_keys($fractions) as $index) {
            if ($left <= 0) {
                break;
            }
            $floors[$index]++;
            $left--;
        }

        return $floors;
    }

    private static function tenths(float $percent): int
    {
        if (!is_finite($percent)) {
            return 0;
        }

        return max(0, min(1000, (int) round($percent * 10)));
    }
}
