<?php
declare(strict_types=1);
/**
 * Adlexone FlowIQ License Agreement 1.0
 *
 * 1. Copying the Adlexone FlowIQ software and distributing as your own software
 *  without the written permission of Adlexone is forbidden under the terms of
 *  the Adlexone FlowIQ License.
 * 2. You may modify your copy of the Adlexone FlowIQ software, however where
 *  Adlexone FlowIQ files contain the Adlexone FlowIQ license in the header of the file, the
 *  Adlexone FlowIQ License header must remain.
 * 3. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the data created or managed by your Adlexone FlowIQ installation.
 * 4. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for your Adlexone FlowIQ configuration or the hosting environment
 *  your Adlexone FlowIQ installation operates in.
 * 5. Adlexone, and the copyright holders of the Adlexone FlowIQ, provide no
 *  warranty for the Adlexone FlowIQ where the software has been modified by
 *  third parties (i.e. other than Adlexone), unless an agreement has been
 *  reached with Adlexone.
 * 6. By using the Adlexone FlowIQ, you are indicating your acceptance of the
 *  stated Adlexone FlowIQ License terms and conditions.
 *
 * Contact info@oneorzero.com if you have any further licensing questions.
 */

/**
 * Class RenderViews
 *
 * Handles rendering of views within Adlexone
 *
 * @author Adam Hall
 * @version 1.0
 * @package Adlexone\support
 */

namespace Adlexone\support;

class RenderViews
{


    /**
     * Renders a form with a modern, semantic HTML structure.
     *
     * This method generates a form using a div-based layout for accessibility and user-friendliness.
     * It includes the form title, fields, and buttons, and renders the form within a vertical card layout.
     * The form is then included in the main page content.
     *
     * @param string $formTitle The title of the form to be displayed at the top.
     *                          This is used as the heading for the form card.
     * @param string $formActionURL The URL where the form data will be submitted.
     *                               This is set as the `action` attribute of the `<form>` tag.
     * @param array $fields An associative array of form fields, where each key is the label
     *                      and the value is the corresponding HTML element.
     *                      Example: ['Label 1' => '<input ...>', 'Label 2' => '<input ...>']
     * @param array $buttons An array of buttons to be included at the bottom of the form.
     *                       Each button is represented as an HTML string.
     *                       Example: ['<button ...>', '<button ...>']
     * @param array $options Optional form tag attributes: method, enctype, name, id.
     *
     * @return string The form HTML wrapped in a vertical card. Does not set BODY_CONTENT.
     */
    public static function buildForm(string $formTitle, string $formActionURL, array $fields, array $buttons, array $options = []): string
    {
        $html = self::buildStartForm(
            $formActionURL,
            (string)($options['method'] ?? 'post'),
            (string)($options['name'] ?? ''),
            (string)($options['id'] ?? ''),
            (string)($options['enctype'] ?? 'application/x-www-form-urlencoded')
        );
        $html .= self::buildFormFieldsGrid($fields);
        $html .= self::buildEndFormWithButtons($buttons);

        return self::buildVerticalCards([
            [
                'title' => $formTitle,
                'html' => $html,
            ],
        ]);
    }

    /**
     * Renders a specific page within the application.
     *
     * Includes the PHP file for the given page, using the specified language and theme.
     * The file path is built from the `THEME_PATH` constant, theme, and page name.
     *
     * @param string $page The name of the page to render (without file extension).
     * @param string $theme The theme to use for rendering the page.
     *
     * @return void
     */
    public static function renderThemePage(string $page, string $theme): void
    {
        include THEME_PATH . "$theme/pages/$page.php";
    }


    /**
     * Includes the appropriate controller file based on the provided controller name.
     *
     * This method determines the controller file to include by sanitizing the provided
     * controller name to prevent directory traversal or invalid characters. If the controller
     * name is null or empty, it defaults to the specified default page. The method then
     * constructs the file path and includes the corresponding PHP file.
     *
     * @param string $controller The name of the controller or subcontroller to load.
     *                                If null or empty, the default page is used.
     * @param string $defaultPage The default controller to use if none is provided.
     *
     * @return void
     */
    public static function includeControllerFile(?string $controller, string $defaultPage): void
    {
        $controller = basename($controller ?? $defaultPage);
        if (str_contains($controller, ':')) {
            [$controller, $appSlug] = explode(':', $controller, 2);
            if (trim((string) ($_GET['app'] ?? '')) === '') {
                $_GET['app'] = $appSlug;
            }
        }
        if ($controller === '') {
            $controller = $defaultPage;
        }

        \Adlexone\Http\Router::open($controller);
    }


    /**
     * Generates HTML for a left navigation button group.
     *
     * This method returns the HTML markup for a left navigation button,
     * optionally including a divider and a section title.
     *
     * @param string $queryString The URL for the button's link.
     * @param string $buttonText The text displayed on the button.
     * @param string|false $navBarTitle Optional title for the navigation section.
     * @param bool $navBarDivider Optional divider before the button.
     * @return string               The generated HTML for the navigation button group.
     */
    public static function buildLeftNavigationButton(string $queryString, string $buttonText, string|false $navBarTitle = false, bool $navBarDivider = false): string
    {
        return
            ($navBarDivider ? '<div class="side-divider"></div>' : '') .
            '<div class="side-group">' .
            (!empty($navBarTitle) ? '<div class="side-title" style="margin-top:10px">' . $navBarTitle . '</div>' : '') .
            '<a class="navbtn" href="' . $queryString . '">' . $buttonText . '</a>' .
            '</div>';
    }

    /**
     * Renders the left navigation card.
     *
     * This method generates the HTML structure for a left navigation card,
     * which includes a sidebar and a navigation section. The navigation
     * section is populated with the provided HTML for navigation buttons.
     *
     * @param string $navigationButtons HTML content for the left navigation buttons.
     *                                  This should be pre-generated HTML for the buttons
     *                                  to be displayed inside the navigation section.
     * @return string The complete HTML for the left navigation card.
     */
    public static function buildLeftNavigationCard(string $navigationButtons): string
    {
        $html = '<aside id="sidebar" class="sidebar" aria-label="Primary">';
        $html .= '  <nav class="side-actions">';
        $html .= $navigationButtons;
        $html .= '  </nav>';
        $html .= '</aside>';
        return $html;
    }


