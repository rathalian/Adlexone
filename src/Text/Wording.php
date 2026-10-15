<?php
declare(strict_types=1);

namespace Adlexone\Text;

/**
 * Language constants and per-application wording overrides.
 */
final class Wording
{
    /** @var array<string, string> controller prefix => constant prefix */
    private static array $prefixes = [
        'app_oneorzeroknowledgebase' => 'APP_KB_',
        'app_oneorzeroreportmanager' => 'APP_RM_',
        'app_oneorzerotimemanager' => 'APP_TM_',
    ];

    /**
     * An application registers the constant prefix for its controller name.
     */
    public static function register(string $controllerPrefix, string $constantPrefix): void
    {
        self::$prefixes[$controllerPrefix] = $constantPrefix;
    }

    public static function get(string $alias, string $original): mixed
    {
        if (defined($alias)) {
            return constant($alias);
        }
        $prefix = self::applicationPrefix();
        if ($prefix !== null && defined($prefix . $alias)) {
            return constant($prefix . $alias);
        }

        return defined($original) ? constant($original) : $original;
    }

    /**
     * Application wording for a shared screen. Falls back when this app has no override.
     */
    public static function application(string $suffix, string $fallback): string
    {
        $prefix = self::applicationPrefix();
        if ($prefix !== null && defined($prefix . $suffix)) {
            return (string) constant($prefix . $suffix);
        }

        return $fallback;
    }

    private static function applicationPrefix(): ?string
    {
        $controller = (string) ($_GET['controller'] ?? '');
        foreach (self::$prefixes as $needle => $prefix) {
            if (str_starts_with($controller, $needle)) {
                return $prefix;
            }
        }

        return null;
    }
}
