<?php
declare(strict_types=1);

namespace Adlexone\Platform;

use Adlexone\Auth\Csrf;
use Adlexone\Html\Html;
use Adlexone\Http\Request;

/**
 * Shared page chrome for the self-hosted installer and the SaaS installer.
 */
final class InstallChrome
{
    public const PROVEN_KEY = 'adlexone_install_proven';

    public static function proven(): bool
    {
        return !empty($_SESSION[self::PROVEN_KEY]);
    }

    /**
     * The first POST of either server installer asks for the token. A match is
     * enough for every later step in this browser session.
     */
    public static function proveToken(string $tokenPath): void
    {
        if (self::proven()) {
            return;
        }
        if (!SetupToken::check((string) (Request::postedValue('setup_token') ?? ''), $tokenPath)) {
            throw new \RuntimeException('The setup token does not match ' . $tokenPath . '. Open that file on the server and enter the value. This page does not show it.');
        }
        session_regenerate_id(true);
        $_SESSION[self::PROVEN_KEY] = true;
    }

    public static function tokenBlockedMessage(): string
    {
        return 'The setup token would be saved inside the website, so this page will not write it. Use a document root that is a folder inside the account. The token then sits one folder higher, where a browser cannot read it.';
    }

    public static function openForm(string $action, string $tokenPath = '', string $idPrefix = 'setup_'): string
    {
        $html = '<form method="post" action="' . Html::e($action) . '" class="login-form install-form" autocomplete="off">' . Csrf::field();
        if ($tokenPath !== '' && !self::proven()) {
            $html .= self::field(
                'Setup token',
                'setup_token',
                'text',
                '',
                true,
                'Open ' . $tokenPath . ' on the server and paste the value. This page does not show it. One entry is enough for the rest of this installer.',
                '',
                'off',
                false,
                'spellcheck="false" autocapitalize="off"',
                $idPrefix
            );
        }

        return $html;
    }

    public static function outsideRootHelp(string $name, string $example): string
    {
        $web = InstallLayout::publicRoot();
        $where = $web !== '' ? ', which is ' . $web : '';

        return 'The ' . $name . ' must sit outside the public document root' . $where . '. Create it one directory up, then check it. For example, ' . $example . '.';
    }

