<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Adlexone</title>
    <meta name="color-scheme" content="light dark">
    <link rel="icon" href="themes/new/assets/brand/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="themes/new/css/style.css">
</head>
<body>
<div class="brandbar">
    <div class="brandrow">
        <div class="brandwrap">
            <div class="logoA" aria-label="Adlexone logo">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3 L20 21 H16 L14.6 18 H9.4 L8 21 H4 L12 3 Z M10.7 14h2.6L12 9.8 10.7 14Z"
                          fill="white"/>
                </svg>
            </div>
            <div class="brandtext"><span class="title-static">dlexone</span></div>
        </div>


    </div>
    <div class="underline" aria-hidden="true"></div>
</div>

<!-- Main content area rendered by the controller including the left navigation card and the body card    -->
<main class="main" id="content">
    <div class="login-center">
        <div class="login-card">
            <?php echo showLoginData(); ?>
        </div>
    </div>
</main>

<p align="center" class="modal-footer">
    Adlexone Version <?php echo VERSION; ?>
    <br>
    <a href="http://www.adlexone.com" target="_blank">&copy;2025 Adlexone</a></span>
</p>
<script src="themes/new/js/app.js" defer></script>
</body>
</html>