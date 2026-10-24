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
use Adlexone\Html\Html;

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
        $html = Access::outputIfAllowed(Blocks::link('?manage=fields', TXT_53, 'ic-fields'), Permission::ADMIN_ITEMS);
        $html .= Access::outputIfAllowed('<br>' . Blocks::link('?manage=item-types', TXT_50, 'ic-layers'), Permission::ADMIN_ITEMS);

        return $html;
    }

    public static function securityManagementURLs()
    {
        $html = Access::outputIfAllowed(Blocks::link('?manage=security&option=manage_users', TXT_40, 'ic-users'), Permission::ADMIN_SECURITY);
        $html .= Access::outputIfAllowed('<br>' . Blocks::link('?manage=security&option=manage_groups', TXT_35, 'ic-users-round'), Permission::ADMIN_SECURITY);

        return $html;
    }

    public static function rulesURLs()
    {
        $html = Access::outputIfAllowed(Blocks::link('?manage=rules', 'Rules', 'ic-zap'), Permission::ADMIN_ACTIONS);

        return $html;
    }

    public static function systemSettingsURLs()
    {
        $html = Access::outputIfAllowed(Blocks::link('?manage=settings&option=adlexone_settings', TXT_42,'ic-sliders'), Permission::ADMIN_SETTINGS);

        $html .= Access::outputIfAllowed('<br>' . Blocks::link('?manage=settings&option=theme_settings', 'Theme', 'ic-palette'), Permission::ADMIN_SETTINGS);

        $html .= Access::outputIfAllowed('<br>' . Blocks::link('?manage=settings&option=sign_in_settings', 'Sign-in', 'ic-lock'), Permission::ADMIN_SYSTEM);

        $html .= Access::outputIfAllowed('<br>' . Blocks::link('?manage=settings&option=email_settings', TXT_565,'ic-mail'), Permission::ADMIN_SYSTEM);

        $html .= Access::outputIfAllowed('<br>' . Blocks::link('?manage=settings&option=advanced_settings', TXT_130, 'ic-settings2'), Permission::ADMIN_SYSTEM);

        return $html;
    }

