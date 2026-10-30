<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Http\Page;

/**
 * Pages for an address that is not an active customer site.
 */
final class StatusScreen
{
    public static function handle(): void
    {
        if (!defined('SET_DEFAULT_THEME')) {
            define('SET_DEFAULT_THEME', 'inlay-slate');
        }
        if (!defined('SET_THEME')) {
            define('SET_THEME', 'inlay-slate');
        }
        $paths = Runtime::paths();
        $suspended = Runtime::mode() === Runtime::SUSPENDED;
        Page::current()->useLayout('setup', [
            'title' => $suspended ? 'Site suspended' : 'Not a customer site',
            'lead' => Runtime::notice(),
            'error' => '',
            'helpHtml' => $suspended
                ? HelpText::suspended($paths)
                : HelpText::unknown($paths, Runtime::notice()),
            'formHtml' => '',
        ]);
    }
}
