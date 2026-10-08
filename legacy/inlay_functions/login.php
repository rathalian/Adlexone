<?php
declare(strict_types=1);

/**
 * Unauthenticated entry point. Bootstrap includes this when there is no session.
 * All auth behaviour lives in Adlexone\Auth\*.
 */

use Adlexone\Auth\AuthRouter;

AuthRouter::dispatch();
