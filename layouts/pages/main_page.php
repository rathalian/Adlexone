<?php
use Adlexone\Html\Accent;
use Adlexone\Html\Html;
use Adlexone\Http\View;
use Adlexone\Theme\Theme;

/**
 * App shell: top bar, optional section navigation, page heading, body, footer.
 *
 * @var \Adlexone\Http\Page $page
 * @var array $nav
 */
$sectionNav = $page->sectionNav();
$heading = $page->title() !== '' ? $page->title() : $page->navLabel();
?>
<!doctype html>
<html lang="en">
<head>
<?php View::partial('head', ['title' => $page->documentTitle()]); ?>
</head>
<body<?php echo $page->inApplication() ? ' style="' . Html::e(Accent::style(Accent::forSlug($page->applicationSlug()))) . '"' : ''; ?>>
    <div class="brandbar appbar">
<?php View::partial('top_nav', ['nav' => $nav]); ?>
</div>
<?php echo $page->appHero(); ?>
<?php echo $sectionNav; ?>

<?php if ($sectionNav !== '' && $heading !== ''): ?>
<h1 class="pagehead__title pagehead__title--sr"><?php echo Html::e($heading); ?></h1>
<?php elseif ($page->title() !== ''): ?>
<header class="pagehead">
    <?php if ($page->eyebrow() !== ''): ?>
        <p class="pagehead__eyebrow"><?php echo Html::e($page->eyebrow()); ?></p>
    <?php endif; ?>
    <h1 class="pagehead__title"><?php echo Html::e($page->title()); ?></h1>
</header>
<?php endif; ?>

<?php echo View::toast(); ?>
<div class="wrap">
    <main class="main" id="content" tabindex="-1">
        <?php echo $page->body(); ?>
    </main>
</div>

<?php View::partial('footer'); ?>
<?php View::partial('palette'); ?>
<script src="<?php echo Html::e(Theme::jsHref()); ?>" defer></script>
<script src="<?php echo Html::e(Theme::jsHref('uplift')); ?>" defer></script>
<?php foreach ($page->scripts() as $script): ?>
<script src="<?php echo Html::e(Theme::jsHref($script)); ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
