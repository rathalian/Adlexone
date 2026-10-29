<?php
use Adlexone\Html\Html;
use Adlexone\Theme\Theme;

/** @var string $title */
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="csrf-token" content="<?php echo Html::e(\Adlexone\Auth\Csrf::token()); ?>">
    <title><?php echo Html::e($title); ?></title>
    <script src="<?php echo Html::e(Theme::jsHref('mode')); ?>"></script>
    <link rel="icon" href="<?php echo Html::e(Theme::faviconHref()); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo Html::e(Theme::baseCssHref()); ?>">
    <link rel="stylesheet" href="<?php echo Html::e(Theme::layoutCssHref('uplift')); ?>">
    <link rel="stylesheet" href="<?php echo Html::e(Theme::layoutCssHref('scale')); ?>">
    <link rel="stylesheet" href="<?php echo Html::e(Theme::cssHref()); ?>">
