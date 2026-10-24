<?php
declare(strict_types=1);

namespace Adlexone\Html;

/**
 * Small pieces of presence on a list or a record: initials, status colour, and age.
 */
final class Presence
{
    public static function tone(string $label): string
    {
        $value = strtolower(trim($label));
        if ($value === '') {
            return 'neutral';
        }
        $rules = [
            'danger' => ['rejected', 'cancelled', 'canceled', 'failed', 'lost', 'urgent', 'critical', 'blocked', 'inactive', 'hidden', 'no', 'off'],
            'warning' => ['waiting', 'on hold', 'hold', 'pending', 'review', 'overdue', 'stalled'],
            'success' => ['done', 'complete', 'completed', 'closed', 'resolved', 'paid', 'approved', 'won', 'shipped', 'fixed', 'shown', 'active', 'yes', 'on'],
            'info' => ['in progress', 'progress', 'started', 'assigned', 'draft', 'new', 'open'],
        ];
        foreach ($rules as $tone => $needles) {
            foreach ($needles as $needle) {
                if ($value === $needle || (strlen($needle) > 3 && str_contains($value, $needle))) {
                    return $tone;
                }
            }
        }

        return 'neutral';
    }

    public static function initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }
        $parts = preg_split('/\s+/u', $name) ?: [];
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : mb_strtoupper(mb_substr($name, 0, 1));
    }

    public static function mark(string $name): string
    {
        $initials = self::initials($name);
        if ($initials === '') {
            return '';
        }

        return '<span class="mark" style="--h:' . Accent::hue($name) . '" title="' . Html::e($name) . '">' . Html::e($initials) . '</span>';
    }

    /**
     * Overlapping initials for a few people, with a count for the rest.
     *
     * @param list<string> $names
     */
    public static function stack(array $names, int $limit = 4): string
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names), static fn (string $name): bool => $name !== '')));
        if ($names === []) {
            return '';
        }
        $html = '';
        foreach (array_slice($names, 0, $limit) as $name) {
            $html .= self::mark($name);
        }
        if (count($names) > $limit) {
            $html .= '<span class="mark mark--more">+' . (count($names) - $limit) . '</span>';
        }

        return '<span class="markstack">' . $html . '</span>';
    }

    public static function pill(string $label, string $tone = ''): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        $tone = $tone !== '' ? $tone : self::tone($label);
        if (!in_array($tone, ['success', 'warning', 'info', 'danger', 'neutral'], true)) {
            $tone = 'neutral';
        }

        return '<span class="pill pill--' . $tone . '">' . Html::e($label) . '</span>';
    }

    public static function ago(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }
        $delta = time() - $timestamp;
        if ($delta < 45) {
            return 'just now';
        }
        if ($delta < 3600) {
            $minutes = max(1, (int) floor($delta / 60));

            return $minutes . 'm ago';
        }
        if ($delta < 86400) {
            return (int) floor($delta / 3600) . 'h ago';
        }
        if ($delta < 86400 * 2) {
            return 'yesterday';
        }
        if ($delta < 86400 * 14) {
            return (int) floor($delta / 86400) . 'd ago';
        }
        return Stamp::day($timestamp);
    }
}
