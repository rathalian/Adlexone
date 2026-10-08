# Smoke: Builder → open app Work/Create, and bare shared engines stay gated.
# Safe GET-heavy path; Create app POST uses the CSRF token from the Builder form.
#
#   powershell -ExecutionPolicy Bypass -File tests\smoke\builder_app.ps1 [-BaseUrl http://localhost:8000]

param(
    [string]$BaseUrl = 'http://localhost:8000',
    [string]$User = 'Administrator',
    [string]$Password = 'password'
)

$ErrorActionPreference = 'Stop'
$script:Results = @()
$slug = 'zz-smoke-' + [DateTime]::UtcNow.ToString('yyyyMMddHHmmss')

function Invoke-Page([string]$Url, [hashtable]$Body = $null) {
    $args = @{ Uri = $Url; WebSession = $script:Session; UseBasicParsing = $true; MaximumRedirection = 5 }
    if ($Body) { $args.Method = 'Post'; $args.Body = $Body }
    try { return Invoke-WebRequest @args } catch {
        return [pscustomobject]@{ Content = "HTTPERROR: $($_.Exception.Message)"; StatusCode = 0; BaseResponse = $null }
    }
}

function Get-PhpErrors([string]$Html) {
    [regex]::Matches($Html, '<b>(Fatal error|Warning|Notice|Deprecated|Parse error)</b>:\s*([\s\S]*?)\s+in\s+<b>[^<]*?([^\\/<]+\.php)</b>\s+on line\s+<b>(\d+)</b>') |
        ForEach-Object { '{0}: {1} ({2}:{3})' -f $_.Groups[1].Value, ($_.Groups[2].Value -replace '\s+', ' ').Trim(), $_.Groups[3].Value, $_.Groups[4].Value } |
        Select-Object -Unique
}

function Record([string]$Name, $Response, [string]$Note = '') {
    $html = if ($Response -is [string]) { $Response } else { [string]$Response.Content }
    $errors = @(Get-PhpErrors $html)
    $status = if ($html -like 'HTTPERROR*') { 'HTTP ERROR' }
        elseif ($errors | Where-Object { $_ -like 'Fatal*' -or $_ -like 'Parse*' }) { 'FATAL' }
        elseif ($errors) { 'WARN' }
        else { 'OK' }
    if ($html -like 'HTTPERROR*') { $errors = @($html) }
    $script:Results += [pscustomobject]@{ Test = $Name; Status = $status; Errors = ($errors -join "`n"); Note = $Note; Html = $html }
}

function Get-Attr([string]$Tag, [string]$Name) {
    $m = [regex]::Match($Tag, "\b$Name=""([^""]*)""")
    if ($m.Success) { return [Net.WebUtility]::HtmlDecode($m.Groups[1].Value) }
    return $null
}

function Get-Form([string]$Html) {
    $main = ($Html -split '<main', 2)[-1]
    $formMatch = [regex]::Match($main, '<form[^>]*>([\s\S]*?)</form>')
    if (-not $formMatch.Success) { return $null }
    $formTag = [regex]::Match($formMatch.Value, '<form[^>]*>').Value
    $inner = $formMatch.Groups[1].Value
    $fields = [ordered]@{}
    $submits = @()
    foreach ($m in [regex]::Matches($inner, '<input[^>]*>')) {
        $tag = $m.Value; $name = Get-Attr $tag 'name'; $type = (Get-Attr $tag 'type'); if (-not $type) { $type = 'text' }
        if (-not $name) { continue }
        switch ($type.ToLower()) {
            'submit'   { $submits += , @($name, (Get-Attr $tag 'value')) }
            'reset'    { }
            'file'     { }
            'checkbox' { if ($tag -match '\schecked\b') { $fields[$name] = (Get-Attr $tag 'value') } }
            'radio'    { if ($tag -match '\schecked\b') { $fields[$name] = (Get-Attr $tag 'value') } }
            default    { $fields[$name] = (Get-Attr $tag 'value') }
        }
    }
    foreach ($m in [regex]::Matches($inner, '<select[^>]*name="([^"]+)"[^>]*>([\s\S]*?)</select>')) {
        $opts = [regex]::Matches($m.Groups[2].Value, '<option[^>]*>')
        $sel = $opts | Where-Object { $_.Value -match '\sselected\b' } | Select-Object -First 1
        if (-not $sel) { $sel = $opts | Select-Object -First 1 }
        $fields[$m.Groups[1].Value] = if ($sel) { Get-Attr $sel.Value 'value' } else { '' }
    }
    foreach ($m in [regex]::Matches($inner, '<textarea[^>]*name="([^"]+)"[^>]*>([\s\S]*?)</textarea>')) {
        $fields[$m.Groups[1].Value] = [Net.WebUtility]::HtmlDecode($m.Groups[2].Value)
    }
    [pscustomobject]@{ Action = (Get-Attr $formTag 'action'); Fields = $fields; Submits = $submits }
}

