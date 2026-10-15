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
 * Facade for the AIMS-era render helpers.
 *
 * Screens keep calling these methods. The work lives in Http\View, Html\Form,
 * Html\Blocks, Text\Wording, support\FormState, and Auth\Access.
 */

namespace Adlexone\support;

use Adlexone\Auth\Access;
use Adlexone\Html\Blocks;
use Adlexone\Html\Form;
use Adlexone\Http\View;
use Adlexone\Text\Wording;

class RenderViews
{
    /**
     * @param array<string, string> $fields
     * @param list<string> $buttons
     * @param array{method?: string, name?: string, id?: string, enctype?: string} $options
     */
    public static function buildForm(string $formTitle, string $formActionURL, array $fields, array $buttons, array $options = []): string
    {
        return Form::form($formTitle, $formActionURL, $fields, $buttons, $options);
    }

    /** $theme is kept for callers. The active skin is Theme::name(). */
    public static function renderThemePage(string $page, string $theme = ''): void
    {
        unset($theme);
        View::render($page);
    }

    /** @deprecated Use renderThemePage(); kept for older action scripts. */
    public static function renderPage(string $page, string $theme = ''): void
    {
        self::renderThemePage($page, $theme);
    }

    public static function includeControllerFile(?string $controller, string $defaultPage): void
    {
        View::includeController($controller, $defaultPage);
    }

    public static function buildLeftNavigationButton(string $queryString, string $buttonText, string|false $navBarTitle = false, bool $navBarDivider = false): string
    {
        return Blocks::sideButton($queryString, $buttonText, $navBarTitle, $navBarDivider);
    }

    public static function buildLeftNavigationCard(string $navigationButtons): string
    {
        return Blocks::sideCard($navigationButtons);
    }

    /** @param list<array<string, mixed>> $bodyBlocks */
    public static function buildVerticalCards(array $bodyBlocks): string
    {
        return Blocks::vertical($bodyBlocks);
    }

    /** @param list<array<string, mixed>> $blocks */
    public static function buildHorizontalCards(array $blocks, ?int $columns = null): string
    {
        return Blocks::horizontal($blocks, $columns);
    }

    public static function buildHorizontalSeparator(string $label = '', string $class = '', string $style = ''): string
    {
        return Blocks::separator($label, $class, $style);
    }

    public static function buildStartForm(string $action, string $method, string $name = '', string $id = '', string $enctype = 'application/x-www-form-urlencoded'): string
    {
        return Form::open($action, $method, $name, $id, $enctype);
    }

    /** @param list<string> $buttons */
    public static function buildEndFormWithButtons(array $buttons, int $lineBreaksBefore = 1, int $lineBreaksAfter = 0): string
    {
        unset($lineBreaksBefore, $lineBreaksAfter);

        return Form::close($buttons);
    }

    public static function buildFormSectionHeading(string $text): string
    {
        return Form::section($text);
    }

    /** @param array<mixed, mixed> $labelFormElementArray */
    public static function buildFormFieldsGrid(array $labelFormElementArray): string
    {
        return Form::fields($labelFormElementArray);
    }

    public static function confirmAttribute(string $message): string
    {
        return Form::confirm($message);
    }

    /** @return list<array{key: string, label: string}> */
    public static function itemListColumns(): array
    {
        return Blocks::itemListColumns();
    }

    /** @return list<array{href: string, label: string, tone?: string, confirm?: string, target?: string}> */
    public static function itemActions(int $itemId, string $base): array
    {
        return Blocks::itemActions($itemId, $base);
    }

    /**
     * @param array<string, mixed> $item
     * @param list<array{href: string, label: string, tone?: string, confirm?: string, target?: string}> $actions
     * @return array{name: string, href: string, meta: string, cells: array{type: string, opened: string}, actions: array}
     */
    public static function itemRecord(array $item, string $href, array $actions): array
    {
        return Blocks::itemRecord($item, $href, $actions);
    }

    /** @param array{illustration?: string, title?: string, copy?: string, action?: string} $opts */
    public static function buildEmptyState(array $opts): string
    {
        return Blocks::emptyState($opts);
    }

    /** @param array<string, mixed> $list */
    public static function buildRecordList(array $list): string
    {
        return Blocks::recordList($list);
    }

