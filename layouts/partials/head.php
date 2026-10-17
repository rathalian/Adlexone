<?php
use Adlexone\Html\Html;
use Adlexone\Theme\Theme;

/** @var string $title */
?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo Html::e($title); ?></title>
    <meta name="color-scheme" content="light">
    <link rel="icon" href="<?php echo Html::e(Theme::faviconHref()); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo Html::e(Theme::baseCssHref()); ?>">
    <link rel="stylesheet" href="<?php echo Html::e(Theme::cssHref()); ?>">
