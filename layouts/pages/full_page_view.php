<?php
use Adlexone\Http\View;

/** @var \Adlexone\Http\Page $page */
?>
<!doctype html>
<html lang="en">
<head>
<?php View::partial('head', ['title' => $page->documentTitle()]); ?>
</head>
<body class="page-print">
<main class="main" id="content">
    <?php echo $page->body(); ?>
</main>
<?php View::partial('footer'); ?>
</body>
</html>
