<?php
use Adlexone\Html\Html;
use Adlexone\Theme\Theme;

/** @var array{href: string, title: string, hint: string, icon: string, stat?: string, when?: string} $tile */
$stat = trim((string) ($tile['stat'] ?? ''));
$when = trim((string) ($tile['when'] ?? ''));
$meta = trim($stat . ($stat !== '' && $when !== '' ? ' · ' : '') . $when);
?>
<a class="launch-tile" href="<?php echo Html::e($tile['href']); ?>">
    <span class="launch-tile__icon" aria-hidden="true"><svg class="icon"><use href="<?php echo Html::e(Theme::spriteHref()) . '#' . Html::e($tile['icon']); ?>"></use></svg></span>
    <span class="launch-tile__copy">
        <span class="launch-tile__title"><?php echo Html::e($tile['title']); ?></span>
        <span class="launch-tile__hint"><?php echo Html::e($tile['hint']); ?></span>
        <?php if ($meta !== ''): ?>
            <span class="launch-tile__meta"><?php echo Html::e($meta); ?></span>
        <?php endif; ?>
    </span>
</a>