/**
     * Convert controller left-nav HTML into a simple map of controller => [ [label, href, target?, rel?], ... ]
     *
     * @param array<string,string> $controllersNavHtml e.g. ['Service Centre' => $serviceCentreNavHtml]
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
    public static function render(
        array $controllers,
        ?int  $columns = null,
        int   $peek = 3,
        bool  $aside = false
    ): string
    {
        // Build blocks
        $blocks = [];
        foreach ($controllers as $title => $links) {
            $blocks[] = [
                'title' => (string)$title,
                'html' => self::cardBody((string)$title, $links, $peek, $aside),
                'full' => false,
            ];
        }

        // Render cards (responsive by default if $columns is null)
        $content = $columns === null
            ? Blocks::horizontal($blocks)
            : Blocks::horizontal($blocks, $columns);

        // Optional aside wrapper
        if ($aside) {
            $content = '<aside id="sidebar" class="sidebar" aria-label="Primary">' . $content . '</aside>';
        }

        return $content;
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
     * Generates an HTML anchor (`<a>`) element with sanitized attributes and content.
     *
     * This method creates an anchor tag based on the provided link data and CSS class.
     * It ensures that the `href`, `target`, and `rel` attributes are properly escaped
     * to prevent XSS vulnerabilities. The content of the anchor tag is either the sanitized
     * inner HTML (if provided) or the escaped label text.
     *
     * @param array $lnk An associative array containing the link data:
     *  - 'href' (string): The URL for the anchor tag. Defaults to `#` if not provided.
     *  - 'target' (string|null): The target attribute for the anchor tag (e.g., `_blank`).
     *  - 'rel' (string|null): The rel attribute for the anchor tag (e.g., `noopener`).
     *  - 'html' (string|null): The inner HTML content of the anchor tag. If provided, it is used as-is.
     *  - 'label' (string|null): The text content of the anchor tag. Used if 'html' is not provided.
     * @param string $class The CSS class to apply to the anchor tag.
     * @return string The generated HTML anchor tag as a string.
     */
    private static function a(array $lnk, string $class): string
    {
        // Sanitize the href attribute, defaulting to '#' if not provided
        $href = Html::e((string)($lnk['href'] ?? '#'));

        // Sanitize the target attribute, if provided
        $target = !empty($lnk['target']) ? ' target="' . Html::e((string)$lnk['target']) . '"' : '';

        // Sanitize the rel attribute, if provided
        $rel = !empty($lnk['rel']) ? ' rel="' . Html::e((string)$lnk['rel']) . '"' : '';

        // Determine the content of the anchor tag
        // Use the sanitized inner HTML if provided; otherwise, escape the label text
        if (!empty($lnk['html'])) {
            $labelOutput = $lnk['html'];
        } else {
            $labelOutput = Html::e((string)($lnk['label'] ?? ''));
        }

        // Return the constructed anchor tag
        return '<a class="' . Html::e($class) . '" href="' . $href . '"' . $target . $rel . '>' . $labelOutput . '</a>';
    }

    /**
     * Generates the HTML content for a navigation card body, including quick links and an optional "More" menu.
     *
     * This method creates a navigation block (`<nav>`) containing a set of links. If the number of links exceeds
     * the specified `$peek` value, the remaining links are hidden behind a "More" button. The "More" button toggles
     * the visibility of the additional links. The method also supports rendering all links without the "More" button
     * when `$aside` is set to `true`.
     *
     * @param string $title The title of the navigation block, used for accessibility labels.
     * @param array<int, array{label: string, href: string, target: ?string, rel: ?string}> $links
     *        An array of links, where each link is an associative array with the following keys:
     *        - 'label': The visible text of the link.
     *        - 'href': The URL the link points to.
     *        - 'target': (Optional) The target attribute for the link (e.g., `_blank`).
     *        - 'rel': (Optional) The rel attribute for the link (e.g., `noopener`).
     * @param int $peek The number of links to display inline before hiding the rest behind the "More" button.
     * @param bool $aside If `true`, all links are displayed without the "More" button.
     * @return string The generated HTML content for the navigation card body.
     */
    private static function cardBody(string $title, array $links, int $peek, bool $aside = false): string
    {
        // If aside is true, list all links (no "More" button)
        if ($aside) {
            $html = '<nav class="side-actions" aria-label="' . Html::e($title) . ' quick actions">';
            foreach ($links as $lnk) {
                $html .= self::a($lnk, 'URL');
            }
            $html .= '</nav>';
            return $html;
        }

        // Split the links into quick links (visible) and the rest (hidden behind "More")
        $quick = array_slice($links, 0, max(0, $peek));
        $rest = array_slice($links, max(0, $peek));

        // Generate the quick links section
        $html = '<nav class="side-actions" aria-label="' . Html::e($title) . ' quick actions">';
        foreach ($quick as $lnk) {
            $html .= self::a($lnk, 'URL');
        }
        $html .= '</nav>';

        // If there are additional links, generate the "More" button and hidden menu
        if (!empty($rest)) {
            // Create a unique ID for the "More" menu based on the title and link count
            $id = 'lp-more-' . self::slug($title) . '-' . substr(md5($title . count($links)), 0, 6);

            $html .= '<div class="controller-more">'
                . '<button class="btn btn--sm controller-more__toggle" type="button" aria-expanded="false" aria-controls="' . $id . '">More</button>'
                . '<div id="' . $id . '" class="controller-more__menu" hidden>';
            foreach ($rest as $lnk) {
                $html .= self::a($lnk, 'controller-more__item URL') . '<br>';
            }
            $html .= '</div>'
                . '</div>';
        }

        return $html;
    }


    /**
     * Converts a string into a URL-friendly "slug".
     *
     * This method takes a string and transforms it into a lowercase, hyphen-separated format
     * suitable for use in URLs. It removes any non-alphanumeric characters and replaces them
     * with hyphens. Leading and trailing hyphens are also trimmed to ensure a clean result.
     *
     * Example:
     * Input: "Hello World!"
     * Output: "hello-world"
     *
     * @param string $s The input string to be converted into a slug.
     * @return string The URL-friendly slug.
     */
    private static function slug(string $s): string
    {
        $s = strtolower(trim($s)); // Convert to lowercase and trim whitespace
        $s = preg_replace('~[^a-z0-9]+~', '-', $s); // Replace non-alphanumeric characters with hyphens
        return trim($s ?? '', '-'); // Trim leading and trailing hyphens
    }

    /**
     * Put a controller's links in the top navigation card and drop the left sidebar.
     * Call this once at the top of a controller, before the page is rendered.
     */
    public static function applySectionNav(string $label, string $linksHtml, string $hero = ''): void
    {
        $page = Page::current();
        if ($page->hasSectionNav()) {
            return;
        }
        $page->suggestEyebrow($label);
        $built = self::build([$label => $linksHtml]);
        $page->setAppHero($hero);
        $page->setSectionNav(self::sectionNav($built[$label] ?? [], $label, $hero === ''));
    }

    /**
     * Horizontal section links for the top navigation card.
     *
     * @param array<int, array{label: ?string, html: ?string, href: string, target: ?string, rel: ?string}> $links
     */
    public static function sectionNav(array $links, string $label, bool $named = true): string
    {
        if ($links === []) {
            return '';
        }

        $html = '<div class="sectionbar' . ($named ? '' : ' sectionbar--tabs') . '">';
        if ($named) {
            $html .= '<p class="sectionbar__name">' . self::sectionMark() . Html::e($label) . '</p>';
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
            || str_contains($href, 'option=edit&application_id=')
            || str_contains($href, 'option=nav&application_id=')
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
        $linkController = (string)($params['controller'] ?? '');
        return $linkController !== ''
            && $linkController === (string)($_GET['controller'] ?? '')
            && (string)($params['subcontroller'] ?? '') === (string)($_GET['subcontroller'] ?? '');
    }



}
