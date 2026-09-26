<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adlexone</title>
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="themes/new/assets/brand/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="themes/new/css/style.css">
</head>
<body>
    <div class="brandbar">
    <div class="brandrow">
        <div class="brandwrap">
            <div class="logoA" aria-label="Adlexone logo">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3 L20 21 H16 L14.6 18 H9.4 L8 21 H4 L12 3 Z M10.7 14h2.6L12 9.8 10.7 14Z"
                          fill="white"/>
                </svg>
            </div>
            <div class="brandtext"><span class="title-static">dlexone</span></div>
        </div>

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
            $applications = [
                ['label' => 'Service Centre', 'href' => '?controller=app_oneorzerohelpdesk_main', 'icon' => 'ic-helpdesk', 'controller' => 'app_oneorzerohelpdesk_main'],
                ['label' => 'Knowledge Hub', 'href' => '?controller=app_oneorzeroknowledgebase_main', 'icon' => 'ic-knowledgebase', 'controller' => 'app_oneorzeroknowledgebase_main'],
                ['label' => 'Report Manager', 'href' => '?controller=app_oneorzeroreportmanager_main', 'icon' => 'ic-search', 'controller' => 'app_oneorzeroreportmanager_main'],
                ['label' => 'Time Manager', 'href' => '?controller=app_oneorzerotimemanager_main', 'icon' => 'ic-time', 'controller' => 'app_oneorzerotimemanager_main'],
            ];
            $quickLaunch = ['label' => 'Quick Launch', 'href' => '?controller=quick_launch', 'icon' => 'ic-launch', 'controller' => 'quick_launch'];
            $manageItems = [
                ['label' => 'Items and Fields', 'href' => '?controller=administration_item_settings&option=manage_fields', 'icon' => 'ic-manage-fields', 'controller' => 'administration_item_settings'],
                ['label' => 'Workflow', 'href' => '?controller=administration_actions&option=show_defined_actions', 'icon' => 'ic-manage-actions', 'controller' => 'administration_actions'],
                ['label' => 'Security', 'href' => '?controller=administration_security&option=manage_users_groups', 'icon' => 'ic-manage-users', 'controller' => 'administration_security'],
                ['label' => 'Settings', 'href' => '?controller=administration_settings&option=adlexone_settings', 'icon' => 'ic-system-settings', 'controller' => 'administration_settings'],
            ];
            $renderMenuItem = static function (array $item) use ($matchesController): string {
                $current = $matchesController($item['controller']) ? ' aria-current="page"' : '';
                return '<a role="menuitem" class="menu__item" href="' . htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') . '"' . $current . '>'
                    . '<svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#' . htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') . '"></use></svg>'
                    . '<span>' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</span></a>';
            };
            $appLabel = 'Applications';
            foreach (array_merge($applications, [$quickLaunch]) as $item) {
                if ($matchesController($item['controller'])) {
                    $appLabel = $item['label'];
                }
            }
            $manageLabel = 'Manage';
            foreach ($manageItems as $item) {
                if ($matchesController($item['controller'])) {
                    $manageLabel = $item['label'];
                }
            }
            $chevron = '<svg class="chev" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 7l5 6 5-6"/></svg>';
            ?>
            <div class="menu" data-menu>
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-applications"><?php echo htmlspecialchars($appLabel, ENT_QUOTES, 'UTF-8'); ?><?php echo $chevron; ?></button>
                <div class="menu__panel" id="menu-applications" role="menu" hidden>
                    <?php foreach ($applications as $item) {
                        echo $renderMenuItem($item);
                    } ?>
                    <div class="menu__divider" role="separator"></div>
                    <?php echo $renderMenuItem($quickLaunch); ?>
                </div>
            </div>
            <div class="menu" data-menu>
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-manage"><?php echo htmlspecialchars($manageLabel, ENT_QUOTES, 'UTF-8'); ?><?php echo $chevron; ?></button>
                <div class="menu__panel" id="menu-manage" role="menu" hidden>
                    <?php foreach ($manageItems as $item) {
                        echo $renderMenuItem($item);
                    } ?>
                </div>
            </div>
            <a class="topnav__logout" href="?action=logoff">
                <svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-logoff"></use></svg>
                Logout
            </a>
        </nav>
    </div>
<!--    <div class="underline" aria-hidden="true"></div>-->
</div>

<!-- Main content area rendered by the controller including the left navigation card and the body card    -->
<?php echo controller(); ?>

    <p class="modal-footer">
        &nbsp;
    </p>
    <p align="center" class="modal-footer">
        Adlexone Version <?php echo VERSION; ?>
        <br>
        <a href="http://www.adlexone.com" target="_blank">&copy;2025 Adlexone</a></span>
    </p>
<script src="themes/new/js/app.js" defer></script>
</body>
</html>