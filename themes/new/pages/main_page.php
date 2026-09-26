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
            <div class="menu" data-menu aria-expanded="false">
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false">
                    Applications
                    <svg class="chev" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M5 7l5 6 5-6"/>
                    </svg>
                </button>
                <div class="menu__panel" role="menu">

                    <a role="menuitem" class="menu__item" href="/apps/helpdesk"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-helpdesk"></use></svg> Service Centre</a>
                    <a role="menuitem" class="menu__item" href="/apps/knowledge"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-knowledgebase"></use></svg> Knowledge Hub</a>
                    <a role="menuitem" class="menu__item" href="/apps/reports"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-search"></use></svg> Report Manager</a>
                    <a role="menuitem" class="menu__item" href="/apps/reports"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-launch"></use></svg> Time Manager</a>
                    <a role="menuitem" class="menu__item" href="?controller=quick_launch"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-launch"></use></svg> Quick Launch</a>
                </div>
            </div>
            <div class="menu" data-menu aria-expanded="false">
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false">
                    Manage
                    <svg class="chev" viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M5 7l5 6 5-6"/>
                    </svg>
                </button>
                <div class="menu__panel" role="menu">
                    <a role="menuitem" class="menu__item" href="?controller=administration_item_settings&amp;option=manage_fields_types"><svg class ="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-item-mgmt"></use></svg> Items and Fields</a>
                    <a role="menuitem" class="menu__item" href="?controller=administration_actions&amp;option=show_defined_actions"><svg class ="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-item-mgmt"></use></svg> Workflow</a>
                    <a role="menuitem" class="menu__item" href="?controller=administration_security&amp;option=manage_users_groups"><svg class ="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-item-mgmt"></use></svg> Security</a>
                    <a role="menuitem" class="menu__item" href="?controller=administration_settings&amp;option=adlexone_settings"><svg class ="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-system-settings"></use></svg> Settings</a>
                    <a role="menuitem" class="menu__item" href="?action=logoff"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-logoff"></use></svg> Logout</a>
                </div>
            </div>
<!--            <div class="">-->
<!--                <a href="?controller=quick_launch" class="link URL"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-launch"></use></svg>&nbsp;Launchpad</a>&nbsp;&nbsp;-->
<!--                <a href="?action=logoff" class="link URL"><svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#ic-logoff"></use></svg>&nbsp;Logoff</a>-->
<!--            </div>-->
<!---->
<!--                <div class="menu__panel" role="menu">-->
<!--                    <a role="menuitem" class="menu__item" href="?controller=quick_launch">Launchpad</a>-->
<!--                    <a role="menuitem" class="menu__item" href="?controller=administration_main&subcontroller=administration_security&option=admin_modify_user&user_id=--><?php //echo $_SESSION['access_user_id'] ?><!--">My Profile</a>-->
<!--                    <a role="menuitem" class="menu__item" href="?action=logoff">Logout</a>-->
<!--                </div>-->


<!---->
<!--            <div class="menu" data-menu aria-expanded="false">-->
<!--                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false">-->
<!--                    Items-->
<!--                    <svg class="chev" viewBox="0 0 20 20" aria-hidden="true">-->
<!--                        <path d="M5 7l5 6 5-6"/>-->
<!--                    </svg>-->
<!--                </button>-->
<!--                <div class="menu__panel" role="menu">-->
<!--                    <a role="menuitem" class="menu__item" href="/items/new">New Item</a>-->
<!--                    <a role="menuitem" class="menu__item" href="/items/browse">Browse</a>-->
<!--                    <a role="menuitem" class="menu__item" href="/items/tags">Tags</a>-->
<!--                </div>-->
<!--            </div>-->
<!---->
<!--            <div class="menu" data-menu aria-expanded="false">-->
<!--                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false">-->
<!--                    Search-->
<!--                    <svg class="chev" viewBox="0 0 20 20" aria-hidden="true">-->
<!--                        <path d="M5 7l5 6 5-6"/>-->
<!--                    </svg>-->
<!--                </button>-->
<!--                <div class="menu__panel" role="menu">-->
<!--                    <a role="menuitem" class="menu__item" href="/search/simple">Simple</a>-->
<!--                    <a role="menuitem" class="menu__item" href="/search/advanced">Advanced</a>-->
<!--                    <a role="menuitem" class="menu__item" href="/search/saved">Saved Searches</a>-->
<!--                </div>-->
<!--            </div>-->
<!---->
<!---->
<!--            <div class="menu" data-menu aria-expanded="false">-->
<!--                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false">-->
<!--                    Administration-->
<!--                    <svg class="chev" viewBox="0 0 20 20" aria-hidden="true">-->
<!--                        <path d="M5 7l5 6 5-6"/>-->
<!--                    </svg>-->
<!--                </button>-->
<!--                <div class="menu__panel" role="menu">-->
<!--                    <a role="menuitem" class="menu__item" href="/admin/users">Users</a>-->
<!--                    <a role="menuitem" class="menu__item" href="/admin/roles">Roles & Permissions</a>-->
<!--                    <a role="menuitem" class="menu__item" href="/admin/settings">Settings</a>-->
<!--                </div>-->
<!--            </div>-->
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