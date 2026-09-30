<?php
use Adlexone\Theme\Theme;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars((defined('PAGE_TITLE') && PAGE_TITLE !== '' ? PAGE_TITLE . ' — Inlay' : 'Inlay'), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="color-scheme" content="light">
    <link rel="icon" href="<?php echo htmlspecialchars(Theme::faviconHref(), ENT_QUOTES, 'UTF-8'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::baseCssHref(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::cssHref(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="page-print">
<main class="main" id="content">
    <?php echo defined('FULL_PAGE_CONTENT') ? FULL_PAGE_CONTENT : ''; ?>
</main>
<?php include Theme::layoutFs() . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
</body>
</html>
