<?php
use Adlexone\Theme\Theme;

if (!defined('ADLEXONE_PAGE_SHELL')) {
    define('ADLEXONE_PAGE_SHELL', true);
}
ob_start();
\Adlexone\Http\Router::open(\Adlexone\Http\Router::requested());
$pageBody = ob_get_clean();
$hasSectionNav = defined('APP_SECTION_NAV') && APP_SECTION_NAV !== '';
$sprite = htmlspecialchars(Theme::spriteHref(), ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars((defined('PAGE_TITLE') && PAGE_TITLE !== '' ? PAGE_TITLE . ' — Inlay' : 'Inlay'), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="color-scheme" content="light">
    <link rel="icon" href="<?php echo htmlspecialchars(Theme::faviconHref(), ENT_QUOTES, 'UTF-8'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::baseCssHref(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::cssHref(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <div class="brandbar appbar">
    <div class="brandrow">
        <a class="brandhome" href="./" aria-label="Inlay home">
        <div class="brandwrap">
            <?php include Theme::layoutFs() . 'partials' . DIRECTORY_SEPARATOR . 'brand_lockup.php'; ?>
        </div>
        </a>

        <nav class="topnav" aria-label="Top">
            <?php
            $currentController = (string) ($_GET['controller'] ?? '');
            $matchesController = static function (string $controller) use ($currentController): bool {
                if ($currentController === $controller) {
                    return true;
                }
                $prefix = preg_replace('/_(main|manage)$/', '', $controller);
                return is_string($prefix) && $prefix !== $controller && str_starts_with($currentController, $prefix);
            };
            $applications = \Adlexone\Application\ApplicationStore::menuItems();
            $inApplication = defined('APPLICATION_SLUG') && (string) APPLICATION_SLUG !== '';
            $designItems = [];
            if (\Adlexone\Auth\Access::canAll(\Adlexone\Auth\Permission::ADMIN_SETTINGS, \Adlexone\Auth\Permission::ADMIN_ITEMS)) {
                $designItems[] = ['label' => 'Builder', 'href' => '?manage=builder', 'icon' => 'ic-itemtype-add', 'controller' => 'manage_builder'];
            }
            if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SETTINGS)) {
                $designItems[] = ['label' => 'Applications', 'href' => '?manage=applications', 'icon' => 'ic-launch', 'controller' => 'manage_applications'];
            }
            if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_ITEMS)) {
                $designItems[] = ['label' => 'Fields', 'href' => '?manage=fields', 'icon' => 'ic-manage-fields', 'controller' => 'manage_fields'];
                $designItems[] = ['label' => 'Item types', 'href' => '?manage=item-types', 'icon' => 'ic-manage-item-types', 'controller' => 'manage_item_types'];
            }
            if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_ACTIONS)) {
                $designItems[] = ['label' => 'Rules', 'href' => '?manage=rules', 'icon' => 'ic-manage-actions', 'controller' => 'manage_actions'];
            }
            if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SECURITY)) {
                $designItems[] = ['label' => 'Security', 'href' => '?manage=security&option=manage_users', 'icon' => 'ic-manage-users', 'controller' => 'manage_security'];
            }
            if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SETTINGS)) {
                $designItems[] = ['label' => 'Settings', 'href' => '?manage=settings&option=adlexone_settings', 'icon' => 'ic-system-settings', 'controller' => 'manage_settings'];
            } elseif (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SYSTEM)) {
                $designItems[] = ['label' => 'Settings', 'href' => '?manage=settings&option=sign_in_settings', 'icon' => 'ic-system-settings', 'controller' => 'manage_settings'];
            }
            $renderMenuItem = static function (array $item) use ($matchesController, $sprite): string {
                $onApplication = ((string) ($_GET['controller'] ?? '') === 'application') && (string) ($item['app'] ?? '') !== '';
                $current = $onApplication
                    ? ((string) ($item['app'] ?? '') === (string) ($_GET['app'] ?? '') ? ' aria-current="page"' : '')
                    : ($matchesController((string) ($item['controller'] ?? '')) ? ' aria-current="page"' : '');
                return '<a role="menuitem" class="menu__item" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '"' . $current . '>'
                    . '<svg class="icon" aria-hidden="true"><use href="' . $sprite . '#' . htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') . '"></use></svg>'
                    . '<span>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span></a>';
            };
            $chevron = '<svg class="chev" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7l5 6 5-6"/></svg>';
            $showAppMenu = count($applications) > 1 || (!$inApplication && $applications !== []);
            ?>
            <?php if ($showAppMenu && count($applications) === 1): ?>
            <a class="topnav__link" href="<?php echo htmlspecialchars((string) $applications[0]['href'], ENT_QUOTES, 'UTF-8'); ?>">
                <span><?php echo htmlspecialchars((string) $applications[0]['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <?php elseif ($showAppMenu): ?>
            <div class="menu" data-menu>
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-applications"><span class="menu__label">Apps</span><?php echo $chevron; ?></button>
                <div class="menu__panel" id="menu-applications" role="menu" hidden>
                    <?php foreach ($applications as $item) {
                        echo $renderMenuItem($item);
                    } ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if ($designItems !== []): ?>
            <div class="menu" data-menu>
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-design"><span class="menu__label">Design</span><?php echo $chevron; ?></button>
                <div class="menu__panel" id="menu-design" role="menu" hidden>
                    <?php foreach ($designItems as $item) {
                        echo $renderMenuItem($item);
                    } ?>
                </div>
            </div>
            <?php endif; ?>
            <?php
            $signedInName = trim((string) ($_SESSION['access_user_name'] ?? ''));
            $initials = '';
            foreach (array_slice(preg_split('/\s+/', $signedInName) ?: [], 0, 2) as $part) {
                if ($part !== '') {
                    $initials .= strtoupper(substr($part, 0, 1));
                }
            }
            if ($signedInName !== ''):
            ?>
            <div class="menu menu--account" data-menu>
                <button class="menu__button account__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-account">
                    <span class="account__mark" aria-hidden="true"><?php echo htmlspecialchars($initials !== '' ? $initials : 'A', ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="menu__label account__name"><?php echo htmlspecialchars($signedInName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php echo $chevron; ?>
                </button>
                <div class="menu__panel menu__panel--account" id="menu-account" role="menu" hidden>
                    <div class="menu__account">
                        <span class="menu__account-label"><?php echo htmlspecialchars(defined('TXT_6') ? (string) TXT_6 : 'Signed in', ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="menu__account-name"><?php echo htmlspecialchars($signedInName, ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="menu__divider" role="separator"></div>
                    <a role="menuitem" class="menu__item" href="?action=logoff">
                        <svg class="icon" aria-hidden="true"><use href="<?php echo $sprite; ?>#ic-logoff"></use></svg>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            <?php else: ?>
            <a class="topnav__logout" href="?action=logoff" aria-label="Logout">
                <svg class="icon" aria-hidden="true"><use href="<?php echo $sprite; ?>#ic-logoff"></use></svg>
                <span class="topnav__logout-label">Logout</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php if ($hasSectionNav) {
        echo APP_SECTION_NAV;
    } ?>
</div>

<?php
$accessibleTitle = '';
if (defined('PAGE_TITLE') && PAGE_TITLE !== '') {
    $accessibleTitle = (string) PAGE_TITLE;
} elseif (defined('APPLICATION_NAV_LABEL') && APPLICATION_NAV_LABEL !== '') {
    $accessibleTitle = (string) APPLICATION_NAV_LABEL;
}
?>
<?php if ($hasSectionNav && $accessibleTitle !== ''): ?>
<h1 class="pagehead__title pagehead__title--sr"><?php echo htmlspecialchars($accessibleTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php elseif (defined('PAGE_TITLE') && PAGE_TITLE !== ''): ?>
<header class="pagehead">
    <?php if (defined('PAGE_EYEBROW') && PAGE_EYEBROW !== ''): ?>
        <p class="pagehead__eyebrow"><?php echo htmlspecialchars(PAGE_EYEBROW, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <h1 class="pagehead__title"><?php echo htmlspecialchars(PAGE_TITLE, ENT_QUOTES, 'UTF-8'); ?></h1>
</header>
<?php endif; ?>

<?php echo $pageBody; ?>

<?php include Theme::layoutFs() . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
<script src="<?php echo htmlspecialchars(Theme::jsHref(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
