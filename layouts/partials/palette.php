<?php
use Adlexone\Html\Html;
use Adlexone\Http\Router;
use Adlexone\Theme\Theme;

if (!isset($_SESSION['access_user_id'])) {
    return;
}
?>
<div class="palette" id="palette" role="dialog" aria-modal="true" aria-label="Search and commands" data-sprite="<?php echo Html::e(Theme::spriteHref()); ?>" data-endpoint="<?php echo Html::e(Router::manageUrl('palette')); ?>" hidden>
    <div class="palette__backdrop" data-palette-close></div>
    <div class="palette__box">
        <div class="palette__search">
            <svg class="icon" aria-hidden="true"><use href="<?php echo Html::e(Theme::spriteHref()); ?>#ic-search"></use></svg>
            <input class="palette__input" type="text" placeholder="Search applications, screens, and records" autocomplete="off" spellcheck="false" aria-label="Search" aria-controls="palette-list">
            <kbd class="kbd">Esc</kbd>
        </div>
        <ul class="palette__list" id="palette-list" role="listbox"></ul>
        <p class="palette__empty" hidden>Nothing matches. Check the spelling, or search by record name.</p>
        <footer class="palette__foot">
            <span><kbd class="kbd">&uarr;</kbd><kbd class="kbd">&darr;</kbd> to move</span>
            <span><kbd class="kbd">Enter</kbd> to open</span>
            <span><kbd class="kbd">Esc</kbd> to close</span>
        </footer>
    </div>
</div>
