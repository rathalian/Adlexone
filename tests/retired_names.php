<?php
declare(strict_types=1);

$failed = 0;
$check = static function (bool $ok, string $message) use (&$failed): void {
    if ($ok) {
        echo "ok  {$message}\n";
        return;
    }
    $failed++;
    echo "FAIL {$message}\n";
};

$root = dirname(__DIR__);
$read = static function (string $path) use ($root): string {
    $file = $root . '/' . $path;
    $contents = file_get_contents($file);
    return is_string($contents) ? $contents : '';
};

$auth = $read('src/Auth/AuthConfig.php');
$permission = $read('src/Auth/Permission.php');
$rule = $read('.cursor/rules/form-rendering.mdc');
$src = '';
foreach (['src', 'layouts', 'bootstrap', 'tests'] as $tree) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $tree, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        if ($file->getFilename() === 'retired_names.php') {
            continue;
        }
        $chunk = file_get_contents($file->getPathname());
        if (is_string($chunk)) {
            $src .= $chunk;
        }
    }
}

$check(!str_contains($auth, 'function oidcEnabled'), 'AuthConfig no longer defines oidcEnabled');
$check(!str_contains($auth, 'function oidcAutoRedirect'), 'AuthConfig no longer defines oidcAutoRedirect');
$check(!str_contains($auth, 'function providers'), 'AuthConfig no longer defines providers');
$check(!str_contains($auth, 'function provider'), 'AuthConfig no longer defines provider');
$check(!str_contains($auth, 'AUTH_OIDC_ENABLED'), 'AuthConfig no longer reads AUTH_OIDC_ENABLED');
$check(!str_contains($auth, 'AUTH_OIDC_AUTO_REDIRECT'), 'AuthConfig no longer reads AUTH_OIDC_AUTO_REDIRECT');
$check(!str_contains($auth, 'AUTH_OIDC_PROVIDERS_JSON'), 'AuthConfig no longer reads AUTH_OIDC_PROVIDERS_JSON');
$check(!str_contains($permission, 'grantAppToAdmins'), 'Permission no longer defines grantAppToAdmins');

foreach ([
    'oidcEnabled(',
    'oidcAutoRedirect(',
    'grantAppToAdmins(',
    'AuthConfig::providers(',
    'AuthConfig::provider(',
] as $name) {
    $check(!str_contains($src, $name), $name . ' has no callers');
}

$check(str_contains($rule, 'Form::form()'), 'the form rule names Form::form');
$check(str_contains($rule, 'FieldsScreen::showSearchOptions()'), 'the form rule names the fields screen exemplar');
$check(str_contains($rule, 'Page::current()->setBody()'), 'the form rule sets the page body');
$check(!str_contains($rule, 'RenderViews'), 'the form rule no longer names RenderViews');
$check(!str_contains($rule, 'BODY_CONTENT'), 'the form rule no longer names BODY_CONTENT');
$check(!str_contains($rule, 'administration_item_settings'), 'the form rule no longer names the removed controller');

echo $failed === 0 ? "all checks passed\n" : "{$failed} check(s) failed\n";
exit($failed === 0 ? 0 : 1);
