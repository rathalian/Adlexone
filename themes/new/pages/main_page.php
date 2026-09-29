<?php
ob_start();
controller();
$pageBody = ob_get_clean();
$hasSectionNav = defined('APP_SECTION_NAV') && APP_SECTION_NAV !== '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars((defined('PAGE_TITLE') && PAGE_TITLE !== '' ? PAGE_TITLE . ' — Adlexone' : 'Adlexone'), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="color-scheme" content="light">
    <link rel="icon" href="themes/new/assets/brand/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="themes/new/css/style.css">
</head>
<body>
    <div class="brandbar appbar">
    <div class="brandrow">
        <a class="brandhome" href="index.php" aria-label="Home">
        <div class="brandwrap">
            <div class="logoA" aria-hidden="true">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3 L20 21 H16 L14.6 18 H9.4 L8 21 H4 L12 3 Z M10.7 14h2.6L12 9.8 10.7 14Z"
                          fill="white"/>
                </svg>
            </div>
            <div class="brandtext"><span class="title-static">Adlexone</span></div>
        </div>
        </a>

        <nav class="topnav" aria-label="Top">
            <?php
            $currentController = (string)($_GET['controller'] ?? '');
            $matchesController = static function (string $controller) use ($currentController): bool {
                if ($currentController === $controller) {
                    return true;
                }
                $prefix = preg_replace('/_(main|manage)$/', '', $controller);
                return is_string($prefix) && $prefix !== $controller && str_starts_with($currentController, $prefix);
            };
            $applications = \Adlexone\Application\ApplicationStore::menuItems();
            $quickLaunch = ['label' => 'Home', 'href' => 'index.php', 'icon' => 'ic-launch', 'controller' => 'quick_launch'];
            $manageItems = [];
            if (\Adlexone\Auth\Access::can(\Adlexone\Auth\Permission::ADMIN_SETTINGS)) {
                $manageItems[] = ['label' => 'Applications', 'href' => 'index.php?manage=applications', 'icon' => 'ic-launch', 'controller' => 'administration_applications'];
            }
            $manageItems = array_merge($manageItems, [
                ['label' => 'Items and Fields', 'href' => 'index.php?manage=items&option=manage_fields', 'icon' => 'ic-manage-fields', 'controller' => 'administration_item_settings'],
                ['label' => 'Workflow', 'href' => 'index.php?manage=workflow&option=show_defined_actions', 'icon' => 'ic-manage-actions', 'controller' => 'administration_actions'],
                ['label' => 'Security', 'href' => 'index.php?manage=security&option=manage_users', 'icon' => 'ic-manage-users', 'controller' => 'administration_security'],
                ['label' => 'Settings', 'href' => 'index.php?manage=settings&option=adlexone_settings', 'icon' => 'ic-system-settings', 'controller' => 'administration_settings'],
            ]);
            $renderMenuItem = static function (array $item) use ($matchesController, $currentController): string {
                $onApplication = $currentController === 'application' && (string) ($item['app'] ?? '') !== '';
                $current = $onApplication
                    ? ((string) ($item['app'] ?? '') === (string) ($_GET['app'] ?? '') ? ' aria-current="page"' : '')
                    : ($matchesController((string) ($item['controller'] ?? '')) ? ' aria-current="page"' : '');
                return '<a role="menuitem" class="menu__item" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '"' . $current . '>'
                    . '<svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#' . htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') . '"></use></svg>'
                    . '<span>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span></a>';
            };
            $appLabel = 'Applications';
            $manageLabel = 'Manage';
            $chevron = '<svg class="chev" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7l5 6 5-6"/></svg>';
            ?>
            <div class="menu" data-menu>
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-applications"><span class="menu__label"><?php echo htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8'); ?></span><?php echo $chevron; ?></button>
                <div class="menu__panel" id="menu-applications" role="menu" hidden>
                    <?php foreach ($applications as $item) {
                        echo $renderMenuItem($item);
                    } ?>
                    <div class="menu__divider" role="separator"></div>
                    <?php echo $renderMenuItem($quickLaunch); ?>
                </div>
            </div>
            <div class="menu" data-menu>
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-manage"><span class="menu__label"><?php echo htmlspecialchars($manageLabel, ENT_QUOTES, 'UTF-8'); ?></span><?php echo $chevron; ?></button>
                <div class="menu__panel" id="menu-manage" role="menu" hidden>
                    <?php foreach ($manageItems as $item) {
                        echo $renderMenuItem($item);
                    } ?>
                </div>
            </div>
            <?php
            $signedInName = trim((string)($_SESSION['access_user_name'] ?? ''));
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
                        <svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-logoff"></use></svg>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
            <?php else: ?>
            <a class="topnav__logout" href="?action=logoff" aria-label="Logout">
                <svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-logoff"></use></svg>
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
$sectionLabel = defined('APPLICATION_NAV_LABEL') ? (string) APPLICATION_NAV_LABEL : '';
$titleRepeatsSection = $hasSectionNav
    && defined('PAGE_TITLE')
    && PAGE_TITLE !== ''
    && $sectionLabel !== ''
    && PAGE_TITLE === $sectionLabel;
?>
<?php if (defined('PAGE_TITLE') && PAGE_TITLE !== '' && $titleRepeatsSection): ?>
<h1 class="pagehead__title pagehead__title--sr"><?php echo htmlspecialchars(PAGE_TITLE, ENT_QUOTES, 'UTF-8'); ?></h1>
<?php elseif (defined('PAGE_TITLE') && PAGE_TITLE !== ''): ?>
<header class="pagehead">
    <?php if (!$hasSectionNav && defined('PAGE_EYEBROW') && PAGE_EYEBROW !== ''): ?>
        <p class="pagehead__eyebrow"><?php echo htmlspecialchars(PAGE_EYEBROW, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <h1 class="pagehead__title"><?php echo htmlspecialchars(PAGE_TITLE, ENT_QUOTES, 'UTF-8'); ?></h1>
</header>
<?php endif; ?>

<!-- Main content area rendered by the controller -->
<?php echo $pageBody; ?>

<footer class="app-footer">
    <span>Adlexone <?php echo htmlspecialchars((string)VERSION, ENT_QUOTES, 'UTF-8'); ?></span>
    <a href="https://www.adlexone.com" target="_blank" rel="noopener">&copy;<?php echo date('Y'); ?> Adlexone</a>
</footer>
<script src="themes/new/js/app.js" defer></script>
</body>
</html>