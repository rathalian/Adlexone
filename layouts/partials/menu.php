<?php
use Adlexone\Html\Html;
use Adlexone\Theme\Theme;

/**
 * Dropdown of icon links in the top bar.
 *
 * @var string $id
 * @var string $label
 * @var list<array{label: string, href: string, icon: string, current: bool}> $items
 */
$sprite = Html::e(Theme::spriteHref());
?>
            <div class="menu" data-menu>
                <button class="menu__button" type="button" aria-haspopup="menu" aria-expanded="false" aria-controls="<?php echo Html::e($id); ?>"><span class="menu__label"><?php echo Html::e($label); ?></span><?php \Adlexone\Http\View::partial('chevron'); ?></button>
                <div class="menu__panel" id="<?php echo Html::e($id); ?>" role="menu" hidden>
                    <?php foreach ($items as $item): ?>
                    <a role="menuitem" class="menu__item" href="<?php echo Html::e($item['href']); ?>"<?php echo $item['current'] ? ' aria-current="page"' : ''; ?>>
                        <svg class="icon" aria-hidden="true"><use href="<?php echo $sprite . '#' . Html::e($item['icon']); ?>"></use></svg>
                        <span><?php echo Html::e($item['label']); ?></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
