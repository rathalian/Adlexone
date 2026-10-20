<?php
use Adlexone\Html\Html;
use Adlexone\Http\View;

/**
 * @var string $copy
 * @var string $art illustration URL, empty when there is none
 */
?>
<section class="launchpad__intro launchpad__intro--illustrated">
    <div class="empty-state empty-state--brand">
        <?php if ($art !== ''): ?>
        <img class="empty-state__art" src="<?php echo Html::e($art); ?>" alt="" width="160" height="120" loading="lazy">
        <?php endif; ?>
        <div class="brandwrap launchpad__brand">
            <?php View::partial('brand_lockup'); ?>
        </div>
        <p class="empty-state__copy"><?php echo Html::e($copy); ?></p>
        <div class="empty-state__actions"><a class="btn btn--primary" href="?manage=builder">Create an application</a></div>
    </div>
</section>
