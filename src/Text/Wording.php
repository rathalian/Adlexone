<?php
declare(strict_types=1);

namespace Adlexone\Text;

/**
 * Language constants and per-application wording overrides.
 */
final class Wording
{
    /** @var array<string, string> application slug => constant prefix */
    private static array $prefixes = [];

    /**
     * An application registers the constant prefix for its slug.
     */
    public static function register(string $slug, string $constantPrefix): void
    {
        self::$prefixes[$slug] = $constantPrefix;
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
        $slug = \Adlexone\Http\Page::current()->applicationSlug();
        if ($slug === '') {
            return null;
        }

        return self::$prefixes[$slug] ?? null;
    }
}
