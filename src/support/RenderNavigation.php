<?php
declare(strict_types=1);

namespace Adlexone\support;

use Adlexone\Auth\Permission;
use Adlexone\Http\Page;
use DOMDocument;
use DOMElement;
use RuntimeException;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Html\Breadcrumb;
use Adlexone\Html\Html;
use Adlexone\Http\Router;

final class RenderNavigation
{

    /**
     * The Design area: the same destinations as the Design menu in the top bar.
     */
    public static function designURLs(): string
    {
        $html = '';
        foreach (\Adlexone\Http\TopNav::designItems() as $item) {
            $html .= Blocks::link($item['href'], $item['label'], $item['icon']);
        }

        return $html;
    }

    public static function itemSettingsURLs()
    {
        $html = Access::outputIfAllowed(Blocks::link(Router::manageUrl('fields'), TXT_53, 'ic-fields'), Permission::ADMIN_ITEMS);
        $html .= Access::outputIfAllowed('<br>' . Blocks::link(Router::manageUrl('record-types'), TXT_50, 'ic-layers'), Permission::ADMIN_ITEMS);

        return $html;
    }

    public static function securityManagementURLs()
    {
        $html = Access::outputIfAllowed(Blocks::link(Router::manageUrl('security', 'option=manage_users'), TXT_40, 'ic-users'), Permission::ADMIN_SECURITY);
        $html .= Access::outputIfAllowed('<br>' . Blocks::link(Router::manageUrl('security', 'option=manage_groups'), TXT_35, 'ic-users-round'), Permission::ADMIN_SECURITY);

        return $html;
    }

    public static function rulesURLs()
    {
        $html = Access::outputIfAllowed(Blocks::link(Router::manageUrl('rules'), 'Rules', 'ic-zap'), Permission::ADMIN_ACTIONS);

        return $html;
    }

    public static function systemSettingsURLs(bool $includeLanding = true)
    {
        $canSite = Access::can(Permission::ADMIN_SETTINGS);
        $html = $includeLanding
            ? Access::outputIfAllowed(Blocks::link(Router::manageUrl('settings', 'option=adlexone_settings'), TXT_42,'ic-sliders'), Permission::ADMIN_SETTINGS)
            : '';

        $html .= Access::outputIfAllowed('<br>' . Blocks::link(Router::manageUrl('settings', 'option=theme_settings'), 'Theme', 'ic-palette'), Permission::ADMIN_SETTINGS);

        if ($includeLanding || $canSite) {
            $html .= Access::outputIfAllowed('<br>' . Blocks::link(Router::manageUrl('settings', 'option=sign_in_settings'), 'Sign-in', 'ic-lock'), Permission::ADMIN_SYSTEM);
        }

        $html .= Access::outputIfAllowed('<br>' . Blocks::link(Router::manageUrl('settings', 'option=email_settings'), TXT_565,'ic-mail'), Permission::ADMIN_SYSTEM);

        $html .= Access::outputIfAllowed('<br>' . Blocks::link(Router::manageUrl('settings', 'option=advanced_settings'), TXT_130, 'ic-settings2'), Permission::ADMIN_SYSTEM);

        return $html;
    }

    /**
     * @param array<string, string> $sections section name => link HTML
     * @return array<string, array<int, array{label: ?string, html: ?string, href: string, target: ?string, rel: ?string}>>
     */
    public static function build(array $sections): array
    {
        $out = [];
        foreach ($sections as $name => $html) {
            $out[$name] = self::parseLinks($html);
        }
        return $out;
    }


