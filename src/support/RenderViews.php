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
     *
     * @return string This method does not return a value. Instead, it defines the `BODY_CONTENT`
     *              constant with the generated HTML and includes the main page layout.
     */
    public static function buildForm(string $formTitle, string $formActionURL, array $fields, array $buttons): string
    {
        // Start building the form HTML with the opening <form> tag
        $html = self::buildStartForm($formActionURL, 'post');

        // Render the form fields in a grid layout
        $html .= self::buildFormFieldsGrid($fields);

        // Add the form buttons and close the <form> tag
        $html .= self::buildEndFormWithButtons($buttons);

        // Wrap the form in a vertical card layout for better visual structure
        $bodyBlocks = [
            [
                'title' => $formTitle, // Title of the card
                'html' => $html,      // Form HTML content
            ],
        ];

        // Generate the final HTML for the vertical card layout and return it to the calling method
        return self::buildVerticalCards($bodyBlocks);

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
    public static function includeControllerFile(string $controller, string $defaultPage): void
    {
        // Use defaultPage if controller is null or empty
        $controller = $controller ?? $defaultPage;
        $controller = basename($controller);

        // Determine the controller path
        $controllerPath = str_starts_with($controller, 'app_')
            ? "app/http/controllers/applications/" . explode('_', $controller)[1] . "/controllers/"
            : 'app/http/controllers/';

        // Include the controller file
        include $controllerPath . $controller . '.php';
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
        $html = '<div class="grid">';
        foreach ($bodyBlocks as $b) {
            $title = isset($b['title']) ? htmlspecialchars((string)$b['title'], ENT_QUOTES, 'UTF-8') : '';
            $html .= '<section class="card span-2">';                      // full width always
            if ($title !== '') {
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
    public static function buildStartForm(string $action, string $method, string $name = '', string $id = ''): string
    {
        return sprintf(
            '<form action="%s" method="%s" enctype="application/x-www-form-urlencoded" name="%s" id="%s">',
            htmlspecialchars($action, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($method, ENT_QUOTES, 'UTF-8'),
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

        $html = str_repeat('<br />', $lineBreaksBefore);

        foreach ($buttons as $button) {
            $html .= '&nbsp&nbsp' . $button;
        }

        $html .= str_repeat('<br />', $lineBreaksAfter);
        $html .= '</form>';

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
        foreach ($labelFormElementArray as $label => $element) {
            $html .= '<div class="field is-inline">';
            $html .= !empty($label) ? '<label class="label">' . htmlspecialchars((string)$label) . '</label>' : '';
            $html .= $element;
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
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
    public static function buildSelectDropdown(string $name, array $values, array $displayValues, mixed $selectedValue, string $other = ''): string
    {
        $html = "<select name=\"{$name}\" class=\"select\" {$other}>";
        if (!is_array($values)) {
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
     * @param string $class (Optional) The CSS class for the file input field. Defaults to 'btn btn-default'.
     * @return string The generated HTML for the file upload input box.
     */
    public static function buildFileInput(string $name, string $maximumSize, string $size = '50', string $class = 'btn btn-default'): string
    {
        return sprintf(
            '<input name="MAX_FILE_SIZE" value="%s" type="hidden"><input name="%s" size="%s" type="file" class="input-file btn btn-primary">',
            htmlspecialchars($maximumSize, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($size, ENT_QUOTES, 'UTF-8')
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
     * @param string $class Button class
     * @param string $javascript Button javascript
     * @return string Returns form button HTML
     */
    public static function buildFormButton(string $type, string $name = '', string $value = '', string $javascript = ''): string
    {
        return sprintf(
            '<input type="%s" name="%s" value="%s" class="btn btn-primary" %s>',
            htmlspecialchars($type, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8'),
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

        // Build the content block, omitting 'html' when no URL/content is provided
        $block = ['title' => $text];

        if ($url !== null && trim((string)$url) !== '') {
            $block['html'] = (string)$url;
        }

        $bodyBlocks = [$block];

        // Render the content blocks in a vertical layout
        $bodyContent = self::buildVerticalCards($bodyBlocks);

        // Define the BODY_CONTENT constant with the generated HTML
        define('BODY_CONTENT', $bodyContent);

        // Include the main page layout
        self::renderThemePage('main_page_content', SET_THEME);
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
     * ic-helpdesk                  (Helpdesk)
     * ic-announcements             (Show Announcements)
     * ic-search                   (Show Search)
     * ic-hd-settings                 (Helpdesk Settings)
     *
     * ic-create-ticket                   (Create A New Ticket)
     * ic-quick-search              (Quick Ticket Search)
     * ic-my-ticket-searches            (View My Ticket Searches)
     * ic-create-ticket-search              (Create A Ticket Search)
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
            $svg = '<svg class="icon" aria-hidden="true"><use href="themes/new/assets/adlexone.sprite.svg#' . $spriteEsc . '"></use></svg>&nbsp;&nbsp;';
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
        return $allowedRole >= $userRole ? $value : null;
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
        // The greater the permissions, the lower the allowed role value
        if ($allowedRole < $userRole) {
            self::buildResponse(TXT_356);
            die;
        }
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
        return defined($aliasValue) ? constant($aliasValue) : constant($originalValue);
    }
}

