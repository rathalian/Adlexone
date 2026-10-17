<?php
declare(strict_types=1);

namespace Adlexone\Http;

/**
 * The page being built for this request.
 *
 * Screens describe the page here (title, section navigation, body). Nothing is
 * printed until send(), which runs once after the screen has returned and wraps
 * the result in one of the layouts under layouts/pages/.
 *
 * Layouts are the templates in layouts/pages/: main_page (shell with top bar),
 * full_page_view (print), response (bare message) and login.
 */
final class Page
{
    public const SHELL = 'main_page';
    public const PRINT = 'full_page_view';
    public const RESPONSE = 'response';
    public const LOGIN = 'login';

    private static ?self $current = null;

    private ?string $title = null;
    private ?string $eyebrow = null;
    private ?string $sectionNav = null;
    private ?string $navLabel = null;
    private string $navOption = '';
    private ?string $body = null;
    private string $applicationSlug = '';
    private int $navId = 0;
    private string $layout = self::RESPONSE;
    /** @var array<string, mixed> */
    private array $layoutVars = [];
    private bool $sent = false;

    public static function current(): self
    {
        return self::$current ??= new self();
    }

    /** Start from a blank page. Used by tests; a request builds one page. */
    public static function reset(): self
    {
        return self::$current = new self();
    }

    public function title(): string
    {
        return $this->title ?? '';
    }

    public function hasTitle(): bool
    {
        return $this->title !== null;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /** Sets the title only when no screen has chosen one. */
    public function suggestTitle(string $title): void
    {
        $this->title ??= $title;
    }

    public function eyebrow(): string
    {
        return $this->eyebrow ?? '';
    }

    public function suggestEyebrow(string $eyebrow): void
    {
        $this->eyebrow ??= $eyebrow;
    }

    public function hasSectionNav(): bool
    {
        return $this->sectionNav !== null;
    }

    public function sectionNav(): string
    {
        return $this->sectionNav ?? '';
    }

    public function setSectionNav(string $html): void
    {
        $this->sectionNav = $html;
    }

    public function hasNavLabel(): bool
    {
        return $this->navLabel !== null;
    }

    public function navLabel(): string
    {
        return $this->navLabel ?? '';
    }

    public function suggestNavLabel(string $label): void
    {
        $this->navLabel ??= $label;
    }

    /** Which section link is current when the request does not say (Security uses this). */
    public function navOption(): string
    {
        return $this->navOption;
    }

    public function setNavOption(string $option): void
    {
        $this->navOption = $option;
    }

    public function hasBody(): bool
    {
        return $this->body !== null;
    }

    public function body(): string
    {
        return $this->body ?? '';
    }

    public function setBody(string $html): void
    {
        $this->body = $html;
    }

    /** The first application context set for the request stays in force. */
    public function enterApplication(string $slug, int $navId): void
    {
        if ($this->applicationSlug === '') {
            $this->applicationSlug = $slug;
            $this->navId = $navId;
        }
    }

    public function applicationSlug(): string
    {
        return $this->applicationSlug;
    }

    public function inApplication(): bool
    {
        return $this->applicationSlug !== '';
    }

    public function navId(): int
    {
        return $this->navId;
    }

    /**
     * @param array<string, mixed> $vars passed to the layout as local variables
     */
    public function useLayout(string $layout, array $vars = []): void
    {
        $this->layout = $layout;
        $this->layoutVars = $vars;
    }

    public function layout(): string
    {
        return $this->layout;
    }

    /** Renders the layout once. A second call, for example after exit paths, does nothing. */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }
        $this->sent = true;

        $vars = $this->layoutVars + ['page' => $this];
        if ($this->layout === self::SHELL) {
            $vars['nav'] = TopNav::build();
        }
        View::render($this->layout, $vars);
    }

    /** Text for the document <title>. */
    public function documentTitle(): string
    {
        return $this->title !== null && $this->title !== '' ? $this->title . ' — Inlay' : 'Inlay';
    }
}
