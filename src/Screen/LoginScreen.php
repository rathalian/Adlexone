<?php
declare(strict_types=1);

namespace Adlexone\Screen;

use Adlexone\Auth\AuthRouter;

/**
 */
final class LoginScreen
{
    public static function open(): void
    {
        /**
         * Unauthenticated entry point. Bootstrap includes this when there is no session.
         * All auth behaviour lives in Adlexone\Auth\*.
         */



        AuthRouter::dispatch();
    }
}
