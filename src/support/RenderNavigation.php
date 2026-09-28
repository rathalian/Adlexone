<?php
declare(strict_types=1);

namespace Adlexone\support;

use DOMDocument;
use DOMElement;
use RuntimeException;
use function libxml_clear_errors;
use function libxml_use_internal_errors;

final class RenderNavigation
{

    /**
     * Helpdesk links for the top section navigation.
     *
     * Each link is included only when the current role is allowed to see it.
     * Controllers place the result in the top card with applySectionNav().
     *
     * @return string
     */
    public static function helpDeskNavigationURLS(): string
    {
        // Generate navigation buttons based on user role and access permissions

        $html = RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&option=show_announcements',
                APP_HDSK_TXT_38, 'ic-announcements'
            ),
            $_SESSION['access_role_id'],
            4
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&option=new_announcement',
                APP_HDSK_TXT_83, 'ic-announcements'
            ),
            $_SESSION['access_role_id'],
            2
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&subcontroller=item_management_manage&option=show_item_types&default_item_type=' . HELPDESK_SET_ITEM_TYPE,
                APP_HDSK_TXT_59, 'ic-create-ticket'
            ),
            $_SESSION['access_role_id'],
            4
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&option=show_search',
                APP_HDSK_TXT_78, 'ic-search'
            ),
            $_SESSION['access_role_id'],
            5
        );


        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&subcontroller=search_management_manage&option=show_quick_search',
                APP_HDSK_TXT_62, 'ic-quick-search'
            ),
            $_SESSION['access_role_id'],
            5
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&subcontroller=search_management_manage&option=show_saved_searches',
                APP_HDSK_TXT_60, 'ic-my-ticket-searches'
            ),
            $_SESSION['access_role_id'],
            5
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&subcontroller=search_management_manage&option=show_item_search&item_types=' . HELPDESK_SET_ITEM_TYPE,
                APP_HDSK_TXT_61, 'ic-create-ticket-search'
            ),
            $_SESSION['access_role_id'],
            5
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzerohelpdesk_main&option=helpdesk_settings',
                APP_HDSK_TXT_79, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            1
        );

        return $html;
    }

    public static function knowledgebaseNavigationURLS()
    {
        $html = RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL('index.php?controller=app_oneorzeroknowledgebase_main&option=show_knowledge', APP_KB_TXT_68, 'ic-knowledgebase'),
            $_SESSION['access_role_id'],
            5
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL('index.php?controller=app_oneorzeroknowledgebase_main&option=show_item_types&default_item_type=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_KB_TXT_49, 'ic-new-article'),
            $_SESSION['access_role_id'],
            5
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL('index.php?controller=app_oneorzeroknowledgebase_main&option=show_item_search&event_id=returned_items&item_types=' . KNOWLEDGEBASE_SET_KB_ITEM_TYPE, APP_KB_TXT_47,'ic-article-search'),
            $_SESSION['access_role_id'],
            1
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL('index.php?controller=app_oneorzeroknowledgebase_main&option=knowledgebase_settings', APP_KB_TXT_31,'ic-kb-settings'),
            $_SESSION['access_role_id'],
            5
        );

        return $html;
    }

    public static function reportManagerNavigationURLS()
    {

        $html = RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage',
                APP_RM_TXT_10, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            5
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage&view_multi_reports',
                APP_RM_TXT_39, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            5
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage&subcontroller=search_management_manage&option=show_item_search',
                APP_RM_TXT_15, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            3
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage&option=show_report_criteria',
                APP_RM_TXT_30, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            3
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage&option=create_report',
                APP_RM_TXT_2, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            3
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage&option=manage_reports',
                APP_RM_TXT_29, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            3
        );


        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage&option=create_multi_report',
                APP_RM_TXT_37, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            3
        );

        $html .= RenderViews::outputIfRoleAllowed(
            RenderViews::buildURL(
                'index.php?controller=app_oneorzeroreportmanager_manage&option=manage_multi_reports',
                APP_RM_TXT_38, 'ic-hd-settings'
            ),
            $_SESSION['access_role_id'],
            3
        );

        return $html;
    }

    public static function itemSettingsURLs()
    {
        $html = RenderViews::outputIfRoleAllowed(RenderViews::buildURL('index.php?controller=administration_item_settings&option=manage_fields', TXT_53, 'ic-manage-fields'), $_SESSION['access_role_id'], 2);
        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_item_settings&option=manage_item_types', TXT_50, 'ic-manage-item-types'), $_SESSION['access_role_id'], 2);
        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_item_settings&option=new_custom_field', TXT_88, 'ic-custom-field-add'), $_SESSION['access_role_id'], 2);
        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_item_settings&option=new_item_type', TXT_85, 'ic-itemtype-add'), $_SESSION['access_role_id'], $_SESSION['access_role_id'], 2);

        return $html;
    }

    public static function securityManagementURLs()
    {
        $html = RenderViews::outputIfRoleAllowed(RenderViews::buildURL('index.php?controller=administration_security&option=manage_users_groups', TXT_73, 'ic-manage-users'), $_SESSION['access_role_id'], 2);
        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_security&option=new_user', TXT_33, 'ic-new-user'), $_SESSION['access_role_id'], 2);
        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_security&option=new_group', TXT_34, 'ic-new-group'), $_SESSION['access_role_id'], 2);

        return $html;
    }

    public static function workflowURLs()
    {
        $html = RenderViews::outputIfRoleAllowed(RenderViews::buildURL('index.php?controller=administration_actions&option=show_defined_actions', TXT_411, 'ic-manage-actions'), $_SESSION['access_role_id'], 1);
        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_actions&option=show_action_packages', TXT_255, 'ic-new-action'), $_SESSION['access_role_id'], 1);

        return $html;
    }

    public static function systemSettingsURLs()
    {

        $html = RenderViews::outputIfRoleAllowed(RenderViews::buildURL('index.php?controller=administration_settings&option=adlexone_settings', TXT_42,'ic-adlexone-settings'), $_SESSION['access_role_id'], 1);

        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_settings&option=inbound_email_settings', TXT_565,'ic-inbound-email'), $_SESSION['access_role_id'], 0);

        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_settings&&option=ldap_settings', TXT_43,'ic-ldap'), $_SESSION['access_role_id'], 0);

        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_settings&option=autologon_settings', TXT_536,'ic-autologon'), $_SESSION['access_role_id'], 1);

        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_settings&option=advanced_settings', TXT_130, 'ic-advanced'), $_SESSION['access_role_id'], 0);

        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_settings&option=data_sharing_settings', TXT_415, 'ic-data-sharing'), $_SESSION['access_role_id'], 0);

        $html .= RenderViews::outputIfRoleAllowed('<br>' . RenderViews::buildURL('index.php?controller=administration_settings&option=data_source_settings', TXT_631, 'ic-data-source'), $_SESSION['access_role_id'], 0);

        return $html;

    }

    /**
     * Convert controller left-nav HTML into a simple map of controller => [ [label, href, target?, rel?], ... ]
     *
     * @param array<string,string> $controllersNavHtml e.g. ['Helpdesk' => $helpdeskNavHtml]
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
        if (!class_exists(RenderViews::class)) {
            throw new RuntimeException(
                'RenderViews class not found. Require RenderViews.php before calling LaunchpadHelper::render().'
            );
        }

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
            ? RenderViews::buildHorizontalCards($blocks)
            : RenderViews::buildHorizontalCards($blocks, $columns);

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
            $labelText = trim((string)$a->textContent);

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
        $href = self::e((string)($lnk['href'] ?? '#'));

        // Sanitize the target attribute, if provided
        $target = !empty($lnk['target']) ? ' target="' . self::e((string)$lnk['target']) . '"' : '';

        // Sanitize the rel attribute, if provided
        $rel = !empty($lnk['rel']) ? ' rel="' . self::e((string)$lnk['rel']) . '"' : '';

        // Determine the content of the anchor tag
        // Use the sanitized inner HTML if provided; otherwise, escape the label text
        if (!empty($lnk['html'])) {
            $labelOutput = $lnk['html'];
        } else {
            $labelOutput = self::e((string)($lnk['label'] ?? ''));
        }

        // Return the constructed anchor tag
        return '<a class="' . self::e($class) . '" href="' . $href . '"' . $target . $rel . '>' . $labelOutput . '</a>';
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
            $html = '<nav class="side-actions" aria-label="' . self::e($title) . ' quick actions">';
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
        $html = '<nav class="side-actions" aria-label="' . self::e($title) . ' quick actions">';
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
    public static function applySectionNav(string $label, string $linksHtml): void
    {
        if (defined('APP_SECTION_NAV')) {
            return;
        }
        $built = self::build([$label => $linksHtml]);
        define('APP_SECTION_NAV', self::sectionNav($built[$label] ?? [], $label));
    }

    /**
     * Horizontal section links for the top navigation card.
     *
     * @param array<int, array{label: ?string, html: ?string, href: string, target: ?string, rel: ?string}> $links
     */
    public static function sectionNav(array $links, string $label): string
    {
        if ($links === []) {
            return '';
        }

        $html = '<nav class="sectionnav" aria-label="' . self::e($label) . '">';
        foreach ($links as $lnk) {
            $href = (string)($lnk['href'] ?? '#');
            $current = self::linkIsCurrent($href) ? ' aria-current="page"' : '';
            $inner = str_replace(['&nbsp;&nbsp;', '&nbsp;'], '', (string)($lnk['html'] ?? ''));
            $icon = '';
            if (preg_match('/<svg\b.*?<\/svg>/s', $inner, $match) === 1) {
                $icon = $match[0];
            }
            $html .= '<a class="sectionnav__link" href="' . self::e($href) . '"' . $current . '>'
                . $icon
                . '<span class="sectionnav__label">' . self::e(trim((string)($lnk['label'] ?? ''))) . '</span></a>';
        }
        $html .= '</nav>';

        return $html;
    }

    private static function linkIsCurrent(string $href): bool
    {
        $params = [];
        $query = parse_url($href, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $params);
        }
        $linkOption = (string)($params['option'] ?? '');
        $currentOption = (string)($_GET['option'] ?? '');
        if ($linkOption !== '' || $currentOption !== '') {
            return $linkOption !== '' && $linkOption === $currentOption;
        }
        $linkController = (string)($params['controller'] ?? '');
        return $linkController !== ''
            && $linkController === (string)($_GET['controller'] ?? '')
            && (string)($params['subcontroller'] ?? '') === (string)($_GET['subcontroller'] ?? '');
    }

    /**
     * Escapes a string for safe output in HTML.
     *
     * This method uses `htmlspecialchars` to convert special characters in a string
     * into their corresponding HTML entities. This ensures that the string can be safely
     * displayed in an HTML context without introducing XSS vulnerabilities.
     *
     * Example:
     * Input: "<script>alert('XSS');</script>"
     * Output: "&lt;script&gt;alert(&#039;XSS&#039;);&lt;/script&gt;"
     *
     * @param string $s The input string to be escaped.
     * @return string The escaped string, safe for HTML output.
     */
    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); // Escape special characters for HTML
    }


}
