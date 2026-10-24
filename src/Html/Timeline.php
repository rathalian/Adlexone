<?php
declare(strict_types=1);

namespace Adlexone\Html;

/**
 * A vertical run of events, newest first, grouped under a day heading.
 */
final class Timeline
{
    /**
     * @param list<array{text: string, when: int, who?: string, href?: string, icon?: string, detail?: string}> $events
     * @param int $preview when above zero, events after this many carry is-extra so a page can fold them on a phone
     */
    public static function render(array $events, string $empty = 'Nothing has happened yet.', int $preview = 0): string
    {
        if ($events === []) {
            return '<p class="timeline__empty">' . Html::e($empty) . '</p>';
        }
        usort($events, static fn (array $left, array $right): int => $right['when'] <=> $left['when']);
        $html = '<ol class="timeline">';
        $day = '';
        foreach (array_values($events) as $index => $event) {
            $extra = $preview > 0 && $index >= $preview ? ' is-extra' : '';
            $when = (int) $event['when'];
            $label = self::day($when);
            if ($label !== $day) {
                $day = $label;
                $html .= '<li class="timeline__day' . $extra . '" aria-hidden="true">' . Html::e($label) . '</li>';
            }
            $who = trim((string) ($event['who'] ?? ''));
            $text = Html::e((string) $event['text']);
            if ((string) ($event['href'] ?? '') !== '') {
                $text = '<a href="' . Html::e((string) $event['href']) . '">' . $text . '</a>';
            }
            $detail = trim((string) ($event['detail'] ?? ''));
            $html .= '<li class="timeline__event' . $extra . '">'
                . '<span class="timeline__node">' . Charts::icon((string) ($event['icon'] ?? 'ic-note')) . '</span>'
                . '<div class="timeline__body"><p class="timeline__text">' . $text . '</p>'
                . ($detail !== '' ? '<p class="timeline__detail">' . Html::e($detail) . '</p>' : '')
                . '<p class="timeline__meta">' . ($who !== '' ? Presence::mark($who) . '<span>' . Html::e($who) . '</span>' : '')
                . '<time datetime="' . Html::e(date('c', $when)) . '" title="' . Html::e(Stamp::dayTime($when)) . '">'
                . Html::e(Presence::ago($when)) . '</time></p></div></li>';
        }

        return $html . '</ol>';
    }

    private static function day(int $when): string
    {
        $today = strtotime('today');
        if ($when >= $today) {
            return 'Today';
        }
        if ($when >= $today - 86400) {
            return 'Yesterday';
        }

        return date('l j F', $when);
    }
}
