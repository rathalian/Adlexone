<?php
use Adlexone\Html\Html;
use Adlexone\Http\View;
use Adlexone\Theme\Theme;

/**
 * @var string $mode local or choose
 * @var string $error
 * @var list<\Adlexone\Auth\OauthConnection> $connections
 * @var bool $showLocal
 * @var bool $showOauth
 */
$defaultsInUse = \Adlexone\Platform\DefaultAccount::stillInUse();
?>
<!doctype html>
<html lang="en">
<head>
<?php View::partial('head', ['title' => 'Sign in — Adlexone']); ?>
</head>
<body class="page-login">
    <a class="skip-link" href="#content">Skip to the page content</a>
<main class="main" id="content">
    <div class="login-shell">
        <aside class="login-atmosphere" aria-hidden="true">
            <div class="login-atmosphere__copy">
                <div class="login-atmosphere__brand">
                    <div class="brandwrap">
                        <?php View::partial('brand_lockup'); ?>
                    </div>
                </div>
            </div>
            <div class="login-board">
                <div class="login-board__piece login-board__piece--ink">
                    <span class="login-board__label">Applications</span>
                    <span class="login-board__rule"></span>
                </div>
                <div class="login-board__piece login-board__piece--bone">
                    <span class="login-board__label">Work</span>
                    <span class="login-board__rule"></span>
                    <span class="login-board__rule login-board__rule--short"></span>
                </div>
                <div class="login-board__piece login-board__piece--stone">
                    <span class="login-board__label">Information</span>
                    <span class="login-board__rule"></span>
                    <span class="login-board__rule login-board__rule--short"></span>
                </div>
            </div>
        </aside>

        <div class="login-center">
            <div class="login-brand login-brand--mobile">
                <div class="brandwrap">
                    <?php View::partial('brand_lockup'); ?>
                </div>
            </div>
            <div class="login-card">
                <?php if ($error !== ''): ?>
                    <div class="login-error" role="alert"><?php echo Html::e($error); ?></div>
                <?php endif; ?>

                <?php if ($mode === 'local'): ?>
                    <section class="login-panel">
                        <h1 class="login-title">Sign in</h1>
                        <p class="login-lead">Use your username and password.</p>
                    <form method="post" action="?action=local" class="login-form">
                        <?php echo \Adlexone\Auth\Csrf::field(); ?>
                        <div class="field">
                            <label class="login-label label" for="access_user_name">Username</label>
                            <input class="input" type="text" id="access_user_name" name="access_user_name" value="<?php echo $defaultsInUse ? Html::e(\Adlexone\Platform\DefaultAccount::USERNAME) : ''; ?>" autocomplete="username" required autofocus>
                        </div>
                        <div class="field">
                            <label class="login-label label" for="access_password">Password</label>
                            <input class="input" type="password" id="access_password" name="access_password" value="<?php echo $defaultsInUse ? Html::e(\Adlexone\Platform\DefaultAccount::PASSWORD) : ''; ?>" autocomplete="current-password" required>
                        </div>
                        <button type="submit" class="btn btn--primary login-submit">Sign in</button>
                    </form>
                        <?php if ($showOauth): ?>
                            <p class="login-alt"><a href="?stay=1">Other sign-in options</a></p>
                        <?php endif; ?>
                    </section>
                <?php else: ?>
                    <section class="login-panel">
                        <h1 class="login-title">Sign in</h1>
                        <p class="login-lead">
                            <?php if ($showOauth && $showLocal): ?>
                                Sign in with OAuth (your organisation account), or use a username.
                            <?php elseif ($showOauth): ?>
                                Sign in with OAuth using your organisation account.
                            <?php else: ?>
                                Choose how to sign in.
                            <?php endif; ?>
                        </p>

                        <?php if ($showOauth): ?>
                            <div class="login-sso">
                                <?php foreach ($connections as $connection): ?>
                                    <a class="btn btn--primary login-sso-btn"
                                       href="?action=oauth_start&amp;connection=<?php echo rawurlencode($connection->id); ?>">
                                        <?php echo Html::e($connection->label); ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($showOauth && $showLocal): ?>
                            <div class="login-divider" role="separator"><span>or</span></div>
                        <?php endif; ?>

                        <?php if ($showLocal): ?>
                            <a class="btn login-local-btn" href="?action=local">Sign in with username</a>
                        <?php endif; ?>

                        <?php if (!$showOauth && !$showLocal): ?>
                            <p class="login-lead">No sign-in methods are turned on. An administrator can enable username sign-in or an identity provider under Settings, Sign-in.</p>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php View::partial('footer'); ?>
<script src="<?php echo Html::e(Theme::jsHref()); ?>" defer></script>
</body>
</html>
