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
<main class="main" id="content" tabindex="-1">
    <?php echo $page->body(); ?>
</main>
</body>
</html>
