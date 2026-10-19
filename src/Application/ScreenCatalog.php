<?php
declare(strict_types=1);

namespace Adlexone\Application;

/**
 * Ids of the built-in screens an application can open.
 *
 * Work, create, search, announcements, and application settings.
 * Wire them from Manage → Applications → Navigation or the Builder.
 * site/apps packs are only for a screen that cannot be expressed as config.
 */
final class ScreenCatalog
{
    public const ORIGIN = 'Built-in';

    public const WORK = 'items.work';
    public const CREATE = 'items.create';
    public const SEARCH_QUICK = 'search.quick';
    public const SEARCH_ADVANCED = 'search.advanced';
    public const SEARCH_SAVED = 'search.saved';
    public const SEARCH_LIST = 'search.saved_list';
    public const ANNOUNCEMENTS = 'announcements.board';
    public const SETTINGS = 'app.settings';

    /**
     * Older pack ids → current screen ids.
     *
     * @var array<string, string>
     */
    public const ALIASES = [
        'contact_centre.work' => self::WORK,
        'servicecentre.work' => self::WORK,
        'servicecentre.tickets' => self::WORK,
        'contact_centre.announcements' => self::ANNOUNCEMENTS,
        'servicecentre.announcements' => self::ANNOUNCEMENTS,
        'announcements' => self::ANNOUNCEMENTS,
        'contact_centre.settings' => self::SETTINGS,
        'servicecentre.settings' => self::SETTINGS,
    ];

    public static function resolve(string $capability): string
    {
        return self::ALIASES[$capability] ?? $capability;
    }

    /**
     * Screens the Builder can attach (id => default nav label).
     *
     * @return array<string, string>
     */
    public static function builderScreens(): array
    {
        return [
            self::WORK => 'Work',
            self::CREATE => 'New',
            self::SEARCH_LIST => 'Saved',
            self::ANNOUNCEMENTS => 'Announcements',
            self::SETTINGS => 'Settings',
        ];
    }
}
