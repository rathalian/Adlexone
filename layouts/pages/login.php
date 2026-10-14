<?php
use Adlexone\Theme\Theme;
$panelArt = Theme::illustrationHref('login-panel');
$homeLead = defined('SET_HOME_LEAD') && trim((string) SET_HOME_LEAD) !== ''
    ? (string) SET_HOME_LEAD
    : 'Create the records, rules, and screens your organisation works with every day.';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in — Inlay</title>
    <meta name="color-scheme" content="light">
    <link rel="icon" href="<?php echo htmlspecialchars(Theme::faviconHref(), ENT_QUOTES, 'UTF-8'); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::baseCssHref(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(Theme::cssHref(), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="page-login">
<main class="main" id="content">
    <div class="login-shell">
        <aside class="login-atmosphere" aria-hidden="true">
            <?php if ($panelArt !== ''): ?>
                <img class="login-atmosphere__art" src="<?php echo htmlspecialchars($panelArt, ENT_QUOTES, 'UTF-8'); ?>" alt="">
            <?php endif; ?>
            <div class="login-atmosphere__copy">
                <div class="login-atmosphere__brand">
                    <?php include Theme::layoutFs() . 'partials' . DIRECTORY_SEPARATOR . 'brand_lockup.php'; ?>
                </div>
                <p class="login-atmosphere__lead"><?php echo htmlspecialchars($homeLead, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </aside>

        <div class="login-center">
            <div class="login-brand login-brand--mobile">
                <div class="brandwrap">
                    <?php include Theme::layoutFs() . 'partials' . DIRECTORY_SEPARATOR . 'brand_lockup.php'; ?>
                </div>
            </div>
            <div class="login-card">
                <?php if (AUTH_ERROR_MESSAGE !== ''): ?>
                    <div class="login-error" role="alert"><?php echo htmlspecialchars(AUTH_ERROR_MESSAGE, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if (AUTH_PAGE_MODE === 'local'): ?>
                    <section class="login-panel">
                        <h1 class="login-title">Sign in</h1>
                        <p class="login-lead">Use your username and password.</p>
                    <form method="post" action="?action=local" class="login-form">
                        <div class="field">
                            <label class="login-label label" for="access_user_name">Username</label>
                            <input class="input" type="text" id="access_user_name" name="access_user_name" autocomplete="username" required autofocus>
                        </div>
                        <div class="field">
                            <label class="login-label label" for="access_password">Password</label>
                            <input class="input" type="password" id="access_password" name="access_password" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn--primary login-submit">Sign in</button>
                    </form>
                        <?php if (AUTH_PAGE_SHOW_OAUTH): ?>
                            <p class="login-alt"><a href="?stay=1">Other sign-in options</a></p>
                        <?php endif; ?>
                    </section>
                <?php else: ?>
                    <section class="login-panel">
                        <h1 class="login-title">Sign in</h1>
                        <p class="login-lead">
                            <?php if (AUTH_PAGE_SHOW_OAUTH && AUTH_PAGE_SHOW_LOCAL): ?>
                                Sign in with OAuth (your organisation account), or use a username.
                            <?php elseif (AUTH_PAGE_SHOW_OAUTH): ?>
                                Sign in with OAuth using your organisation account.
                            <?php else: ?>
                                Choose how you want to sign in to Inlay.
                            <?php endif; ?>
                        </p>

                        <?php if (AUTH_PAGE_SHOW_OAUTH): ?>
                            <div class="login-sso">
                                <?php foreach (AUTH_PAGE_CONNECTIONS as $connection): ?>
                                    <a class="btn btn--primary login-sso-btn"
                                       href="?action=oauth_start&amp;connection=<?php echo rawurlencode($connection->id); ?>">
                                        <?php echo htmlspecialchars($connection->label, ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (AUTH_PAGE_SHOW_OAUTH && AUTH_PAGE_SHOW_LOCAL): ?>
                            <div class="login-divider" role="separator"><span>or</span></div>
                        <?php endif; ?>

                        <?php if (AUTH_PAGE_SHOW_LOCAL): ?>
                            <a class="btn login-local-btn" href="?action=local">Sign in with username</a>
                        <?php endif; ?>

                        <?php if (!AUTH_PAGE_SHOW_OAUTH && !AUTH_PAGE_SHOW_LOCAL): ?>
                            <p class="login-lead">No sign-in methods are turned on. An administrator can enable username sign-in or an identity provider under Settings, Sign-in.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php include Theme::layoutFs() . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
<script src="<?php echo htmlspecialchars(Theme::jsHref(), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
</body>
</html>
