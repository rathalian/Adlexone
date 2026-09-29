<?php
declare(strict_types=1);

use Adlexone\Application\ApplicationStore;
use Adlexone\Application\Capabilities;
use Adlexone\Auth\Access;
use Adlexone\support\RenderViews;

$slug = trim((string) ($_GET['app'] ?? ''));
if ($slug === '') {
    $slug = trim((string) ($_SESSION['application_slug'] ?? ''));
}

$app = $slug === '' ? null : ApplicationStore::findBySlug($slug);
if ($app === null || !$app['enabled']) {
    RenderViews::buildResponse('This application is not available.');
    return;
}
if ($app['entry_mode'] === 'legacy' && $app['legacy_controller'] !== '') {
    header('Location: index.php?controller=' . rawurlencode((string) $app['legacy_controller']));
    exit;
}
if (!Access::can((string) $app['permission'])) {
    Access::deny();
}

$visible = [];
foreach (ApplicationStore::navigation((int) $app['application_id']) as $link) {
    if (Capabilities::linkVisible($link)) {
        $visible[] = $link;
    }
}
if ($visible === []) {
    RenderViews::buildResponse('This application has no navigation yet. Add links in Manage.');
    return;
}

$requested = (int) ($_GET['nav'] ?? 0);
$current = $visible[0];
if ($requested > 0) {
    $current = null;
    foreach ($visible as $link) {
        if ((int) $link['nav_id'] === $requested) {
            $current = $link;
            break;
        }
    }
    if ($current === null) {
        Access::deny();
    }
} else {
    $sessionNav = (int) ($_SESSION['application_nav_id'] ?? 0);
    foreach ($visible as $link) {
        if ((int) $link['nav_id'] === $sessionNav) {
            $current = $link;
            break;
        }
    }
}
$_SESSION['application_nav_id'] = (int) $current['nav_id'];

$_GET['nav'] = (string) $current['nav_id'];
Capabilities::sectionNavigation($app, $visible);

$engine = \Adlexone\Http\Router::engine();
if ($engine !== null) {
    Capabilities::forwardShared($app, (int) $current['nav_id'], $engine);
    return;
}

Capabilities::open($app, $current);