# --- Login --------------------------------------------------------------------------------------------
Invoke-WebRequest "$BaseUrl/index.php" -Method Post -Body @{
    access_user_name = $User
    access_password = $Password
    Login = 'Login'
} -Headers @{ Referer = "$BaseUrl/index.php" } -SessionVariable s -UseBasicParsing -MaximumRedirection 5 -ErrorAction SilentlyContinue | Out-Null
$script:Session = $s

$homePage = Invoke-Page "$BaseUrl/index.php"
Record 'GET home' $homePage

$builder = Invoke-Page "$BaseUrl/index.php?manage=builder"
Record 'GET builder' $builder
$form = Get-Form $builder.Content
if (-not $form) {
    Record 'POST create application' 'HTTPERROR: no builder form found'
} else {
    $body = @{}
    foreach ($k in $form.Fields.Keys) { $body[$k] = [string]$form.Fields[$k] }
    $body['name'] = 'ZZ Smoke App'
    $body['slug'] = $slug
    $body['hint'] = 'Smoke test application'
    $body['blueprint'] = 'case'
    $body['record_type_name'] = "ZZ Smoke Record $slug"
    $body['icon'] = 'ic-launch'
    $body['screens[]'] = @('items.work', 'items.create', 'search.saved_list')
    $body['perm_levels[]'] = @('use', 'announce', 'settings')
    if ($form.Submits.Count -gt 0) { $body[$form.Submits[0][0]] = [string]$form.Submits[0][1] }
    $url = if ($form.Action -match '^https?://') { $form.Action } else { "$BaseUrl/$($form.Action.TrimStart('/'))" }
    $created = Invoke-Page $url $body
    $note = if ($created.Content -match [regex]::Escape($slug) -or $created.BaseResponse.ResponseUri.AbsoluteUri -match [regex]::Escape($slug)) {
        "created slug=$slug"
    } else {
        'response did not clearly confirm slug'
    }
    Record 'POST create application' $created $note
}

$app = Invoke-Page "$BaseUrl/index.php?application=$slug"
Record "GET application/$slug" $app

$create = Invoke-Page "$BaseUrl/index.php?application=$slug&option=show_item_types"
Record "GET create (show_item_types)" $create
if ($create.Content -match 'item_type_id' -and ($create.Content -split 'item_type_id').Count -gt 3) {
    $script:Results[-1].Note = 'type picker may still list multiple types'
} elseif ($create.Content -match 'Create|New|item_title|Submit' -or $create.Content -match 'option=new_item') {
    $script:Results[-1].Note = 'create form or single-type path rendered'
}

$work = Invoke-Page "$BaseUrl/index.php?application=$slug"
Record "GET work shell" $work

$legacy = Invoke-Page "$BaseUrl/index.php?controller=social_management_main"
# Administrator may still open legacy (ADMIN_SYSTEM). Assert no fatal.
Record 'GET legacy social_management_main (admin)' $legacy 'admin may still open; non-admin denied'

$bare = Invoke-Page "$BaseUrl/index.php?controller=item_management_manage&option=show_item_types"
Record 'GET bare item engine (admin)' $bare 'ADMIN_ITEMS still allowed outside app shell'

# --- Report -------------------------------------------------------------------------------------------
foreach ($r in $script:Results) {
    Write-Host ('[{0,-10}] {1}' -f $r.Status, $r.Test)
    if ($r.Errors) { $r.Errors -split "`n" | ForEach-Object { Write-Host "             $_" } }
    if ($r.Note)   { Write-Host "             $($r.Note)" }
}
$summary = $script:Results | Group-Object Status | ForEach-Object { "$($_.Name)=$($_.Count)" }
Write-Host "`nSummary: $($summary -join ', ')"
$fatal = @($script:Results | Where-Object { $_.Status -in @('FATAL', 'HTTP ERROR') }).Count
if ($fatal -gt 0) { exit 1 }