    /**
     * Parses a string of HTML and extracts sanitized anchor (`<a>`) elements.
     *
     * This method uses the DOMDocument class to parse the provided HTML string and extract
     * all anchor tags (`<a>`). It ensures that the extracted links are sanitized by:
     * - Removing `<script>` elements to prevent malicious scripts.
     * - Stripping inline event handlers (attributes starting with "on") to avoid XSS vulnerabilities.
     * - Neutralizing `javascript:` URIs in `href` attributes by replacing them with `#`.
     *
     * The method returns an array of sanitized links, each represented as an associative array
     * containing the following keys:
     * - `label`: The visible text content of the anchor tag (if any).
     * - `html`: The inner HTML of the anchor tag (preserving any child elements like SVGs).
     * - `href`: The sanitized `href` attribute of the anchor tag.
     * - `target`: The `target` attribute of the anchor tag (if present).
     * - `rel`: The `rel` attribute of the anchor tag (if present).
     *
     * @param string $html The HTML string to parse.
     * @return array<int, array{label: ?string, html: ?string, href: string, target: ?string, rel: ?string}>
     *         An array of sanitized links extracted from the HTML string.
     */
    private static function parseLinks(string $html): array
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Remove <script> elements to prevent malicious scripts
        $scripts = $doc->getElementsByTagName('script');
        for ($i = $scripts->length - 1; $i >= 0; $i--) {
            $scripts->item($i)->parentNode->removeChild($scripts->item($i));
        }

