<?php
use Adlexone\Theme\Theme;

/** @var string $responseTitle */
/** @var string $responseBody */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($responseTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="color-scheme" content="light">
    <link rel="icon" href="<?php echo htmlspecialchars(Theme::faviconHref(), ENT_QUOTES, 'UTF-8'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::baseCssHref(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::cssHref(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
<main class="main" id="content" tabindex="-1">
    <?php echo $responseBody; ?>
</main>
</body>
</html>
