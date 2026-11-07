<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Http\Page;
use Adlexone\Theme\Theme;

/**
 * Pages for an address that is not an active customer site.
 */
final class StatusScreen
{
    public static function handle(): void
    {
        Theme::defineInstallDefaults(false);
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
