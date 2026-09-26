<?php
declare(strict_types=1);

namespace Adlexone\support;

final class NavHelper
{
    public static function buildHelpdeskLeftNavHTML(){


        
    }
    public static function navList(array $items, array $opts = []): string
    {
        $variant = isset($opts['variant']) ? (string)$opts['variant'] : 'pills';
        $cols    = isset($opts['columns']) ? max(1, (int)$opts['columns']) : 3;

        $safe = static function(string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $wrapOpen = '<nav class="adx-navlist adx-navlist--' . $safe($variant) . '">';
        $wrapClose = '</nav>';

        $html = $wrapOpen;
        if ($variant === 'grid') {
            $html .= '<div class="adx-grid" style="display:grid;grid-template-columns:repeat(' . $cols . ',minmax(0,1fr));gap:10px;">';
        } else {
            $html .= '<ul class="adx-ul">';
        }

        foreach ($items as $it) {
            $label = $safe((string)($it['label'] ?? ''));
            $href  = $safe((string)($it['href']  ?? '#'));
            $icon  = isset($it['icon']) ? $safe((string)$it['icon']) : '';
            $desc  = isset($it['desc']) ? $safe((string)$it['desc']) : '';

            $inner = '';
            if ($icon !== '') { $inner .= '<img class="adx-nav-ico" src="' . $icon . '" alt="" />'; }
            $inner .= '<span class="adx-nav-label">' . $label . '</span>';
            if ($desc !== '') { $inner .= '<small class="adx-nav-desc">' . $desc . '</small>'; }

            if ($variant === 'grid') {
                $html .= '<a class="adx-nav-card" href="' . $href . '">' . $inner . '</a>';
            } else {
                $html .= '<li class="adx-li"><a class="adx-nav-pill" href="' . $href . '">' . $inner . '</a></li>';
            }
        }

        if ($variant === 'grid') { $html .= '</div>'; } else { $html .= '</ul>'; }
        $html .= $wrapClose;
        return $html;
    }
}
