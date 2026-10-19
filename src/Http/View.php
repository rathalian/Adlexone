<?php
declare(strict_types=1);

namespace Adlexone\Http;

use Adlexone\Html\Blocks;
use Adlexone\Html\Html;
use Adlexone\Theme\Theme;

/**
 * Loads templates from layouts/ and turns a message into a response page.
 */
final class View
{
    /**
     * @param array<string, mixed> $vars
     */
    public static function render(string $page, array $vars = []): void
    {
        self::load(Theme::pagePath($page), $page, $vars);
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function partial(string $name, array $vars = []): void
    {
        self::load(Theme::partialPath($name), 'partial ' . $name, $vars);
    }

    /**
     * @param array<string, mixed> $vars
     */
    public static function partialToString(string $name, array $vars = []): string
    {
        ob_start();
        self::partial($name, $vars);

        return (string) ob_get_clean();
    }

    public static function respond(string $text, string $detail = ''): void
    {
        $message = trim($text);
        $page = Page::current();
        $page->suggestTitle($message);

        $detail = trim($detail);
        $follow = $detail !== '' ? self::formatDetail($detail) : self::backLink();
        if ($follow === '') {
            $follow = Blocks::link('./', 'Home', '', 'btn btn--primary btn--sm');
        }
        $visibleHeading = trim($page->sectionNav()) === ''
            && strcasecmp(trim(html_entity_decode(strip_tags($page->title()), ENT_QUOTES, 'UTF-8')), $message) === 0;
        $body = $visibleHeading ? '' : '<p class="response__message">' . Html::e($message) . '</p>';
        $body .= '<div class="response" role="status">' . $follow . '</div>';

        if (!$page->hasBody()) {
            $page->setBody($body);
        }
    }

    /**
     * @param array<string, mixed> $vars
     */
    private static function load(string $path, string $label, array $vars): void
    {
        if (!is_file($path)) {
            echo '<!-- missing layout page: ' . Html::e($label) . ' -->';
            return;
        }
        (static function () use ($path, $vars): void {
            extract($vars, EXTR_SKIP);
            include $path;
        })();
    }

    private static function formatDetail(string $detail): string
    {
        if (str_contains($detail, '<a ')) {
            return $detail;
        }

        return '<p class="response__detail">' . Html::e($detail) . '</p>';
    }

    private static function backLink(): string
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return '';
        }
        $host = (string) ($parts['host'] ?? '');
        $serverHost = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && strcasecmp($host, preg_replace('/:\d+$/', '', $serverHost) ?? $serverHost) !== 0) {
            return '';
        }
        $path = (string) ($parts['path'] ?? '');
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = str_replace('\\', '/', dirname($script));
        $prefix = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
        $appPaths = [$prefix . '/', $prefix . '/index.php'];
        if (!in_array($path, $appPaths, true)) {
            return '';
        }
        $href = isset($parts['query']) ? '?' . $parts['query'] : './';
        $label = defined('TXT_31') ? TXT_31 : 'Back';

        return Blocks::link($href, $label, '', 'btn btn--primary btn--sm');
    }
}
