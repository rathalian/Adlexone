<?php
use Adlexone\Html\Html;

/** @var string|null $descriptor */
$descriptor = trim((string) ($descriptor ?? ''));
$siteName = defined('SET_SITE_NAME') ? trim((string) SET_SITE_NAME) : '';
$brandTitle = $siteName !== '' ? $siteName : 'Adlexone';
?>
<span class="logoA">
    <svg class="logo-mark" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect x="6" y="6" width="52" height="52" rx="11" fill="none" class="logo-mark__stroke" stroke-width="4"/>
        <path d="M15 20H45" fill="none" class="logo-mark__stroke" stroke-width="5" stroke-linecap="round"/>
        <circle cx="50" cy="20" r="2.5" class="logo-mark__stroke-fill"/>
        <path d="M15 32H34" fill="none" class="logo-mark__accent" stroke-width="5" stroke-linecap="round"/>
        <path d="M40 31.5L44.5 36L52 27.5" fill="none" class="logo-mark__accent" stroke-width="4.5" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M15 45H39" fill="none" class="logo-mark__stroke" stroke-width="5" stroke-linecap="round"/>
        <circle cx="46" cy="45" r="2.5" class="logo-mark__stroke-fill"/>
    </svg>
</span>
<span class="brandtext">
    <span class="title-static"><?php echo Html::e($brandTitle); ?></span>
    <?php if ($descriptor !== ''): ?>
        <span class="brand-descriptor"><?php echo Html::e($descriptor); ?></span>
    <?php endif; ?>
</span>
