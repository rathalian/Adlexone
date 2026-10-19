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

    public static function flash(string $message, string $tone = 'success'): void
    {
        $message = trim($message);
        if ($message === '') {
            return;
        }
        $_SESSION['_flash'] = ['message' => $message, 'tone' => $tone];
    }

    public static function toast(): string
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        if (!is_array($flash)) {
            return '';
        }
        $message = trim((string) ($flash['message'] ?? ''));
        if ($message === '') {
            return '';
        }
        $tone = (string) ($flash['tone'] ?? 'success');
        $art = Theme::illustrationHref($tone === 'danger' ? 'confirm-pause' : 'confirm-done');
        $image = $art !== ''
            ? '<img class="toast__art" src="' . Html::e($art) . '" alt="" width="72" height="54">'
            : '';

        return '<div class="toast toast--' . Html::e($tone) . '" role="status" data-toast>'
            . $image
            . '<p class="toast__text">' . Html::e($message) . '</p>'
            . '<button type="button" class="toast__close" data-toast-close>Close</button>'
            . '</div>';
    }

    public static function respond(string $text, string $detail = ''): void
    {
        $message = trim($text);
        $page = Page::current();
        $page->suggestTitle($message);

        $detail = trim($detail);
        $href = self::continueHref($detail);
        if ($href !== null && self::isSuccess($message) && !headers_sent()) {
            self::flash($message);
            header('Location: ' . Router::absolute($href));
            exit;
        }

        $follow = $detail !== '' ? self::formatDetail($detail) : self::backLink();
        if ($follow === '') {
            $follow = Blocks::link('./', 'Home', '', 'btn btn--primary btn--sm');
        }
        $artName = self::isSuccess($message) ? 'confirm-done' : 'confirm-pause';
        $art = Theme::illustrationHref($artName);
        $image = $art !== ''
            ? '<img class="empty-state__art" src="' . Html::e($art) . '" alt="" width="160" height="120">'
            : '';
        $body = '<div class="confirm" role="status">'
            . $image
            . '<p class="empty-state__title">' . Html::e($message) . '</p>'
            . '<div class="response">' . $follow . '</div>'
            . '</div>';

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

    private static function continueHref(string $detail): ?string
    {
        if (!preg_match('/href="([^"]+)"/', $detail, $match)) {
            return null;
        }
        $href = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
        if ($href === '' || str_starts_with($href, 'javascript:') || preg_match('#^https?://#i', $href) === 1) {
            return null;
        }

        return $href;
    }

    private static function isSuccess(string $text): bool
    {
        $value = strtolower($text);
        foreach (['fail', 'not found', 'not here', 'not available', 'not on', 'does not', 'invalid', 'problem', 'error', 'missing', 'cannot', "can't", 'choose', 'enter ', 'exceeds', 'unknown', 'empty file', 'did not', 'partially', 'before '] as $needle) {
            if (str_contains($value, $needle)) {
                return false;
            }
        }
        foreach (['success', 'updated', 'saved', 'created', 'added', 'deleted', 'removed', 'completed'] as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
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
