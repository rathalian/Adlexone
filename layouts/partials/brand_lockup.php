<?php
use Adlexone\Html\Html;

/** @var string|null $descriptor */
$descriptor = trim((string) ($descriptor ?? ''));
?>
<span class="logoA">
    <svg class="logo-mark" viewBox="8 8 48 48" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path d="M15 51 L32 14 L49 51" fill="none" class="logo-mark__stroke" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M24 41 L30 47 L41 34" fill="none" class="logo-mark__accent" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</span>
<span class="brandtext">
    <span class="title-static">Adlexone</span>
    <?php if ($descriptor !== ''): ?>
        <span class="brand-descriptor"><?php echo Html::e($descriptor); ?></span>
    <?php endif; ?>
</span>