    /**
     * Renders content contentBlocks in a vertical layout (single column).
     *
     * This method generates HTML for displaying content contentBlocks in a single-column layout.
     * Each block can include a title, static HTML content, or dynamically rendered content.
     *
     * - If the 'title' key is provided, it is displayed as the block's title.
     * - If the 'html' key is provided, its content is directly included in the block.
     * - If the 'render' key is provided and is callable, the function is executed to render dynamic content.
     *
     * @param array $bodyBlocks An array of content contentBlocks, where each block is an associative array
     *                      with optional keys:
     *                      - 'title': string, the title of the block.
     *                      - 'html': string, static HTML content.
     *                      - 'render': callable, a function to render dynamic content.
     * @return string The generated HTML for the vertical layout.
     */
    public static function buildVerticalCards(array $bodyBlocks): string
    {
        $titles = [];
        foreach ($bodyBlocks as $b) {
            $rawTitle = trim((string)($b['title'] ?? ''));
            if ($rawTitle !== '') {
                $titles[] = $rawTitle;
            }
        }
        $inSection = defined('APP_SECTION_NAV');
        $liftTitle = count($bodyBlocks) === 1
            && count($titles) === 1
            && !defined('PAGE_TITLE')
            && !$inSection;
        if ($liftTitle) {
            define('PAGE_TITLE', trim(html_entity_decode(strip_tags($titles[0]), ENT_QUOTES, 'UTF-8')));
        }

        $html = '<div class="grid">';
        foreach ($bodyBlocks as $b) {
            $rawTitle = trim((string)($b['title'] ?? ''));
            $title = $rawTitle !== '' ? htmlspecialchars($rawTitle, ENT_QUOTES, 'UTF-8') : '';
            $repeatsSection = self::titleRepeatsSection($rawTitle);
            $html .= '<section class="card span-2">';                      // full width always
            if ($title !== '' && !$liftTitle && !$repeatsSection) {
                $html .= '<div class="card-title">' . $title . '</div>';
            }
            $html .= '<div class="form-block">';                         // consistent inner padding/visual
            if (!empty($b['render']) && is_callable($b['render'])) {
                ($b['render'])();
            } elseif (isset($b['html'])) {
                $html .= $b['html'];                                   // trusted server HTML
            }
            $html .= '</div>';
            $html .= '</section>';
        }
        $html .= '</div>';

        return $html;
    }

    private static function titleRepeatsSection(string $title): bool
    {
        if (!defined('APP_SECTION_NAV') || !defined('APPLICATION_NAV_LABEL')) {
            return false;
        }
        $plain = trim(html_entity_decode(strip_tags($title), ENT_QUOTES, 'UTF-8'));

        return $plain !== '' && strcasecmp($plain, trim((string) APPLICATION_NAV_LABEL)) === 0;
    }

    /**
     * Renders content blocks in a horizontal layout with a configurable number of columns.
     *
     * This method generates HTML for displaying content blocks in a grid layout. The number of columns
     * can be adjusted using the `$columns` parameter. Each block can include a title, static HTML content,
     * or dynamically rendered content. Blocks can also span all columns if the 'full' key is set to true.
     *
     * - If the 'title' key is provided, it is displayed as the block's title.
     * - If the 'html' key is provided, its content is directly included in the block.
     * - If the 'render' key is provided and is callable, the function is executed to render dynamic content.
     * - If the 'full' key is set to true, the block spans all columns.
     *
     * @param array $bodyBlocks An array of content blocks, where each block is an associative array
     *                              with optional keys:
     *                              - 'title': string, the title of the block.
     *                              - 'html': string, static HTML content.
     *                              - 'render': callable, a function to render dynamic content.
     *                              - 'full': bool, whether the block spans all columns.
     * @param int $columns The number of columns in the grid layout. Defaults to 2.
     * @return string The generated HTML for the horizontal layout.
     */
    public static function buildHorizontalCards(array $blocks, ?int $columns = null): string
    {
        // Container classes decide layout mode; CSS handles the rest.
        $modeClass = $columns === null ? '-auto' : '-fixed';
        $styleAttr = $columns === null ? '' : ' style="--cols: ' . (int)$columns . ';"';

        $html = '<section class="launchpad-grid ' . $modeClass . '"' . $styleAttr . '>';
        foreach ($blocks as $b) {
            $title = htmlspecialchars($b['title'] ?? '', ENT_QUOTES, 'UTF-8');
            $body = $b['html'] ?? '';
            $full = !empty($b['full']);

            // If a block must span all columns, we give it a helper class.
            $span = $full ? ' card -full' : ' card';

            $html .= '<article class="' . $span . '">';
            if ($title !== '') {
                $html .= '<div class="card-title">' . $title . '</div>';
            }
            $html .= '<div class="card__body">' . $body . '</div>';
            $html .= '</article>';
        }
        $html .= '</section>';

        return $html;
    }

    /**
     * Render a horizontal separator. If $label is provided the label is centered
     * between two lines. Use $class for custom classes and $style for inline styles.
     *
     * @param string $label Text to show centered in the separator (optional)
     * @param string $class Additional CSS classes (optional)
     * @param string $style Inline style attributes (optional)
     * @return string HTML for the separator
     */
    public static function buildHorizontalSeparator(string $label = '', string $class = '', string $style = ''): string
    {
        $classAttr = $class !== '' ? ' ' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') : '';
        $styleAttr = $style !== '' ? ' style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '"' : '';

        if ($label === '') {
            // Simple <hr> for no-label case (accessible by default)
            return '<hr class="rv-separator' . $classAttr . '"' . $styleAttr . ' />';
        }

        // Labelled separator: two lines with the label centered (uses flexbox)
        $labelEsc = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');

        return
            '<div class="rv-separator-block' . $classAttr . '"' . $styleAttr . ' role="separator" aria-label="' . $labelEsc . '" style="display:flex;align-items:center;gap:0.75rem;">'
            . '<span style="flex:1;border-bottom:1px solid #e0e0e0;" aria-hidden="true"></span>'
            . '<span class="rv-separator-label" style="white-space:nowrap;padding:0 0.5rem;color:inherit;">' . $labelEsc . '</span>'
            . '<span style="flex:1;border-bottom:1px solid #e0e0e0;" aria-hidden="true"></span>'
            . '</div>';
    }

