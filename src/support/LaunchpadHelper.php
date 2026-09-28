<?php
/**
 * LaunchpadHelper (simplified)
 * - Minimal public API
 * - Uses RenderViews::renderHorizontalCards
 * - Only the "More" dropdown items get a <br> after each link
 */
namespace Adlexone\support;

final class LaunchpadHelper
{
    /**
     * Convert controller left-nav HTML into a simple map of controller => [ [label, href, target?, rel?], ... ]
     *
     * @param array<string,string> $controllersNavHtml  e.g. ['Service Centre' => $serviceCentreNavHtml]
     * @return array<string,array<int,array{label:string, href:string, target:?string, rel:?string}>>
     */
    public static function build(array $controllersNavHtml): array
    {
        $out = [];
        foreach ($controllersNavHtml as $name => $html) {
            $out[$name] = self::parseLinks($html);
        }
        return $out;
    }

    /**
     * Render a Launchpad grid with one card per controller.
     * - Shows first $peek links inline (no <br>)
     * - Remaining links sit behind a "More" button and include <br> after each link
     *
     * @param array<string,array<int,array{label:string, href:string, target:?string, rel:?string}>> $controllers
     */
    public static function render(array $controllers, int $columns = 3, int $peek = 3): string
    {
        if (!class_exists(RenderViews::class)) {
            throw new \RuntimeException('RenderViews class not found. Require RenderViews.php before calling LaunchpadHelper::render().');
        }

        $blocks = [];
        foreach ($controllers as $title => $links) {
            $blocks[] = [
                'title' => (string)$title,
                'html'  => self::cardBody((string)$title, $links, $peek),
                'full'  => false,
            ];
        }

        return RenderViews::renderHorizontalCards($blocks, $columns);
    }

    // ---------------- internals ----------------

    /** @return array<int,array{label:string, href:string, target:?string, rel:?string}> */
    private static function parseLinks(string $html): array
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        \libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        \libxml_clear_errors();

        $out = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            /** @var \DOMElement $a */
            $href   = $a->getAttribute('href') ?: '#';
            $label  = trim($a->textContent ?? '');
            if ($label === '' && $href === '#') { continue; }
            $out[] = [
                'label'  => $label,
                'href'   => $href,
                'target' => $a->getAttribute('target') ?: null,
                'rel'    => $a->getAttribute('rel') ?: null,
            ];
        }
        return $out;
    }

    /** @param array<int,array{label:string, href:string, target:?string, rel:?string}> $links */
    private static function cardBody(string $title, array $links, int $peek): string
    {
        $quick = array_slice($links, 0, max(0, $peek));
        $rest  = array_slice($links, max(0, $peek));

        // Quick links (no <br>)
        $html = '<nav class="side-actions" aria-label="' . self::e($title) . ' quick actions">';
        foreach ($quick as $lnk) {
            $html .= self::a($lnk, 'URL');
        }
        $html .= '</nav>';

        // More menu (links get <br>)
        if (!empty($rest)) {
            // use slug-based id with fallback to uniqid() to ensure it's never empty / duplicated
            $id = 'lp-more-' . (self::slug($title) ?: uniqid());
            $html .= '<div class="controller-more">'
                .  '<button class="btn btn--sm" type="button" aria-expanded="false" aria-controls="' . $id . '">More</button>'
                .  '<div id="' . $id . '" class="controller-more__menu" hidden>';
            foreach ($rest as $lnk) {
                $html .= self::a($lnk, 'controller-more__item URL') . '<br>';
            }
            $html .=   '</div>'
                . '</div>'
                . '<script>document.addEventListener("click",function(e){var b=e.target.closest("button[aria-controls=\\"' . $id . '\\"]");if(!b)return;var m=document.getElementById("' . $id . '");var x=b.getAttribute("aria-expanded")==="true";b.setAttribute("aria-expanded",String(!x));if(m)m.hidden=x;});</script>';
        }

        return $html;
    }

    /** @param array{label:string, href:string, target:?string, rel:?string} $lnk */
    private static function a(array $lnk, string $class): string
    {
        $href   = self::e($lnk['href']);
        $label  = self::e($lnk['label']);
        $target = $lnk['target'] ? ' target="' . self::e($lnk['target']) . '"' : '';
        $rel    = $lnk['rel']    ? ' rel="'    . self::e($lnk['rel'])    . '"' : '';
        return '<a class="' . self::e($class) . '" href="' . $href . '"' . $target . $rel . '>' . $label . '</a>';
    }

    private static function slug(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('~[^a-z0-9]+~', '-', $s);
        return trim($s ?? '', '-');
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}