        // Strip inline event handlers (attributes starting with "on") and neutralize `javascript:` URIs
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//*') as $node) {
            /** @var \DOMElement $node */
            if (!$node->hasAttributes()) {
                continue;
            }
            $remove = [];
            foreach (iterator_to_array($node->attributes) as $attr) {
                $name = strtolower($attr->name);
                if (strpos($name, 'on') === 0) {
                    $remove[] = $name;
                } elseif ($name === 'href' && preg_match('#^\s*javascript:#i', $attr->value)) {
                    // Neutralize `javascript:` URIs by replacing them with `#`
                    $node->setAttribute('href', '#');
                }
            }
            foreach ($remove as $attrName) {
                $node->removeAttribute($attrName);
            }
        }

        $out = [];
        foreach ($doc->getElementsByTagName('a') as $a) {
            /** @var \DOMElement $a */
            $href = $a->getAttribute('href') ?: '#';
            $target = $a->getAttribute('target') ?: null;
            $rel = $a->getAttribute('rel') ?: null;

            // Extract visible text content (may be empty if the anchor contains only child elements like SVGs)
            $labelText = trim(str_replace("\u{00A0}", ' ', (string) $a->textContent));
            $labelText = trim((string) preg_replace('/\s+/u', ' ', $labelText));

            // Extract inner HTML (preserving child elements like SVGs)
            $innerHtml = '';
            foreach ($a->childNodes as $child) {
                $innerHtml .= $doc->saveHTML($child);
            }
            $innerHtml = trim($innerHtml);

            // Skip placeholder anchors (anchors with no content and `href` set to `#`)
            if ($labelText === '' && $innerHtml === '' && $href === '#') {
                continue;
            }

            // Add the sanitized anchor data to the output array
            $out[] = [
                'label' => $labelText !== '' ? $labelText : null,
                'html' => $innerHtml !== '' ? $innerHtml : null,
                'href' => $href,
                'target' => $target,
                'rel' => $rel,
            ];
        }

        return $out;
    }


    /**
     * @param list<array{label: string, href?: string}> $crumbs The page trail, Home first. It is drawn in the
     *        application band when there is one, otherwise at the start of the section bar.
     */
    public static function applySectionNav(string $label, string $linksHtml, string $hero = '', array $crumbs = []): void
    {
        $page = Page::current();
        if ($page->hasSectionNav()) {
            return;
        }
        $page->suggestEyebrow($label);
        $built = self::build([$label => $linksHtml]);
        $page->setAppHero($hero);
        $links = $built[$label] ?? [];
        if ($crumbs !== []) {
            $page->setCrumbs($crumbs);
            if ($links === []) {
                $page->suggestNavLabel((string) end($crumbs)['label']);
            }
        }
        $page->setSectionNav(self::sectionNav($links, $label, $hero === '', $crumbs !== [] && $hero === ''));
    }

    /**
     * Section bar for a Configure screen: a breadcrumb that starts at Home and Configure, then the tabs
     * that belong to this screen only.
     *
     * @param list<array{label: string, href?: string}> $trail the crumbs after Configure. Give each one an
     *        href so a deeper screen can add a crumb after it with Page::addCrumb().
     */
    public static function applyConfigureNav(array $trail, string $linksHtml = ''): void
    {
        self::applySectionNav('Configure', $linksHtml, '', Breadcrumb::trail(array_merge([['label' => 'Configure']], $trail)));
    }

    /**
     * Horizontal section links for the top navigation card.
     *
     * @param array<int, array{label?: ?string, html?: ?string, href: string, target?: ?string, rel?: ?string}> $links
     */
    public static function sectionNav(array $links, string $label, bool $named = true, bool $crumbs = false): string
    {
        if ($links === [] && !$crumbs) {
            return '';
        }

        $html = '<div class="sectionbar' . ($named ? '' : ' sectionbar--tabs') . ($links === [] ? ' sectionbar--crumbs' : '') . '">';
        if ($crumbs) {
            $html .= Breadcrumb::BAR_SLOT;
            $label = (string) (Page::current()->crumbs()[count(Page::current()->crumbs()) - 1]['label'] ?? $label);
        } elseif ($named) {
            $html .= '<p class="sectionbar__name">' . self::sectionMark() . Html::e($label) . '</p>';
        }
        if ($links === []) {
            return $html . '</div>';
        }
        $html .= '<nav class="sectionnav" aria-label="' . Html::e($label) . '">';
        foreach ($links as $lnk) {
            $href = (string)($lnk['href'] ?? '#');
            $current = self::linkIsCurrent($href) ? ' aria-current="page"' : '';
            $inner = str_replace(['&nbsp;&nbsp;', '&nbsp;'], '', (string)($lnk['html'] ?? ''));
            $icon = '';
            if (preg_match('/<svg\b.*?<\/svg>/s', $inner, $match) === 1) {
                $icon = $match[0];
            }
            $linkLabel = trim(str_replace("\u{00A0}", '', (string)($lnk['label'] ?? '')));
            if ($current !== '' && $linkLabel !== '') {
                Page::current()->suggestNavLabel($linkLabel);
            }
            $group = self::sectionNavGroup($href) ? ' sectionnav__link--group' : '';
            $html .= '<a class="sectionnav__link' . $group . '" href="' . Html::e($href) . '"' . $current . '>'
                . $icon
                . '<span class="sectionnav__label">' . Html::e($linkLabel) . '</span></a>';
        }
        $html .= '</nav></div>';

        return $html;
    }

    private static function sectionMark(): string
    {
        $page = Page::current();
        if (!$page->inApplication()) {
            return '';
        }
        $app = \Adlexone\Application\ApplicationStore::findBySlug($page->applicationSlug());
        $icon = is_array($app) ? trim((string) ($app['icon'] ?? '')) : '';
        if ($icon === '') {
            return '';
        }

        return '<span class="sectionbar__mark" aria-hidden="true"><svg class="icon"><use href="'
            . Html::e(\Adlexone\Theme\Theme::spriteHref()) . '#' . Html::e($icon)
            . '"></use></svg></span>';
    }

    private static function sectionNavGroup(string $href): bool
    {
        return str_contains($href, 'option=show_item_types')
            || str_contains($href, 'option=show_saved_searches')
            || str_contains($href, 'option=show_announcements')
            || str_contains($href, 'option=details&application_id=')
            || str_contains($href, 'option=navigation&application_id=')
            || str_contains($href, '&option=settings')
            || str_contains($href, 'option=knowledgebase_settings');
    }

    private static function linkIsCurrent(string $href): bool
    {
        $params = [];
        $query = parse_url($href, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
        }
        $linkNav = (string)($params['nav'] ?? '');
        if ($linkNav !== '') {
            return $linkNav === (string)($_GET['nav'] ?? '');
        }
        $linkOption = (string)($params['option'] ?? '');
        $navOverride = Page::current()->navOption();
        $currentOption = $navOverride !== ''
            ? $navOverride
            : (string)($_GET['option'] ?? '');
        if ($currentOption === '__section__') {
            $currentOption = '';
        }
        $linkManage = (string)($params['manage'] ?? '');
        if ($linkManage !== '' && $linkOption === '' && $currentOption === '' && $linkManage === (string)($_GET['manage'] ?? '')) {
            return true;
        }
        if ($linkOption !== '' || $currentOption !== '') {
            return $linkOption !== '' && $linkOption === $currentOption;
        }

        return false;
    }



}
