<?php
declare(strict_types=1);

namespace Adlexone\support;

/**
 * Writes an RSS document for a saved search.
 */
class RssFeed
{
    /**
     * @param list<string> $itemTitles
     * @param list<string> $itemLinks
     */
    public static function send($dateFormat, $channelName, $channelDescription, $channelUrl, $itemTitles, $itemLinks): void
    {
        header('Content-Type: application/rss+xml; charset=UTF-8');
        $now = date((string) $dateFormat);

        $html = "<?xml version=\"1.0\"?>
            <rss version=\"2.0\">
                <channel>
                    <title>$channelName</title>
                    <link>$channelUrl</link>
                    <description>$channelDescription</description>
                    <language>en-us</language>
                    <pubDate>$now</pubDate>
                    <lastBuildDate>$now</lastBuildDate>";
        $i = 0;
        foreach ($itemTitles as $title) {
            $html .= '<item><title>' . htmlentities((string) $title) . '</title>
                            <link>' . htmlentities((string) ($itemLinks[$i] ?? '')) . '</link>
                    </item>';
            $i++;
        }
        $html .= '</channel>
                </rss>';
        echo $html;
    }
}
