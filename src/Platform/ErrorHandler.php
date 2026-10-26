<?php
declare(strict_types=1);

namespace Adlexone\Platform;

/**
 * Last-resort handling for an uncaught exception or a fatal error.
 *
 * Without this, a failure part way through a request leaves the visitor with a
 * blank page or, when display_errors is on, a stack trace. Both are poor: one
 * gives no guidance, the other can leak file paths and query fragments. The
 * handler logs the failure and shows a short, generic page. The message and
 * location are added only when the site is in debug mode.
 *
 * Only fatals and uncaught exceptions are handled. Notices and warnings are left
 * as they are, so existing behaviour around non-fatal errors does not change.
 */
final class ErrorHandler
{
    private static bool $handled = false;

    public static function register(): void
    {
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(\Throwable $e): void
    {
        error_log(
            'Uncaught ' . $e::class . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine()
        );
        self::renderFailure($e);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($error['type'], $fatal, true)) {
            return;
        }
        error_log(
            'Fatal error: ' . $error['message']
            . ' in ' . $error['file'] . ':' . $error['line']
        );
        self::renderFailure(null);
    }

    private static function renderFailure(?\Throwable $e): void
    {
        if (self::$handled) {
            return;
        }
        self::$handled = true;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: same-origin');
            header('X-Frame-Options: DENY');
        }

        $detail = '';
        if ($e !== null && defined('SET_DEBUG_MODE') && SET_DEBUG_MODE === 'Yes') {
            $detail = '<pre>' . htmlspecialchars(
                $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(),
                ENT_QUOTES,
                'UTF-8'
            ) . '</pre>';
        }

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<title>Something went wrong — Adlexone</title></head><body>'
            . '<h1>Something went wrong</h1>'
            . '<p>The page could not be shown because of an error on the server. '
            . 'Try again. If it keeps happening, tell the site administrator.</p>'
            . $detail
            . '</body></html>';
    }
}
