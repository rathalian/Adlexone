<?php
use Adlexone\Html\Html;
use Adlexone\Http\View;

/**
 * @var string $label
 * @var list<array{href: string, title: string, hint: string, icon: string}> $tiles
 */
?>
<section class="launchpad__group" aria-label="<?php echo Html::e($label); ?>">
    <h2 class="launchpad__heading"><?php echo Html::e($label); ?></h2>
    <div class="launchpad__grid">
        <?php foreach ($tiles as $tile) {
            View::partial('launch_tile', ['tile' => $tile]);
        } ?>
    </div>
</section>