    /** @param list<string> $contentElements */
    public static function renderHorizontalList(array $contentElements): string
    {
        return Blocks::horizontalList($contentElements);
    }

    /** @param list<mixed>|null $values @param list<mixed>|null $displayValues */
    public static function buildSelectDropdown(string $name, ?array $values, ?array $displayValues, mixed $selectedValue, string $other = ''): string
    {
        return Form::select($name, $values, $displayValues, $selectedValue, $other);
    }

    public static function buildTextInput(string $name, mixed $value = '', string $placeholder = '', bool $readOnly = false): string
    {
        return Form::text($name, $value, $placeholder, $readOnly);
    }

    public static function buildFileInput(string $name, string $maximumSize, string $size = '50', string $class = ''): string
    {
        unset($size, $class);

        return Form::file($name, $maximumSize);
    }

    public static function buildDateInputFields(string $nameID, string $value, string $dateFormat, string $class = ''): string
    {
        unset($class);

        return Form::date($nameID, $value, $dateFormat);
    }

    public static function buildPasswordInput(string $name, string $value): string
    {
        return Form::password($name, $value);
    }

    public static function buildTextArea(string $name, mixed $value, string $rows = '3', bool $readOnly = false): string
    {
        return Form::textarea($name, $value, $rows, $readOnly);
    }

    public static function buildHiddenInput(string $name, mixed $value): string
    {
        return Form::hidden($name, $value);
    }

    public static function buildFormButton(string $type, string $name = '', string $value = '', string $javascript = '', string $variant = ''): string
    {
        return Form::button($type, $name, $value, $javascript, $variant);
    }

    public static function buildCheckBox(string $name, string $value, string $selectedValue, string $class = 'checkbox', string $label = '', string $javascript = ''): string
    {
        unset($class);

        return Form::checkbox($name, $value, $selectedValue, $label, $javascript);
    }

    public static function buildRadioButton(string $name, string $value, string $selectedValue, string $class, string $javascript = ''): string
    {
        unset($class);

        return Form::radio($name, $value, $selectedValue, $javascript);
    }

    public static function buildResponse(string $text, $url = ''): void
    {
        View::respond($text, (string) $url);
    }

    /** @param array<string, mixed> $array @return array<string, mixed> */
    public static function processVBLPrefixedKeys(array $array, string $action): array
    {
        return FormState::keys($array, $action);
    }

    public static function buildURL(string $href, string $text, string $spriteName = '', string $class = 'URL', string $javascript = '', string $target = '_parent', string $confirm = ''): string
    {
        return Blocks::link($href, $text, $spriteName, $class, $javascript, $target, $confirm);
    }

    public static function buildImage(string $imageSource, string $enabled = 'Yes', string $altText = '', string $javascript = ''): string
    {
        return Blocks::image($imageSource, $enabled, $altText, $javascript);
    }

    public static function outputIfRoleAllowed(mixed $value, int $userRole, int $allowedRole): mixed
    {
        return Access::outputIfRoleAllowed($value, $userRole, $allowedRole);
    }

    /** @deprecated alias of outputIfRoleAllowed */
    public static function secureOutput(mixed $value, int $userRole, int $allowedRole): mixed
    {
        return self::outputIfRoleAllowed($value, $userRole, $allowedRole);
    }

    public static function outputIfAllowed(mixed $value, string ...$permissions): mixed
    {
        return Access::outputIfAllowed($value, ...$permissions);
    }

    public static function terminateIfRoleNotAllowed(int $userRole, int $allowedRole): void
    {
        Access::terminateIfRoleNotAllowed($userRole, $allowedRole);
    }

    public static function terminateUnlessAllowed(string ...$permissions): void
    {
        Access::require(...$permissions);
    }

    public static function getLanguageConstant(string $aliasValue, string $originalValue): mixed
    {
        return Wording::get($aliasValue, $originalValue);
    }

    public static function applicationText(string $suffix, string $fallback): string
    {
        return Wording::application($suffix, $fallback);
    }

    public static function languageAlias(string $aliasValue, string $originalValue): mixed
    {
        return Wording::get($aliasValue, $originalValue);
    }
}
