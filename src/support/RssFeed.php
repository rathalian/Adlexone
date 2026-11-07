<?php
declare(strict_types=1);

namespace Adlexone\support;

use Adlexone\Html\Html;

/**
 * Writes an RSS document for a saved search.
 */
class RssFeed
{
    /**
     * @param list<string> $itemTitles
     * @param list<string> $itemLinks
     */
    public static function send(
        string $dateFormat,
        string $channelName,
        string $channelDescription,
        string $channelUrl,
        array $itemTitles,
        array $itemLinks
    ): void {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        $now = Html::e(date($dateFormat));

        $html = '<?xml version="1.0"?>'
            . '<rss version="2.0"><channel>'
            . '<title>' . Html::e($channelName) . '</title>'
            . '<link>' . Html::e($channelUrl) . '</link>'
            . '<description>' . Html::e($channelDescription) . '</description>'
            . '<language>en-us</language>'
            . '<pubDate>' . $now . '</pubDate>'
            . '<lastBuildDate>' . $now . '</lastBuildDate>';
        foreach ($itemTitles as $index => $title) {
            $html .= '<item><title>' . Html::e($title) . '</title>'
                . '<link>' . Html::e($itemLinks[$index] ?? '') . '</link></item>';
        }
        $html .= '</channel></rss>';
        echo $html;
    }
}
