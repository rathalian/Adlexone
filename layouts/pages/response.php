<?php
use Adlexone\Http\View;

/** @var \Adlexone\Http\Page $page */
?>
<!doctype html>
<html lang="en">
<head>
<?php View::partial('head', ['title' => $page->title() !== '' ? $page->title() : 'Inlay']); ?>
</head>
<body>
    <a class="skip-link" href="#content">Skip to the page content</a>
<main class="main" id="content" tabindex="-1">
    <?php echo $page->body(); ?>
</main>
</body>
</html>