    /**
     * Generates the opening HTML `<form>` tag for a web form.
     *
     * This method creates a form tag with the specified action URL, HTTP method,
     * name, and id. The `enctype` is set to `application/x-www-form-urlencoded` by default.
     *
     * @param string $action The URL where the form data will be submitted.
     * @param string $method The HTTP method to use for the form submission (e.g., GET, POST).
     * @param string $name (Optional) The name attribute of the form. Defaults to an empty string.
     * @param string $id (Optional) The id attribute of the form. Defaults to an empty string.
     * @return string The generated HTML for the opening `<form>` tag.
     */
    public static function buildStartForm(string $action, string $method, string $name = '', string $id = '', string $enctype = 'application/x-www-form-urlencoded'): string
    {
        return sprintf(
            '<form action="%s" method="%s" enctype="%s" name="%s" id="%s">',
            htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($method, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($enctype, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($id, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generates the closing HTML `</form>` tag with optional buttons and line breaks.
     *
     * This method appends a series of buttons and optional line breaks before and after
     * the buttons to the closing `</form>` tag. The buttons are provided as an array of
     * HTML strings, and the alignment parameter is currently unused.
     *
     * - If the `$buttons` is not an array, the method simply returns the `</form>` tag.
     * - Line breaks before and after the buttons are added using the `$lineBreaksBefore`
     *   and `$lineBreaksAfter` parameters.
     *
     * @param array $buttons Array containing HTML strings for form buttons.
     * @param int $lineBreaksBefore (Optional) Number of line breaks to add before the buttons. Defaults to 0.
     * @param int $lineBreaksAfter (Optional) Number of line breaks to add after the buttons. Defaults to 0.
     * @return string The generated HTML for the buttons and the closing `</form>` tag.
     */
    public static function buildEndFormWithButtons(array $buttons, int $lineBreaksBefore = 1, int $lineBreaksAfter = 0): string
    {
        if (!is_array($buttons)) {
            return '</form>';
        }

        $html = '<div class="form-actions">';
        foreach ($buttons as $button) {
            $html .= $button;
        }
        $html .= '</div></form>';

        return $html;
    }

    /**
     * Generates an HTML heading for a form section.
     *
     * This method creates a styled HTML `div` element to display a heading
     * for a form section. The heading is styled with the `well well-small`
     * classes and bold font weight.
     *
     * @param string $text The text to display as the form section heading.
     * @return string The generated HTML for the form section heading.
     */
    public static function buildFormSectionHeading(string $text): string
    {
        return sprintf('<div class="well well-small" style="font-weight: bold;">%s</div>', $text);
    }

    /**
     * Renders a form field with label and element in a grid layout.
     *
     * This method generates HTML for a grid layout containing form fields.
     * Each field consists of a label and a corresponding form element.
     * - If the label is not empty, it is displayed using a `<label>` tag.
     * - The form element is added directly after the label.
     *
     * @param array $labelFormElementArray An associative array where the key is the label text
     *                                     and the value is the HTML for the form element.
     * @return string The generated HTML for the form fields in a grid layout.
     */
    public static function buildFormFieldsGrid(array $labelFormElementArray): string
    {
        $html = '<div class="form-grid">';
        $fieldIndex = 0;
        foreach ($labelFormElementArray as $label => $element) {
            $fieldIndex++;
            $labelText = trim((string)$label);
            if ($labelText === '') {
                $html .= $element;
                continue;
            }
            [$element, $controlId] = self::associateControlId((string)$element, 'field-' . $fieldIndex);
            $for = $controlId !== '' ? ' for="' . htmlspecialchars($controlId, ENT_QUOTES, 'UTF-8') . '"' : '';
            $html .= '<div class="field">';
            $html .= '<label class="label"' . $for . '>' . htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') . '</label>';
            $html .= $element;
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Point the field label at the first control when that control has no id yet.
     *
     * @return array{0: string, 1: string}
     */
    private static function associateControlId(string $element, string $fallbackId): array
    {
        static $sequence = 0;
        $sequence++;
        $fallbackId .= '-' . $sequence;
        if (preg_match_all('/<(input|select|textarea)\b([^>]*)>/', $element, $matches, PREG_OFFSET_CAPTURE) !== false) {
            foreach ($matches[0] as $index => $full) {
                $tag = $matches[1][$index][0];
                $attrs = $matches[2][$index][0];
                if ($tag === 'input' && preg_match('/\btype\s*=\s*"hidden"/i', $attrs) === 1) {
                    continue;
                }
                if (preg_match('/\bid="([^"]+)"/', $attrs, $idMatch) === 1) {
                    return [$element, $idMatch[1]];
                }
                $id = htmlspecialchars($fallbackId, ENT_QUOTES, 'UTF-8');
                $replacement = '<' . $tag . ' id="' . $id . '"' . $attrs . '>';
                $element = substr_replace($element, $replacement, $full[1], strlen($full[0]));

                return [$element, $fallbackId];
            }
        }

        return [$element, ''];
    }

    /**
     * Confirm handler for a destructive link. Literal "\n" from language files become line breaks.
     */
    public static function confirmAttribute(string $message): string
    {
        $message = str_replace('\\n', "\n", $message);
        return htmlspecialchars(
            'return confirm(' . json_encode($message, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ')',
            ENT_QUOTES,
            'UTF-8'
        );
    }

    /**
     * Searchable collection of records.
     *
     * The name opens the record. Other verbs are buttons at the end of the row.
     * Groups are optional: omit "label" for a flat list.
     *
     * @param array{
     *   column?: string,
     *   searchLabel?: string,
     *   primary?: array{href: string, label: string},
     *   empty?: string,
     *   noMatch?: string,
     *   groups: array<int, array{label?: string, rows: array<int, array{
     *     name: string,
     *     href: string,
     *     meta?: string,
     *     metaTitle?: string,
     *     search?: string,
     *     actions?: array<int, array{href: string, label: string, tone?: string, confirm?: string}>
     *   }>}>
     * } $list
     */
    /**
     * @return list<array{key: string, label: string}>
     */
    public static function itemListColumns(): array
    {
        return [
            ['key' => 'type', 'label' => TXT_119],
            ['key' => 'opened', 'label' => TXT_103],
        ];
    }

    /**
     * Logs, attachments, security, print, and delete for one item.
     *
     * @return list<array{href: string, label: string, tone?: string, confirm?: string, target?: string}>
     */
    public static function itemActions(int $itemId, string $base): array
    {
        $actions = [];
        $role = (int) ($_SESSION['access_role_id'] ?? 5);
        if ($role <= 4) {
            $actions[] = ['href' => $base . '&item=' . $itemId . '&option=log_entry', 'label' => TXT_246];
            $actions[] = ['href' => $base . '&item=' . $itemId . '&option=show_attachments', 'label' => TXT_389];
        }
        if ($role <= 3) {
            $actions[] = ['href' => $base . '&item=' . $itemId . '&option=change_security', 'label' => TXT_28];
        }
        if ($role <= 5) {
            $actions[] = [
                'href' => \Adlexone\Http\Router::manageUrl('print', 'item=' . $itemId),
                'label' => TXT_625,
                'target' => '_blank',
            ];
        }
        if ($role <= 2) {
            $actions[] = [
                'href' => $base . '&item=' . $itemId . '&option=delete_item',
                'label' => TXT_315,
                'tone' => 'danger',
                'confirm' => TXT_400,
            ];
        }

        return $actions;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array{href: string, label: string, tone?: string, confirm?: string, target?: string}> $actions
     * @return array{name: string, href: string, meta: string, cells: array{type: string, opened: string}, actions: array}
     */
    public static function itemRecord(array $item, string $href, array $actions): array
    {
        static $typeNames = [];
        $itemId = (int) ($item['item_id'] ?? 0);
        $title = trim((string) ($item['item_title'] ?? ''));
        if ($title === '') {
            $title = (string) TXT_357;
        }
        $typeId = (string) ($item['item_type_id'] ?? '');
        if (!array_key_exists($typeId, $typeNames)) {
            $typeRow = Database::first('item_types', ['item_type_name'], 'item_type_id = ?', [$typeId]);
            $typeNames[$typeId] = (string) ($typeRow['item_type_name'] ?? '');
        }

        return [
            'name' => $title,
            'href' => $href,
            'meta' => self::getLanguageConstant('LA_102', 'TXT_102') . ' ' . $itemId,
            'cells' => [
                'type' => $typeNames[$typeId],
                'opened' => date(SET_DATE_FORMAT, (int) ($item['create_date'] ?? 0)),
            ],
            'actions' => $actions,
        ];
    }

    public static function buildRecordList(array $list): string
    {
        static $sequence = 0;
        $sequence++;
        $id = 'record-list-' . $sequence;

        $column = (string)($list['column'] ?? TXT_151);
        $columns = [];
        foreach ($list['columns'] ?? [] as $columnDef) {
            if (!is_array($columnDef)) {
                continue;
            }
            $key = trim((string)($columnDef['key'] ?? ''));
            $label = trim((string)($columnDef['label'] ?? ''));
            if ($key === '' || $label === '') {
                continue;
            }
            $columns[] = ['key' => $key, 'label' => $label, 'wrap' => !empty($columnDef['wrap'])];
        }
        $searchLabel = (string)($list['searchLabel'] ?? TXT_3);
        $emptyText = (string)($list['empty'] ?? TXT_115);
        $noMatch = (string)($list['noMatch'] ?? TXT_689);
        $groups = $list['groups'] ?? [];
        $primary = self::recordListPrimary($list['primary'] ?? null);

        $rows = [];
        $hasActions = false;
        foreach ($groups as $group) {
            foreach ($group['rows'] ?? [] as $row) {
                $rows[] = $row;
                if (!empty($row['actions'])) {
                    $hasActions = true;
                }
            }
        }

        $toolbar = (string) ($list['toolbar'] ?? '');
        if ($rows === []) {
            $bar = $primary . $toolbar;
            return '<div class="record-list">'
                . ($bar !== '' ? '<div class="record-list__bar">' . $bar . '</div>' : '')
                . '<p class="record-list__empty">' . htmlspecialchars($emptyText, ENT_QUOTES, 'UTF-8') . '</p>'
                . '</div>';
        }

        $colCount = 1 + count($columns) + ($hasActions ? 1 : 0);
        $body = '';
        foreach ($groups as $group) {
            $groupRows = $group['rows'] ?? [];
            if ($groupRows === []) {
                continue;
            }
            $label = trim((string)($group['label'] ?? ''));
            $body .= '<tbody data-group>';
            if ($label !== '') {
                $body .= '<tr class="record-list__grouphead"><th colspan="' . $colCount . '" scope="rowgroup">'
                    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                    . ' <span class="record-list__badge">' . count($groupRows) . '</span></th></tr>';
            }
            foreach ($groupRows as $row) {
                $body .= self::recordListRow($row, $label, $hasActions, $columns);
            }
            $body .= '</tbody>';
        }

        $columnHeads = '';
        foreach ($columns as $columnDef) {
            $wrap = !empty($columnDef['wrap']) ? ' record-list__cell--wrap' : '';
            $columnHeads .= '<th class="record-list__cell' . $wrap . '">' . htmlspecialchars($columnDef['label'], ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $manageHead = $hasActions
            ? '<th class="record-list__manage"><span class="record-list__sr">' . htmlspecialchars(TXT_388, ENT_QUOTES, 'UTF-8') . '</span></th>'
            : '';
        $searchId = $id . '-search';

        return '<div class="record-list" id="' . $id . '">'
            . '<div class="record-list__bar">'
            . '<input id="' . $searchId . '" class="input record-list__search" type="search" placeholder="' . htmlspecialchars($searchLabel, ENT_QUOTES, 'UTF-8') . '" aria-label="' . htmlspecialchars($searchLabel, ENT_QUOTES, 'UTF-8') . '" autocomplete="off">'
            . '<span class="record-list__count" aria-live="polite">' . count($rows) . '</span>'
            . $toolbar
            . $primary
            . '</div>'
            . '<div class="record-list__tablewrap"><table class="table table-hover">'
            . '<thead><tr><th>' . htmlspecialchars($column, ENT_QUOTES, 'UTF-8') . '</th>' . $columnHeads . $manageHead . '</tr></thead>'
            . $body
            . '</table></div>'
            . '<p class="record-list__nomatch" hidden>' . htmlspecialchars($noMatch, ENT_QUOTES, 'UTF-8') . '</p>'
            . self::recordListScript($id)
            . '</div>';
    }

    /**
     * @param array{href: string, label: string}|null $primary
     */
    private static function recordListPrimary(?array $primary): string
    {
        if ($primary === null || ($primary['href'] ?? '') === '' || ($primary['label'] ?? '') === '') {
            return '';
        }
        return '<a class="btn btn--primary btn--sm" href="' . htmlspecialchars((string)$primary['href'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars((string)$primary['label'], ENT_QUOTES, 'UTF-8') . '</a>';
    }

    /**
     * @param array{name: string, href: string, meta?: string, metaTitle?: string, search?: string, cells?: array<string, string>, actions?: array<int, array{href: string, label: string, tone?: string, confirm?: string, target?: string}>} $row
     * @param list<array{key: string, label: string}> $columns
     */
    private static function recordListRow(array $row, string $groupLabel, bool $hasActions, array $columns = []): string
    {
        $name = (string)($row['name'] ?? '');
        $meta = (string)($row['meta'] ?? '');
        $search = strtolower($name . ' ' . $groupLabel . ' ' . $meta . ' ' . (string)($row['search'] ?? ''));
        $nameHtml = '<a class="record-link" href="' . htmlspecialchars((string)$row['href'], ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a>';
        if ($meta !== '') {
            $title = (string)($row['metaTitle'] ?? '');
            $titleAttr = $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '"' : '';
            $nameHtml .= '<div class="record-list__meta"' . $titleAttr . '>' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</div>';
            $search .= ' ' . strtolower($title);
        }

        $cellsHtml = '';
        $cellValues = is_array($row['cells'] ?? null) ? $row['cells'] : [];
        foreach ($columns as $columnDef) {
            $value = trim((string)($cellValues[$columnDef['key']] ?? ''));
            $search .= ' ' . strtolower($value);
            $wrap = !empty($columnDef['wrap']) ? ' record-list__cell--wrap' : '';
            $cellsHtml .= '<td class="record-list__cell' . $wrap . '">'
                . ($value === '' ? '—' : htmlspecialchars($value, ENT_QUOTES, 'UTF-8'))
                . '</td>';
        }

        $actionsHtml = '';
        if ($hasActions) {
            $actions = is_array($row['actions'] ?? null) ? $row['actions'] : [];
            foreach ($actions as $action) {
                $search .= ' ' . strtolower((string)($action['label'] ?? ''));
            }
            $actionsHtml = '<td class="record-list__manage">' . self::recordListActions($actions, $name) . '</td>';
        }

        return '<tr data-search="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '"><td>' . $nameHtml . '</td>' . $cellsHtml . $actionsHtml . '</tr>';
    }

    /**
     * @param array<int, array{href?: string, label?: string, tone?: string, confirm?: string, target?: string}> $actions
     */
    private static function recordListActions(array $actions, string $name): string
    {
        if ($actions === []) {
            return '';
        }
        $asMenu = count($actions) >= 2;
        $buttons = '';
        foreach ($actions as $action) {
            $label = (string)($action['label'] ?? '');
            $confirm = isset($action['confirm']) && $action['confirm'] !== ''
                ? ' onclick="' . self::confirmAttribute((string)$action['confirm']) . '"'
                : '';
            $target = (string)($action['target'] ?? '');
            $targetAttr = $target !== '' ? ' target="' . htmlspecialchars($target, ENT_QUOTES, 'UTF-8') . '"' : '';
            $href = htmlspecialchars((string)($action['href'] ?? ''), ENT_QUOTES, 'UTF-8');
            $aria = ' aria-label="' . htmlspecialchars($label . ': ' . $name, ENT_QUOTES, 'UTF-8') . '"';
            if ($asMenu) {
                $danger = ($action['tone'] ?? '') === 'danger' ? ' rowmenu__item--danger' : '';
                $buttons .= '<a class="rowmenu__item' . $danger . '" role="menuitem" href="' . $href . '"'
                    . $targetAttr . $confirm . $aria . '>'
                    . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
                continue;
            }
            $tone = ($action['tone'] ?? '') === 'danger' ? 'btn--danger' : 'btn--quiet';
            $buttons .= '<a class="btn btn--sm ' . $tone . '" href="' . $href . '"'
                . $targetAttr . $confirm . $aria . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        if (!$asMenu) {
            return '<span class="record-list__actions">' . $buttons . '</span>';
        }

        return '<details class="rowmenu">'
            . '<summary class="btn btn--sm btn--quiet" aria-label="' . htmlspecialchars(TXT_388 . ': ' . $name, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(TXT_388, ENT_QUOTES, 'UTF-8') . '</summary>'
            . '<div class="rowmenu__panel" role="menu">' . $buttons . '</div>'
            . '</details>';
    }

    private static function recordListScript(string $id): string
    {
        $idJson = json_encode($id);
        return '<script>(function(root){if(!root)return;var input=root.querySelector(".record-list__search");if(!input)return;'
            . 'var count=root.querySelector(".record-list__count");var empty=root.querySelector(".record-list__nomatch");'
            . 'var total=root.querySelectorAll("tr[data-search]").length;function apply(){var q=(input.value||"").trim().toLowerCase();var shown=0;'
            . 'root.querySelectorAll("tr[data-search]").forEach(function(row){var hit=q===""||(row.getAttribute("data-search")||"").indexOf(q)!==-1;row.hidden=!hit;if(hit)shown++;});'
            . 'root.querySelectorAll("tbody[data-group]").forEach(function(group){var visible=group.querySelectorAll("tr[data-search]:not([hidden])").length;group.hidden=visible===0;var badge=group.querySelector(".record-list__badge");if(badge)badge.textContent=String(visible);});'
            . 'if(count)count.textContent=q===""?String(total):(shown+" / "+total);if(empty)empty.hidden=shown!==0;}'
            . 'input.addEventListener("input",apply);'
            . 'function place(menu){var panel=menu.querySelector(".rowmenu__panel");var summary=menu.querySelector("summary");if(!panel||!summary)return;var rect=summary.getBoundingClientRect();panel.style.top=(rect.bottom+4)+"px";panel.style.right=Math.max(8,window.innerWidth-rect.right)+"px";}'
            . 'root.querySelectorAll("details.rowmenu").forEach(function(menu){menu.addEventListener("toggle",function(){if(!menu.open)return;root.querySelectorAll("details.rowmenu[open]").forEach(function(other){if(other!==menu)other.open=false;});place(menu);});});'
            . 'document.addEventListener("click",function(event){if(event.target.closest(".rowmenu"))return;root.querySelectorAll("details.rowmenu[open]").forEach(function(menu){menu.open=false;});});'
            . 'window.addEventListener("resize",function(){root.querySelectorAll("details.rowmenu[open]").forEach(place);});'
            . '})(document.getElementById(' . $idJson . '));</script>';
    }

    /**
     * Renders content elements in a horizontal layout.
     *
     * This method generates HTML for displaying content elements in a horizontal layout.
     * Each content element is wrapped in a `<div>` with the class `hcc-item`.
     *
     * @param array $contentElements An array of content elements to be rendered.
     *                                Each element is expected to be a string containing HTML or text.
     * @return string The generated HTML for the horizontal content layout.
     */
    public static function renderHorizontalList(array $contentElements): string
    {
        $html = '<div class="horizontal-content-container">';
        foreach ($contentElements as $element) {
            $html .= '<div class="hcc-item">' . $element . '</div>';
        }
        return $html . '</div>';
    }

    /**
     * Generates an HTML `<select>` dropdown list.
     *
     * This method creates a dropdown list with the specified name, values, display values,
     * and a default selected value. Additional attributes can be added using the `$other` parameter.
     *
     * @param string $name The name attribute for the `<select>` element.
     * @param array $values An array of values for the `<option>` elements.
     * @param array $displayValues An array of display values corresponding to the `$values`.
     * @param mixed $selectedValue The value to be pre-selected in the dropdown.
     * @param string $other Additional attributes for the `<select>` element (e.g., JavaScript).
     * @return string The generated HTML for the dropdown list.
     */
    public static function buildSelectDropdown(string $name, ?array $values, ?array $displayValues, mixed $selectedValue, string $other = ''): string
    {
        $html = "<select name=\"{$name}\" class=\"select\" {$other}>";
        if (empty($values)) {
            $html .= '<option value="" selected>' . TXT_366 . '</option>';
        } else {
            foreach ($values as $i => $value) {
                $selected = ($value == $selectedValue) ? ' selected' : '';
                $html .= "<option value=\"{$value}\"{$selected}>{$displayValues[$i]}</option>";
            }
        }
        $html .= '</select>';
        return $html;
    }

    /**
     * Generates an HTML `<input>` text box.
     *
     * This method creates a text input field with the specified name, value, placeholder,
     * and an optional `readonly` attribute.
     *
     * @param string $name The name attribute for the `<input>` element.
     * @param mixed $value The value attribute for the `<input>` element.
     * @param string $placeholder (Optional) The placeholder text for the `<input>` element. Defaults to an empty string.
     * @param bool $readOnly (Optional) Whether the text box should be read-only. Defaults to false.
     * @return string The generated HTML for the text box.
     */
    public static function buildTextInput(string $name, mixed $value = '', string $placeholder = '', bool $readOnly = false): string
    {
        $value = $value ?? '';
        $readOnlyAttribute = $readOnly ? ' readonly' : '';
        return '<input type="text" name="' . $name . '" value="' . $value . '" placeholder="' . $placeholder . '" class="input"' . $readOnlyAttribute . '>';
    }

    /**
     * Generates an HTML file upload input box.
     *
     * This method creates a file input field with a hidden input to specify the maximum file size.
     * The file input field includes attributes for name, size, and CSS class.
     *
     * @param string $name The name attribute for the file input field.
     * @param string $maximumSize The maximum file size allowed, specified in bytes.
     * @param string $size (Optional) The size attribute for the file input field. Defaults to '50'.
     * @param string $class Unused. File fields use the standard input style.
     * @return string The generated HTML for the file upload input box.
     */
    public static function buildFileInput(string $name, string $maximumSize, string $size = '50', string $class = ''): string
    {
        return sprintf(
            '<input name="MAX_FILE_SIZE" value="%s" type="hidden"><input name="%s" type="file" class="input input-file">',
            htmlspecialchars($maximumSize, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generates HTML for date input fields.
     *
     * This method creates three text input fields for day, month, and year,
     * and formats them based on the specified date format. A separator is
     * used to join the fields, and the resulting HTML is returned.
     *
     * @param mixed $nameID The base name/id for the input fields.
     * @param mixed $value The value to populate in the input fields.
     * @param mixed $dateFormat The format of the date ('dmY', 'mdY', or default).
     *                          - 'dmY': Day-Month-Year
     *                          - 'mdY': Month-Day-Year
     *                          - default: Year-Month-Year
     * @param string $class (Optional) The CSS class for the input fields. Defaults to an empty string.
     * @return string The generated HTML for the date input fields.
     */
    public static function buildDateInputFields(string $nameID, string $value, string $dateFormat, string $class = ''): string
    {
        $day = self::buildTextInput('day_' . $nameID, $value);
        $month = self::buildTextInput('month_' . $nameID, $value);
        $year = self::buildTextInput('year_' . $nameID, $value);

        $separator = '-';
        $html = match ($dateFormat) {
            'dmY' => "{$day}{$separator}{$month}{$separator}{$year} " . TXT_382,
            'mdY' => "{$month}{$separator}{$day}{$separator}{$year} " . TXT_383,
            default => "{$year}{$separator}{$month}{$separator}{$year} " . TXT_382,
        };

        return $html;
    }

    /**
     * Generates an HTML password input field.
     *
     * This method creates a password input field with the specified name and value.
     * The input field is styled using the `form-control` CSS class.
     *
     * @param string $name The name attribute for the password input field.
     * @param string $value The value attribute for the password input field.
     * @return string The generated HTML for the password input field.
     */
    public static function buildPasswordInput(string $name, string $value): string
    {
        return sprintf('<input type="password" name="%s" value="%s" class="input">', $name, $value);
    }

    /**
     * Creates an HTML `<textarea>` element.
     *
     * This method generates a `<textarea>` element with the specified name, value,
     * number of rows, and an optional `readonly` attribute.
     *
     * @param string $name The name attribute for the `<textarea>` element.
     * @param mixed $value The content to be displayed inside the `<textarea>` element.
     * @param string $rows (Optional) The number of rows for the `<textarea>` element. Defaults to '3'.
     * @param bool $readOnly (Optional) Whether the `<textarea>` should be read-only. Defaults to false.
     * @return string The generated HTML for the `<textarea>` element.
     */
    public static function buildTextArea(string $name, mixed $value, string $rows = '3', bool $readOnly = false): string
    {
        $value = $value ?? '';
        return sprintf(
            '<textarea name="%s" rows="%s" class="input"%s>%s</textarea>',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($rows, ENT_QUOTES, 'UTF-8'),
            $readOnly ? ' readonly="readonly"' : '',
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generates an HTML `<input>` element of type "hidden".
     *
     * This method creates a hidden input field with the specified name and value.
     *
     * @param string $name The name attribute for the `<input>` element.
     * @param mixed $value The value attribute for the `<input>` element.
     * @return string The generated HTML for the hidden input field.
     */
    public static function buildHiddenInput(string $name, mixed $value): string
    {
        return sprintf('<input name="%s" type="hidden" value="%s">', $name, $value);
    }

    /**
     * RenderViews::buildFormButton()
     *
     * @param string $type Button type
     * @param string $name Button name
     * @param string $value Button value
     * @param string $javascript Optional raw attributes, such as an onclick handler.
     * @param string $variant primary, secondary, quiet, or danger. Submit defaults to primary; reset defaults to secondary.
     * @return string Returns form button HTML
     */
    public static function buildFormButton(string $type, string $name = '', string $value = '', string $javascript = '', string $variant = ''): string
    {
        if ($variant === '') {
            $variant = $type === 'reset' ? 'secondary' : 'primary';
        }
        $class = match ($variant) {
            'primary' => 'btn btn--primary',
            'quiet' => 'btn btn--quiet',
            'danger' => 'btn btn--danger',
            default => 'btn',
        };

        return sprintf(
            '<input type="%s" name="%s" value="%s" class="%s" %s>',
            htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            $class,
            $javascript
        );
    }

    /**
     * Generates an HTML checkbox input field wrapped in a label.
     *
     * This method creates a checkbox input field with the specified attributes,
     * including name, value, and optional CSS class, label, and JavaScript.
     * If the provided value matches the selected value, the checkbox will be pre-checked.
     *
     * @param string $name The name attribute for the checkbox input field.
     * @param string $value The value attribute for the checkbox input field.
     * @param string $selectedValue The value to compare with $value to determine if the checkbox is checked.
     * @param string $class (Optional) The CSS class for the label and checkbox. Defaults to 'checkbox'.
     * @param string $label (Optional) The text to display as the label for the checkbox. Defaults to an empty string.
     * @param string $javascript (Optional) Additional JavaScript or attributes for the checkbox input field. Defaults to an empty string.
     * @return string The generated HTML for the checkbox input field wrapped in a label.
     */
    public static function buildCheckBox(string $name, string $value, string $selectedValue, string $class = 'checkbox', string $label = '', string $javascript = ''): string
    {
        $checked = ($value == $selectedValue) ? 'checked' : '';
        return sprintf(
            '<label class="checkbox"><input name="%s" type="checkbox" value="%s" %s class="checkbox"> %s</label>',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            $javascript . ' ' . $checked,
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generates an HTML radio button input field.
     *
     * This method creates a radio button input field with the specified attributes,
     * including name, value, CSS class, and optional JavaScript. If the provided value
     * matches the selected value, the radio button will be pre-selected.
     *
     * @param string $name The name attribute for the radio button input field.
     * @param string $value The value attribute for the radio button input field.
     * @param string $selectedValue The value to compare with $value to determine if the radio button is selected.
     * @param string $class The CSS class for the radio button input field.
     * @param string $javascript (Optional) Additional JavaScript or attributes for the radio button input field. Defaults to an empty string.
     * @return string The generated HTML for the radio button input field.
     */
    public static function buildRadioButton(string $name, string $value, string $selectedValue, string $class, string $javascript = ''): string
    {
        $checked = ($value == $selectedValue) ? 'checked' : '';
        return sprintf(
            '<input name="%s" type="radio" value="%s" %s class="radio" %s>',
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
            $javascript,
            $checked
        );
    }

    /**
     * Generates an HTML response message and renders it within the application.
     *
     * This method creates a styled HTML response message consisting of bolded text
     * and an optional URL displayed in a separate `<div>`. The response message is
     * rendered using the `buildVerticalCards` method, and the resulting content is
     * included in the main page layout.
     *
     * - If the `$url` parameter is provided, it is included in the response message.
     * - The method defines the `BODY_CONTENT` constant with the generated HTML content.
     * - The `renderThemePage` method is used to include the main page layout.
     *
     * @param string $text The main response message to display. This is shown as bold text.
     * @param string $url (Optional) A URL or additional information to display below the message.
     *                    Defaults to an empty string. If provided, it is displayed in a separate `<div>`.
     * @return void This method does not return a value. It directly renders the response message.
     */
    public static function buildResponse(string $text, $url = ''): void
    {
        $message = trim($text);
        if (!defined('PAGE_TITLE')) {
            define('PAGE_TITLE', $message);
        }

        $detail = trim((string)$url);
        $follow = $detail !== '' ? self::formatResponseDetail($detail) : self::responseBackLink();
        $body = '';
        if (defined('APP_SECTION_NAV')) {
            $body .= '<p class="response__message">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        if ($follow !== '') {
            $body .= '<div class="response" role="status">' . $follow . '</div>';
        }

        define('BODY_CONTENT', $body);
        self::renderThemePage('main_page_content', SET_THEME);
    }

    /**
     * Supporting copy stays as text. A link built by this class stays HTML so it can be styled as an action.
     */
    private static function formatResponseDetail(string $detail): string
    {
        if (str_contains($detail, '<a ')) {
            return $detail;
        }

        return '<p class="response__detail">' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    /**
     * When a response has no follow-up of its own, offer a way back to the form that posted it.
     */
    private static function responseBackLink(): string
    {
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return '';
        }
        $host = (string)($parts['host'] ?? '');
        $serverHost = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '' && strcasecmp($host, preg_replace('/:\d+$/', '', $serverHost) ?? $serverHost) !== 0) {
            return '';
        }
        $path = (string)($parts['path'] ?? '');
        if (!str_ends_with($path, 'index.php')) {
            return '';
        }
        $href = 'index.php' . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $label = defined('TXT_31') ? TXT_31 : 'Back';

        return self::buildURL($href, $label, '', 'btn btn--primary btn--sm');
    }

    /**
     * Manipulates array keys with the 'VBL_' prefix based on the specified action.
     *
     * This method provides three operations for associative arrays:
     * - 'add': Adds the 'VBL_' prefix to keys that do not already have it.
     * - 'remove': Removes the 'VBL_' prefix from keys that have it, renaming them.
     * - 'unset': Removes all keys that start with 'VBL_' from the array.
     *
     * The function uses native PHP functions for efficient key manipulation.
     *
     * @param array $array The associative array to process.
     * @param string $action The action to perform: 'add', 'remove', or 'unset'.
     * @return array The modified array after applying the action.
     */
    public static function processVBLPrefixedKeys(array $array, string $action): array
    {
        switch ($action) {
            case 'add':
                // Add 'VBL_' prefix to keys that do not already have it
                foreach (array_keys($array) as $key) {
                    if (stripos($key, 'VBL_') !== 0) {
                        $array['VBL_' . $key] = $array[$key];
                        unset($array[$key]);
                    }
                }
                break;
            case 'remove':
                // Remove 'VBL_' prefix from keys that have it
                foreach (array_keys($array) as $key) {
                    if (stripos($key, 'VBL_') === 0) {
                        $array[substr($key, 4)] = $array[$key];
                        unset($array[$key]);
                    }
                }
                break;
            case 'unset':
                // Remove all keys that start with 'VBL_'
                $array = array_filter(
                    $array,
                    fn($k) => stripos($k, 'VBL_') !== 0,
                    ARRAY_FILTER_USE_KEY
                );
                break;
        }
        return $array;
    }

    /**
     * Generates an HTML anchor (`<a>`) element, optionally with an image.
     *
     * This method creates a hyperlink with optional CSS class, JavaScript attributes,
     * and target. If an image source is provided, the link will display the image;
     * otherwise, it displays the provided text.
     *
     * @param string $href The URL for the link.
     * @param string $text The text to display for the link.
     * @param string $class (Optional) CSS class for the link. Defaults to an empty string.
     * @param string $spriteName (Optional) Image source for the link. If provided, the image is shown instead of text. Sprite names:
     * ic-servicecentre               (Service Centre)
     * ic-announcements             (Announcements)
     * ic-search                   (Work)
     * ic-settings                 (Settings)
     *
     * ic-create-ticket                   (New)
     * ic-quick-search              (Quick Search)
     * ic-my-ticket-searches            (Searches)
     * ic-create-ticket-search              (Create A Search)
     *
     * ic-knowledgebase            (Knowledgebase)
     * ic-subjects                 (Knowledge Subjects)
     * ic-new-article              (New Article)
     * ic-article-search           (Article Search)
     * ic-kb-settings              (Knowledge Base Settings)
     *
     * ic-item-mgmt                (Item Management)
     * ic-custom-field-add         (Add Custom Field)
     * ic-itemtype-add             (Add Item Type)
     * ic-multilevel-menu          (Add Multi-Level Menu Relationships)
     * ic-manage-fields-types      (Manage Custom Fields and Item Types)
     *
     * ic-manage-users             (Manage Users and Groups)
     * ic-new-user                 (New User)
     * ic-new-group                (New Security Group)
     *
     * ic-manage-actions           (Manage Actions)
     * ic-new-action               (New Action)
     *
     * ic-adlexone-settings        (Adlexone Settings)
     * ic-inbound-email            (Inbound Email Settings)
     * ic-ldap                     (LDAP / Active Directory Settings)
     * ic-autologon                (Auto-Logon Settings)
     * ic-advanced                 (Advanced Settings)
     * ic-data-sharing             (Data Sharing Settings)
     * ic-data-source              (Data Source Settings)
     * ic-server                   (Alexone Server Settings)
     * @param string $javascript (Optional) Additional attributes or JavaScript for the link. Defaults to an empty string.
     * @param string $target (Optional) Target attribute for the link. Defaults to '_parent'.
     * @return string             The generated HTML for the anchor element.
     */
    public static function buildURL(string $href, string $text, string $spriteName = '', string $class = 'URL', string $javascript = '', string $target = '_parent'): string
    {
        $hrefEsc = htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
        $classEsc = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
        $targetEsc = htmlspecialchars($target, ENT_QUOTES, 'UTF-8');
        $jsAttr = $javascript !== '' ? ' ' . $javascript : '';

        // Build SVG sprite (if provided) and always include the escaped text afterwards
        $svg = '';
        if ($spriteName !== '') {
            $spriteEsc = htmlspecialchars($spriteName, ENT_QUOTES, 'UTF-8');
            $theme = defined('SET_THEME') ? rawurlencode((string) SET_THEME) : 'new';
            $svg = '<svg class="icon" aria-hidden="true"><use href="themes/' . $theme . '/assets/adlexone.sprite.svg#' . $spriteEsc . '"></use></svg>&nbsp;&nbsp;';
        }

        $textEsc = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        return '<a class="link ' . $classEsc . '" href="' . $hrefEsc . '"' . $jsAttr . ' target="' . $targetEsc . '">' . $svg . $textEsc . '</a>';
    }

//    /**
//     * Generates the closing HTML `</form>` tag.
//     *
//     * This method simply returns the closing `</form>` tag, which is used to
//     * terminate an HTML form. It does not include any additional content or attributes.
//     *
//     * @return string The closing `</form>` tag.
//     */
//    public static function endForm(): string
//    {
//        return '</form>';
//    }

    /**
     * Renders an HTML `<img>` element if enabled.
     *
     * Generates an image tag with the specified source, alt text, and optional JavaScript attributes.
     * Returns an empty string if the image is not enabled.
     *
     * @param string $imageSource The full path or URL to the image.
     * @param string $enabled Whether the image should be rendered ('Yes' to render).
     * @param string $altText Alternate text for the image.
     * @param string $javascript Optional JavaScript or additional attributes for the image tag.
     * @return string The generated HTML for the image, or an empty string if not enabled.
     */
    public static function buildImage(string $imageSource, string $enabled = 'Yes', string $altText = '', string $javascript = ''): string
    {
        return $enabled === 'Yes'
            ? '<img src="' . $imageSource . '" alt="' . $altText . '" ' . $javascript . '>'
            : '';
    }

    /**
     * Checks if the user's role level meets or exceeds the required role level
     * and returns the provided string if allowed.
     *
     * This method is used to conditionally return a string based on the user's role.
     * If the user's role level is less than the required role level, the method
     * returns `null`. Otherwise, it returns the provided string.
     *
     * @param mixed $value The data to return if the user's role is allowed.
     * @param int $userRole The user's role level. A lower value indicates higher permissions.
     * @param int $allowedRole The minimum role level required to access the string.
     * @return mixed The mixed value if the user's role is allowed, otherwise `null`.
     */
    public static function outputIfRoleAllowed(mixed $value, int $userRole, int $allowedRole): mixed
    {
        // Prefer permission-derived legacy role when the session is hydrated.
        $effective = \Adlexone\Auth\Access::legacyRole();
        if ($effective !== $userRole && isset($_SESSION['access_permissions'])) {
            $userRole = $effective;
        }
        return $allowedRole >= $userRole ? $value : null;
    }

    /**
     * Include content when the signed-in user has at least one of the permissions.
     */
    public static function outputIfAllowed(mixed $value, string ...$permissions): mixed
    {
        return \Adlexone\Auth\Access::can(...$permissions) ? $value : null;
    }

    /**
     * Terminates the application if the user's role level does not meet the required role level.
     *
     * This method checks if the user's role level is less than the allowed role level.
     * If the condition is met, it sets the page heading and body content, renders the
     * main page content, and terminates the script execution.
     *
     * @param int $userRole The user's role level. A lower value indicates higher permissions.
     * @param int $allowedRole The minimum role level required to proceed.
     *
     * @return void
     */
    public static function terminateIfRoleNotAllowed(int $userRole, int $allowedRole): void
    {
        $effective = \Adlexone\Auth\Access::legacyRole();
        if (isset($_SESSION['access_permissions'])) {
            $userRole = $effective;
        }
        // The greater the permissions, the lower the allowed role value
        if ($allowedRole < $userRole) {
            \Adlexone\Auth\Access::deny();
        }
    }

    /**
     * Stop the request unless the user has at least one of the named permissions.
     */
    public static function terminateUnlessAllowed(string ...$permissions): void
    {
        \Adlexone\Auth\Access::require(...$permissions);
    }

    /**
     * Returns the value of a language constant if defined, otherwise falls back to the original constant.
     *
     * This method checks if the constant specified by `$aliasValue` is defined.
     * If it is, the method returns its value. If not, it returns the value of the constant specified by `$originalValue`.
     *
     * @param string $aliasValue The name of the alias constant to check.
     * @param string $originalValue The name of the original constant to use as a fallback.
     * @return mixed The value of the defined constant.
     */
    public static function getLanguageConstant(string $aliasValue, string $originalValue): mixed
    {
        if (defined($aliasValue)) {
            return constant($aliasValue);
        }
        $prefix = self::applicationPrefix();
        if ($prefix !== null && defined($prefix . $aliasValue)) {
            return constant($prefix . $aliasValue);
        }
        return defined($originalValue) ? constant($originalValue) : $originalValue;
    }

    /**
     * Application wording for a shared screen. Falls back when this app has no override.
     */
    public static function applicationText(string $suffix, string $fallback): string
    {
        $prefix = self::applicationPrefix();
        if ($prefix !== null && defined($prefix . $suffix)) {
            return (string)constant($prefix . $suffix);
        }

        return $fallback;
    }

    public static function languageAlias(string $aliasValue, string $originalValue): mixed
    {
        return self::getLanguageConstant($aliasValue, $originalValue);
    }

    private static function applicationPrefix(): ?string
    {
        if (defined('APPLICATION_SLUG') && (string) APPLICATION_SLUG === 'service-centre') {
            return 'APP_SC_';
        }
        if ((string) ($_GET['app'] ?? '') === 'service-centre') {
            return 'APP_SC_';
        }

        $controller = (string)($_GET['controller'] ?? '');
        $prefixes = [
            'app_oneorzeroknowledgebase' => 'APP_KB_',
            'app_oneorzeroreportmanager' => 'APP_RM_',
            'app_oneorzerotimemanager' => 'APP_TM_',
        ];
        foreach ($prefixes as $needle => $prefix) {
            if (str_starts_with($controller, $needle)) {
                return $prefix;
            }
        }

        return null;
    }
}

