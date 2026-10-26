<?php
declare(strict_types=1);

/**
 * Checks the shared breadcrumb: the trail always starts at Home, the last crumb is the current page,
 * labels are escaped, and the Page places the trail in the band, the section bar, or on its own line.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Adlexone\Html\Breadcrumb;
use Adlexone\Http\Page;

$failed = 0;

function check(bool $ok, string $message): void
{
    global $failed;
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL {$message}\n");
}

$trail = Breadcrumb::trail([['label' => 'Configure'], ['label' => 'Fields', 'href' => '?manage=fields'], ['label' => '  ']]);
check(count($trail) === 3, 'trail drops blank crumbs and adds Home');
check($trail[0] === ['label' => 'Home', 'href' => './'], 'trail starts at Home');

$html = Breadcrumb::render($trail, 'page');
check(str_contains($html, 'aria-label="Breadcrumb"'), 'render names the landmark');
check(substr_count($html, 'aria-current="page"') === 1, 'only one crumb is current');
check(str_contains($html, '<span aria-current="page">Fields</span>'), 'the last crumb is current and is not a link');
check(str_contains($html, '<a href="./">Home</a>'), 'Home is a link');
check(substr_count($html, 'crumbs__sep') === 2, 'a separator sits between crumbs, not before the first');
check(str_contains($html, '<span>Configure</span>') && !str_contains($html, '>Configure</a>'), 'a crumb without an href is not a link');
check(str_contains(Breadcrumb::render($trail, 'band'), 'crumbs--band'), 'band surface has its own class');
check(str_contains(Breadcrumb::render($trail, 'bar'), 'sectionbar__crumbs'), 'bar surface has the section bar class');
check(Breadcrumb::render([], 'page') === '', 'an empty trail renders nothing');

$escaped = Breadcrumb::render(Breadcrumb::trail([['label' => 'A <b>&"', 'href' => '?x=1&y=2'], ['label' => 'End']]), 'page');
check(str_contains($escaped, 'A &lt;b&gt;&amp;&quot;') && str_contains($escaped, 'href="?x=1&amp;y=2"'), 'labels and links are escaped');

$filled = Breadcrumb::fill('<header>' . Breadcrumb::BAND_SLOT . '</header>', $trail);
check(!str_contains($filled, '<!--crumbs') && str_contains($filled, 'crumbs--band'), 'fill replaces the band slot');
check(Breadcrumb::fill('<p>none</p>', $trail) === '<p>none</p>', 'fill leaves html without a slot alone');

$page = Page::reset();
check($page->standaloneCrumbs() === '', 'no trail, nothing to print');
$page->addCrumb('Ignored');
check($page->crumbs() === [], 'addCrumb does nothing before a trail is set');

$page->setCrumbs(Breadcrumb::trail([['label' => 'People', 'href' => '?manage=security']]));
$page->addCrumb('Add person');
$page->addCrumb('add person');
check(count($page->crumbs()) === 3 && $page->crumbs()[2]['label'] === 'Add person', 'addCrumb appends once');
check(str_contains($page->standaloneCrumbs(), 'Add person'), 'a page with no band or bar prints its own trail');

$page->setSectionNav('<div class="sectionbar">' . Breadcrumb::BAR_SLOT . '</div>');
check($page->standaloneCrumbs() === '', 'the trail is not printed twice when the section bar carries it');
check(str_contains($page->sectionNav(), 'sectionbar__crumbs') && str_contains($page->sectionNav(), 'Add person'), 'sectionNav fills the trail at output');

$page = Page::reset();
$page->setCrumbs(Breadcrumb::trail([['label' => 'Matters', 'href' => '?application=matters'], ['label' => 'Work']]));
$page->setAppHero('<header>' . Breadcrumb::BAND_SLOT . '</header>');
check(str_contains($page->appHero(), 'crumbs--band') && $page->standaloneCrumbs() === '', 'the band carries the trail for an application');

if ($failed > 0) {
    exit(1);
}
echo "breadcrumbs ok\n";
