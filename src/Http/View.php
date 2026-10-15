<?php
declare(strict_types=1);

namespace Adlexone\Http;

use Adlexone\Html\Blocks;
use Adlexone\Theme\Theme;

/**
 * Renders a layout page. Screens pass body HTML; the document shell lives in layouts/pages/.
 */
final class View
{
    public static function render(string $page): void
    {
        $path = Theme::pagePath($page);
        if (!is_file($path)) {
            echo '<!-- missing layout page: ' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8') . ' -->';
            return;
        }
        include $path;
    }

    public static function body(string $html, string $page = 'main_page_content'): void
    {
        if (!defined('BODY_CONTENT')) {
            define('BODY_CONTENT', $html);
        }
        self::render($page);
    }

    /**
     * Open a screen by name. Kept for older action scripts.
     */
    public static function includeController(?string $controller, string $defaultPage): void
    {
        $controller = basename($controller ?? $defaultPage);
        if (str_contains($controller, ':')) {
            [$controller, $appSlug] = explode(':', $controller, 2);
            if (trim((string) ($_GET['app'] ?? '')) === '') {
                $_GET['app'] = $appSlug;
            }
        }
        if ($controller === '') {
            $controller = $defaultPage;
        }

        Router::open($controller);
    }

    public static function respond(string $text, string $detail = ''): void
    {
        $message = trim($text);
        if (!defined('PAGE_TITLE')) {
            define('PAGE_TITLE', $message);
        }

        $detail = trim($detail);
        $follow = $detail !== '' ? self::formatDetail($detail) : self::backLink();
        $body = '<p class="response__message">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($follow !== '') {
            $body .= '<div class="response" role="status">' . $follow . '</div>';
        }

        if (defined('ADLEXONE_PAGE_SHELL')) {
            self::body($body, 'main_page_content');
            return;
        }

        $responseTitle = $message !== '' ? $message : 'Inlay';
        $responseBody = $body;
        $path = Theme::pagePath('response');
        if (!is_file($path)) {
            echo '<!-- missing layout page: response -->';
            return;
        }
        include $path;
    }

    private static function formatDetail(string $detail): string
    {
        if (str_contains($detail, '<a ')) {
            return $detail;
        }

        return '<p class="response__detail">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p>';
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
