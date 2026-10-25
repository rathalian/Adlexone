<?php
use Adlexone\Html\Html;
use Adlexone\Http\View;
use Adlexone\Theme\Theme;

/**
 * Top bar of the app shell.
 *
 * @var array $nav from Adlexone\Http\TopNav::build()
 */
$sprite = Html::e(Theme::spriteHref());
$apps = $nav['apps'];
$account = $nav['account'];
?>
    <div class="brandrow">
        <a class="brandhome" href="./" aria-label="Inlay home">
        <div class="brandwrap">
            <?php View::partial('brand_lockup'); ?>
        </div>
        </a>

        <nav class="topnav" aria-label="Top">
            <?php if ($account !== null): ?>
            <button class="topnav__search" type="button" data-palette-open aria-label="Search and commands">
                <svg class="icon" aria-hidden="true"><use href="<?php echo $sprite; ?>#ic-search"></use></svg>
                <span class="topnav__search-label">Search</span>
                <kbd class="kbd" data-shortcut>Ctrl K</kbd>
            </button>
            <?php endif; ?>
            <?php if ($apps['mode'] === 'link'): ?>
            <a class="topnav__link" href="<?php echo Html::e($apps['items'][0]['href']); ?>">
                <span><?php echo Html::e($apps['items'][0]['label']); ?></span>
            </a>
            <?php elseif ($apps['mode'] === 'menu'): ?>
            <?php View::partial('menu', ['id' => 'menu-applications', 'label' => 'Applications', 'items' => $apps['items']]); ?>
            <?php endif; ?>
            <?php if ($nav['design'] !== []): ?>
            <?php View::partial('menu', ['id' => 'menu-design', 'label' => 'Configure', 'items' => $nav['design']]); ?>
            <?php endif; ?>
            <?php if ($account !== null): ?>
            <button class="topnav__icon" type="button" data-mode-toggle aria-label="Switch between light and dark" title="Switch between light and dark">
                <svg class="icon icon--sun" aria-hidden="true"><use href="<?php echo $sprite; ?>#ic-sun"></use></svg>
                <svg class="icon icon--moon" aria-hidden="true"><use href="<?php echo $sprite; ?>#ic-moon"></use></svg>
            </button>
            <div class="menu menu--account" data-menu>
                <button class="menu__button account__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="menu-account">
                    <span class="account__mark" aria-hidden="true"><?php echo Html::e($account['initials']); ?></span>
                    <span class="menu__label account__name"><?php echo Html::e($account['name']); ?></span>
                    <?php View::partial('chevron'); ?>
                </button>
                <div class="menu__panel menu__panel--account" id="menu-account" role="menu" hidden>
                    <div class="menu__account">
                        <span class="menu__account-label"><?php echo Html::e(defined('TXT_6') ? (string) TXT_6 : 'Signed in'); ?></span>
                        <span class="menu__account-name"><?php echo Html::e($account['name']); ?></span>
                    </div>
                    <div class="menu__divider" role="separator"></div>
                    <?php foreach ($account['links'] as $item): ?>
                    <a role="menuitem" class="menu__item" href="<?php echo Html::e($item['href']); ?>">
                        <svg class="icon" aria-hidden="true"><use href="<?php echo $sprite . '#' . Html::e($item['icon']); ?>"></use></svg>
                        <span><?php echo Html::e($item['label']); ?></span>
                    </a>
                    <?php endforeach; ?>
                    <button type="button" role="menuitem" class="menu__item menu__item--mode" data-mode-toggle>
                        <svg class="icon icon--sun" aria-hidden="true"><use href="<?php echo $sprite; ?>#ic-sun"></use></svg>
                        <svg class="icon icon--moon" aria-hidden="true"><use href="<?php echo $sprite; ?>#ic-moon"></use></svg>
                        <span>Switch between light and dark</span>
                    </button>
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
