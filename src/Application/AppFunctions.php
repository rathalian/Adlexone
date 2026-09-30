<?php
declare(strict_types=1);

namespace Adlexone\Application;

use Adlexone\support\RenderViews;

/**
 * Extra screens shipped as packs under application_functions/{pack}/.
 *
 * Packs are code libraries, not managed application names. Each screen
 * registers itself and appears in Manage Applications with its pack origin.
 */
final class AppFunctions
{
    private const ORIGIN_FALLBACK = 'Application';

    private static string $currentOrigin = self::ORIGIN_FALLBACK;

    /**
     * @var array<string, array{
     *   label: string,
     *   origin: string,
     *   icon: string,
     *   config: string,
     *   default_option: string,
     *   open: callable|string
     * }>|null
     */
    private static ?array $registry = null;

    /**
     * @return array<string, array{label: string, origin: string, icon: string, config: string}>
     */
    public static function catalog(): array
    {
        self::discover();
        $out = [];
        foreach (self::$registry ?? [] as $id => $meta) {
            $out[$id] = [
                'label' => $meta['label'],
                'origin' => $meta['origin'],
                'icon' => $meta['icon'],
                'config' => $meta['config'],
            ];
        }
        return $out;
    }

    public static function has(string $id): bool
    {
        self::discover();
        return isset(self::$registry[$id]);
    }

    public static function defaultOption(string $id): string
    {
        self::discover();
        return (string) (self::$registry[$id]['default_option'] ?? '');
    }

    public static function open(string $id): void
    {
        self::discover();
        if (!isset(self::$registry[$id])) {
            RenderViews::buildResponse('This screen is missing.');
            return;
        }

        $open = self::$registry[$id]['open'];
        if (is_string($open) && function_exists($open)) {
            $open();
            return;
        }
        if (is_callable($open)) {
            $open();
            return;
        }

        RenderViews::buildResponse('This screen is missing.');
    }

    /**
     * @param array{
     *   label: string,
     *   origin?: string,
     *   icon?: string,
     *   config?: string,
     *   default_option?: string,
     *   open: callable|string
     * } $meta
     */
    public static function register(string $id, array $meta): void
    {
        if (self::$registry === null) {
            self::$registry = [];
        }
        self::$registry[$id] = [
            'label' => (string) $meta['label'],
            'origin' => (string) ($meta['origin'] ?? self::$currentOrigin),
            'icon' => (string) ($meta['icon'] ?? 'ic-launch'),
            'config' => (string) ($meta['config'] ?? 'none'),
            'default_option' => (string) ($meta['default_option'] ?? ''),
            'open' => $meta['open'],
        ];
    }

    private static function discover(): void
    {
        if (self::$registry !== null) {
            return;
        }

        self::$registry = [];
        $root = (defined('SET_INSTALL_PATH') ? (string) SET_INSTALL_PATH : '') . 'application_functions';
        if ($root === 'application_functions' || !is_dir($root)) {
            return;
        }

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $packDir) {
            self::$currentOrigin = self::packOrigin($packDir);
            foreach (glob($packDir . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
                $base = basename($file);
                if ($base === 'info.php' || str_starts_with($base, '_')) {
                    continue;
                }
                require_once $file;
            }
        }
        self::$currentOrigin = self::ORIGIN_FALLBACK;
    }

    private static function packOrigin(string $packDir): string
    {
        $metaFile = $packDir . DIRECTORY_SEPARATOR . 'info.php';
        if (is_file($metaFile)) {
            $meta = include $metaFile;
            if (is_array($meta) && trim((string) ($meta['origin'] ?? '')) !== '') {
                return (string) $meta['origin'];
            }
        }

        $folder = basename($packDir);
        $label = str_replace(['_', '-'], ' ', $folder);
        return ucwords($label);
    }
}
