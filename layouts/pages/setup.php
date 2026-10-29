<?php
use Adlexone\Html\Html;
use Adlexone\Http\View;
use Adlexone\Theme\Theme;

/**
 * @var string $title
 * @var string $documentTitle
 * @var string $lead
 * @var string $error
 * @var string $notice
 * @var string $helpHtml
 * @var string $formHtml
 * @var bool $installer
 * @var string $stepsHtml
 * @var string $headingAction
 * @var string $kicker
 */
$error = $error ?? '';
$notice = $notice ?? '';
$helpHtml = $helpHtml ?? '';
$formHtml = $formHtml ?? '';
$installer = !empty($installer);
$stepsHtml = $stepsHtml ?? '';
$headingAction = $headingAction ?? '';
$kicker = $kicker ?? 'Self-hosted installer';
$documentTitle = $documentTitle ?? ($title . ' — Adlexone');
?>
<!doctype html>
<html lang="en">
<head>
<?php View::partial('head', ['title' => $documentTitle]); ?>
</head>
<body class="page-login page-setup<?php echo $installer ? ' page-install' : ''; ?>">
    <a class="skip-link" href="#content">Skip to the page content</a>
<main class="main" id="content">
<?php if ($installer): ?>
    <div class="install">
        <div class="install__brand">
            <div class="brandwrap">
                <?php View::partial('brand_lockup'); ?>
            </div>
            <p class="install-kicker"><?php echo Html::e($kicker); ?></p>
        </div>
        <?php echo $stepsHtml; ?>
        <div class="login-panel install__panel">
            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?php echo Html::e($error); ?></div>
            <?php endif; ?>
            <?php if ($notice !== ''): ?>
                <div class="login-notice" role="status"><?php echo Html::e($notice); ?></div>
            <?php endif; ?>
            <div class="install-head">
                <h1 class="login-title"><?php echo Html::e($title); ?></h1>
                <?php echo $headingAction; ?>
            </div>
            <?php if (trim($lead) !== ''): ?>
                <p class="login-lead"><?php echo Html::e($lead); ?></p>
            <?php endif; ?>
            <?php echo $formHtml; ?>
        </div>
    </div>
<?php else: ?>
    <div class="login-shell">
        <aside class="login-atmosphere">
            <div class="login-atmosphere__copy">
                <div class="login-atmosphere__brand">
                    <div class="brandwrap">
                        <?php View::partial('brand_lockup'); ?>
                    </div>
                </div>
                <div class="setup-help">
                    <?php echo $helpHtml; ?>
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
                <?php if ($notice !== ''): ?>
                    <div class="login-notice" role="status"><?php echo Html::e($notice); ?></div>
                <?php endif; ?>
                <h1 class="login-title"><?php echo Html::e($title); ?></h1>
                <?php if (trim($lead) !== ''): ?>
                    <p class="login-lead"><?php echo Html::e($lead); ?></p>
                <?php endif; ?>
                <?php echo $formHtml; ?>
            </div>
        </div>
    </div>
<?php endif; ?>
</main>
<?php View::partial('footer'); ?>
<script src="<?php echo Html::e(Theme::jsHref()); ?>" defer></script>
</body>
</html>