    /**
     * Document root step shared by both installers.
     *
     * @param array<string, string> $paths
     */
    public static function placementForm(
        array $paths,
        bool $focus,
        string $action,
        string $idPrefix,
        string $helpHtml,
        string $tokenPath = ''
    ): string {
        $install = rtrim($paths['install'], '/\\');
        $package = InstallLayout::canonical($install);
        $public = InstallLayout::canonical($install . DIRECTORY_SEPARATOR . 'public');
        $doc = InstallLayout::canonical((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $detected = InstallLayout::detect($paths['install']);
        $selected = (string) (Request::postedValue('placement') ?? '');
        if ($selected === '' && ($detected === InstallLayout::PACKAGE || $detected === InstallLayout::PUBLIC_FOLDER)) {
            $selected = $detected;
        }

        $html = self::openForm($action, $tokenPath, $idPrefix);
        $html .= '<p class="install-section">Prerequisite</p>';
        $html .= self::facts([
            'Package' => $package !== '' ? $package : $install,
            'public folder' => $public !== '' ? $public : $install . DIRECTORY_SEPARATOR . 'public',
            'Document root' => $doc !== '' ? $doc : 'Not reported',
        ]);
        $html .= self::matchLine($detected);
        $note = InstallLayout::publicFolderNote($paths['install']);
        if ($note !== '') {
            $html .= '<p class="install-match">' . Html::e($note) . '</p>';
        }
        $html .= self::options(
            'Where is this copy of Adlexone?',
            'placement',
            [
                InstallLayout::PACKAGE => [
                    'The package is in the document root',
                    'The website folder is the unzipped package. This is the usual shared-hosting layout.',
                ],
                InstallLayout::PUBLIC_FOLDER => [
                    'The document root is the public folder',
                    'The website folder is public, inside the package. The rest of the package is off the website.',
                ],
            ],
            $selected,
            $focus,
            $idPrefix
        );
        $html .= self::actions('Continue', 'placement');
        $html .= '</form>';
        if ($helpHtml !== '') {
            $html .= self::dialog('install-help-root', 'Document root', $helpHtml);
        }

        return $html;
    }

    /**
     * @param array<string, string> $items id => label, in order
     * @param list<string> $plain ids that stay plain text even after they are done
     */
    public static function steps(
        array $items,
        string $current,
        string $hrefPrefix,
        array $plain = [],
        string $olClass = 'install-steps',
        bool $missingIsFirst = false
    ): string {
        $order = array_keys($items);
        $found = array_search($current, $order, true);
        $currentIndex = $missingIsFirst ? (int) $found : $found;
        $html = '<nav aria-label="Setup steps"><ol class="' . Html::e($olClass) . '">';
        foreach ($items as $id => $label) {
            $index = (int) array_search($id, $order, true);
            $class = $index === $currentIndex ? 'is-current' : ($index < $currentIndex ? 'is-done' : 'is-ahead');
            $inner = '<span class="install-steps__num">' . ($index + 1) . '</span><span class="install-steps__label">' . Html::e($label) . '</span>';
            if ($index < $currentIndex && !in_array($id, $plain, true)) {
                $item = '<a class="install-steps__item" href="' . Html::e($hrefPrefix . $id) . '">' . $inner . '</a>';
            } elseif ($index === $currentIndex) {
                $item = '<span class="install-steps__item" aria-current="step">' . $inner . '</span>';
            } else {
                $item = '<span class="install-steps__item">' . $inner . '</span>';
            }
            $html .= '<li class="' . $class . '">' . $item . '</li>';
        }

        return $html . '</ol></nav>';
    }

    public static function matchLine(string $detected): string
    {
        if ($detected === InstallLayout::PACKAGE) {
            return '<p class="install-match is-ok">This matches "The package is in the document root".</p>';
        }
        if ($detected === InstallLayout::PUBLIC_FOLDER) {
            return '<p class="install-match is-ok">This matches "The document root is the public folder".</p>';
        }

        return '<p class="install-match is-bad">This does not match either choice. Set the document root, then reload this page.</p>';
    }

    /**
     * @param array<string, array{0: string, 1: string}> $options
     */
    public static function options(
        string $legend,
        string $name,
        array $options,
        string $selected,
        bool $focus,
        string $idPrefix,
        string $labelledBy = ''
    ): string {
        $html = '<fieldset class="install-choice"' . ($labelledBy !== '' ? ' aria-labelledby="' . Html::e($labelledBy) . '"' : '') . '>';
        if ($legend !== '') {
            $html .= '<legend class="login-label label">' . Html::e($legend) . '</legend>';
        }
        $first = true;
        foreach ($options as $value => [$label, $help]) {
            $id = $idPrefix . $name . '_' . $value;
            $checked = $selected === (string) $value;
            $autofocus = $focus && ($checked || ($selected === '' && $first));
            $html .= '<label class="install-option" for="' . Html::e($id) . '">'
                . '<input class="radio" type="radio" name="' . Html::e($name) . '" id="' . Html::e($id) . '" value="' . Html::e((string) $value) . '"'
                . ($checked ? ' checked' : '')
                . ($autofocus ? ' autofocus' : '')
                . ' required>'
                . '<span class="install-option__text"><span class="install-option__title">' . Html::e($label) . '</span>'
                . '<span class="form-help">' . Html::e($help) . '</span></span></label>';
            $first = false;
        }

        return $html . '</fieldset>';
    }

    public static function field(
        string $label,
        string $name,
        string $type,
        string $value,
        bool $required,
        string $help = '',
        string $detailsId = '',
        string $autocomplete = '',
        bool $focus = false,
        string $attributes = '',
        string $idPrefix = 'setup_'
    ): string {
        $id = $idPrefix . $name;
        $class = 'input' . (str_ends_with($name, '_path') ? ' install-path' : '');
        $html = '<div class="field">';
        $html .= '<div class="install-labelrow">';
        $html .= '<label class="login-label label" for="' . Html::e($id) . '">' . Html::e($label) . '</label>';
        if ($detailsId !== '') {
            $html .= self::detailsButton($detailsId);
        }
        $html .= '</div>';
        $html .= '<input class="' . $class . '" type="' . Html::e($type) . '" id="' . Html::e($id) . '" name="' . Html::e($name) . '" value="' . Html::e($value) . '"'
            . ($required ? ' required' : '')
            . ($autocomplete !== '' ? ' autocomplete="' . Html::e($autocomplete) . '"' : '')
            . ($focus ? ' autofocus' : '')
            . ($attributes !== '' ? ' ' . $attributes : '')
            . '>';
        if ($help !== '') {
            $html .= '<p class="form-help">' . Html::e($help) . '</p>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param list<array{id: string, label: string, ok: bool, detail: string, state: string}> $checks
     */
    public static function checks(array $checks): string
    {
        if ($checks === []) {
            return '';
        }
        $html = '<ul class="install-checks">';
        foreach ($checks as $check) {
            $state = $check['state'];
            $result = $state === 'pass' ? 'Passed' : ($state === 'skip' ? 'Not tried' : 'Failed');
            $html .= '<li class="is-' . Html::e($state) . '">'
                . '<span class="install-checks__name">' . Html::e($check['label']) . '</span>'
                . '<span class="install-checks__result">' . $result . '</span>';
            if ($check['detail'] !== '') {
                $html .= '<span class="install-checks__detail">' . Html::e($check['detail']) . '</span>';
            }
            $html .= '</li>';
        }

        return $html . '</ul>';
    }

    /**
     * @param array<string, string> $rows
     */
    public static function facts(array $rows): string
    {
        $html = '<dl class="install-facts">';
        foreach ($rows as $label => $value) {
            $html .= '<div><dt>' . Html::e((string) $label) . '</dt><dd><code>' . Html::e($value) . '</code></dd></div>';
        }

        return $html . '</dl>';
    }

    public static function actions(
        string $primary,
        string $primaryValue,
        string $secondary = '',
        string $secondaryValue = '',
        string $backHref = ''
    ): string {
        $html = '<div class="install-actions">';
        if ($backHref !== '') {
            $html .= '<a class="btn" href="' . Html::e($backHref) . '">Back</a>';
        }
        if ($secondary !== '') {
            $html .= '<button type="submit" class="btn" name="setup_action" value="' . Html::e($secondaryValue) . '">' . Html::e($secondary) . '</button>';
        }
        $html .= '<button type="submit" class="btn btn--primary" name="setup_action" value="' . Html::e($primaryValue) . '">' . Html::e($primary) . '</button>';

        return $html . '</div>';
    }

    public static function detailsButton(string $id): string
    {
        return '<button type="button" class="install-help" data-sheet-pop aria-controls="' . Html::e($id) . '">Details</button>';
    }

    public static function dialog(string $id, string $title, string $body): string
    {
        return '<dialog id="' . Html::e($id) . '" class="sheet-pop" aria-labelledby="' . Html::e($id) . '-title">'
            . '<div class="sheet-pop__panel">'
            . '<div class="sheet-pop__head"><h2 class="sheet-pop__title" id="' . Html::e($id) . '-title">' . Html::e($title) . '</h2>'
            . '<button type="button" class="btn btn--sm sheet-pop__close" data-sheet-pop-close>Close</button></div>'
            . '<div class="sheet-pop__body setup-help">' . $body . '</div>'
            . '<div class="sheet-pop__actions"><button type="button" class="btn btn--primary" data-sheet-pop-close>Done</button></div>'
            . '</div></dialog>';
    }
}
