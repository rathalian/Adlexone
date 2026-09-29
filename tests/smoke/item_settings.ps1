# Smoke test for screens/administration_item_settings.php.
# Loads every page option, then submits each form the way a browser would (parsing inputs, selected
# options and checked boxes from the rendered HTML). Submits WRITE to the database: back it up first.
#
#   powershell -ExecutionPolicy Bypass -File tests\smoke\item_settings.ps1 [-BaseUrl http://localhost:8000]

param(
    [string]$BaseUrl = 'http://localhost:8000',
    [string]$User = 'Administrator',
    [string]$Password = 'password'
)

$ErrorActionPreference = 'Stop'
$script:Results = @()
$Ctl = "$BaseUrl/index.php?controller=administration_item_settings"

function Invoke-Page([string]$Url, [hashtable]$Body = $null) {
    $args = @{ Uri = $Url; WebSession = $script:Session; UseBasicParsing = $true }
    if ($Body) { $args.Method = 'Post'; $args.Body = $Body }
    try { return (Invoke-WebRequest @args).Content } catch { return "HTTPERROR: $($_.Exception.Message)" }
}

function Get-PhpErrors([string]$Html) {
    [regex]::Matches($Html, '<b>(Fatal error|Warning|Notice|Deprecated|Parse error)</b>:\s*([\s\S]*?)\s+in\s+<b>[^<]*?([^\\/<]+\.php)</b>\s+on line\s+<b>(\d+)</b>') |
        ForEach-Object { '{0}: {1} ({2}:{3})' -f $_.Groups[1].Value, ($_.Groups[2].Value -replace '\s+', ' ').Trim(), $_.Groups[3].Value, $_.Groups[4].Value } |
        Select-Object -Unique
}

function Get-Titles([string]$Html) {
    ([regex]::Matches($Html, 'class="card-title">([^<]+)<') | ForEach-Object { [Net.WebUtility]::HtmlDecode($_.Groups[1].Value) } | Where-Object { $_ -ne '-->' }) -join ' / '
}

function Get-Attr([string]$Tag, [string]$Name) {
    $m = [regex]::Match($Tag, "\b$Name=""([^""]*)""")
    if ($m.Success) { return [Net.WebUtility]::HtmlDecode($m.Groups[1].Value) }
    return $null
}

# Parse the first form inside <main> into action + fields, as a browser would submit it.
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

function Record([string]$Name, [string]$Html, [string]$Note = '') {
    $errors = @(Get-PhpErrors $Html)
    $status = if ($Html -like 'HTTPERROR*') { 'HTTP ERROR' } elseif ($errors | Where-Object { $_ -like 'Fatal*' -or $_ -like 'Parse*' }) { 'FATAL' } elseif ($errors) { 'WARN' } else { 'OK' }
    if ($Html -like 'HTTPERROR*') { $errors = @($Html) }
    $script:Results += [pscustomobject]@{ Test = $Name; Status = $status; Titles = (Get-Titles $Html); Errors = ($errors -join "`n"); Note = $Note }
}

# Load a page, then submit its form with optional overrides and a chosen submit button.
function Submit-Form([string]$Name, [string]$PageHtml, [hashtable]$Overrides = @{}, [string]$Button = '', [string[]]$Remove = @()) {
    $form = Get-Form $PageHtml
    if (-not $form) { Record $Name 'HTTPERROR: no form found on source page (page did not render)'; return $null }
    $body = @{}
    foreach ($k in $form.Fields.Keys) { $body[$k] = [string]$form.Fields[$k] }
    foreach ($k in $Remove) { $body.Remove($k) }
    foreach ($k in $Overrides.Keys) { $body[$k] = [string]$Overrides[$k] }
    $btn = if ($Button) { $form.Submits | Where-Object { $_[0] -eq $Button } | Select-Object -First 1 } else { $form.Submits | Select-Object -First 1 }
    if ($btn) { $body[$btn[0]] = [string]$btn[1] }
    $url = if ($form.Action -match '^https?://') { $form.Action } else { "$BaseUrl/$($form.Action.TrimStart('/'))" }
    $html = Invoke-Page $url $body
    Record $Name $html
    return $html
}

function Invoke-Sql([string]$Sql) {
    $php = (Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP.8.1*' | Select-Object -First 1 -ExpandProperty FullName) + '\php.exe'
    $code = "<?php `$p = new PDO('sqlite:storage/database/adlexone.sqlite'); foreach (`$p->query(base64_decode('" + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($Sql)) + "'), PDO::FETCH_ASSOC) as `$r) echo json_encode(`$r), `"\n`";"
    $code | & $php
}

# --- Log in -------------------------------------------------------------------------------------------
Invoke-WebRequest "$BaseUrl/index.php" -Method Post -Body @{ access_user_name = $User; access_password = $Password; Login = 'Login' } `
    -Headers @{ Referer = "$BaseUrl/index.php" } -SessionVariable s -UseBasicParsing -MaximumRedirection 0 -ErrorAction SilentlyContinue | Out-Null
$script:Session = $s

# --- Page options -------------------------------------------------------------------------------------
Record 'GET  (default option)'                     (Invoke-Page $Ctl)
$newField   = Invoke-Page "$Ctl&option=new_custom_field";              Record 'GET  new_custom_field' $newField
$editField  = Invoke-Page "$Ctl&option=modify_custom_field&custom_field_id=2"; Record 'GET  modify_custom_field (2 Priority)' $editField
$menuVals   = Invoke-Page "$Ctl&option=modify_menu_values&custom_field_id=2";  Record 'GET  modify_menu_values (2)' $menuVals
$menuFilt   = Invoke-Page "$Ctl&option=modify_menu_value_filters&custom_field_id=7"; Record 'GET  modify_menu_value_filters (7 Ticket Group)' $menuFilt
$newType    = Invoke-Page "$Ctl&option=new_item_type";                 Record 'GET  new_item_type' $newType
$editType   = Invoke-Page "$Ctl&option=modify_item_type&item_type_id=1"; Record 'GET  modify_item_type (1)' $editType
$search     = Invoke-Page "$Ctl&option=manage_fields_types";           Record 'GET  manage_fields_types' $search
$newMlm     = Invoke-Page "$Ctl&option=new_multilevel_menu_relationship"; Record 'GET  new_multilevel_menu_relationship' $newMlm

# --- Submits ------------------------------------------------------------------------------------------
Invoke-Page "$BaseUrl/index.php?controller=administration_item_settings&option=field_type_search" @{ type = 'custom_field_name'; operator = 'LIKE'; criteria = ''; submit_button = 'Submit' } |
    ForEach-Object { Record 'POST field_type_search (custom fields)' $_ }
Invoke-Page "$BaseUrl/index.php?controller=administration_item_settings&option=field_type_search" @{ type = 'item_type_name'; operator = 'LIKE'; criteria = ''; submit_button = 'Submit' } |
    ForEach-Object { Record 'POST field_type_search (item types)' $_ }

Submit-Form 'POST add_custom_field (new textBox)' $newField @{ custom_field_name = 'ZZ Smoke Text'; field_type = 'textBox' } | Out-Null
$created = Invoke-Sql "SELECT custom_field_id, field_type FROM custom_fields WHERE custom_field_name = 'ZZ Smoke Text'"
$script:Results[-1].Note = "db: $created"
Submit-Form 'POST add_custom_field (duplicate name)' $newField @{ custom_field_name = 'ZZ Smoke Text'; field_type = 'textBox' } | Out-Null
Submit-Form 'POST add_custom_field (multiLevelMenu)' $newField @{ custom_field_name = 'ZZ Smoke MLM'; field_type = 'multiLevelMenu'; menu_levels = '2' } | Out-Null
$mlmId = ((Invoke-Sql "SELECT custom_field_id FROM custom_fields WHERE custom_field_name = 'ZZ Smoke MLM'") | ConvertFrom-Json).custom_field_id

Submit-Form 'POST update_custom_field (2, unchanged)' $editField | Out-Null
$script:Results[-1].Note = 'db: ' + (Invoke-Sql "SELECT custom_field_name, field_type, default_value, enabled FROM custom_fields WHERE custom_field_id = 2")

Submit-Form 'POST add_update_menu_value (add_new)' $menuVals @{ menu_value = 'ZZ Smoke Value' } 'add_new' | Out-Null
$script:Results[-1].Note = 'db: ' + (Invoke-Sql "SELECT menu_value_id, menu_value FROM custom_field_menu_values WHERE menu_value = 'ZZ Smoke Value'")
Submit-Form 'POST add_update_menu_value (update_existing)' $menuVals @{} 'update_existing' | Out-Null
Invoke-Page "$BaseUrl/index.php?controller=administration_item_settings&option=add_update_menu_value" @{ custom_field_id = '2'; menu_value = 'ZZ Smoke Value 2'; add_new = 'Add' } |
    ForEach-Object { Record 'POST add_update_menu_value (add_new, direct)' $_ }
$mvId = ((Invoke-Sql "SELECT menu_value_id FROM custom_field_menu_values WHERE menu_value LIKE 'ZZ Smoke Value%' LIMIT 1") | ConvertFrom-Json).menu_value_id
Record "GET  delete_menu_value ($mvId)" (Invoke-Page "$Ctl&option=delete_menu_value&menu_value_id=$mvId&custom_field_id=2")

Submit-Form 'POST update_menu_value_filter (7)' $menuFilt | Out-Null
Invoke-Page "$BaseUrl/index.php?controller=administration_item_settings&option=update_menu_value_filter" @{ custom_field_id = '7'; '13' = 'Corrupted Installation,Require Installation,Update Required,Vendor Issue'; submit_button = 'Update' } |
    ForEach-Object { Record 'POST update_menu_value_filter (7, direct)' $_ }

Submit-Form 'POST add_item_type (new)' $newType @{ item_type_name = 'ZZ Smoke Type'; group_1 = '1'; custom_field_id_1 = '1'; custom_field_sort_1 = '1' } | Out-Null
$script:Results[-1].Note = 'db: ' + ((Invoke-Sql "SELECT item_type_id, item_type_name, group_security, enabled FROM item_types WHERE item_type_name = 'ZZ Smoke Type'") -join ' ')
Submit-Form 'POST add_item_type (duplicate name)' $newType @{ item_type_name = 'ZZ Smoke Type' } | Out-Null
Submit-Form 'POST update_item_type (1, unchanged)' $editType | Out-Null
$script:Results[-1].Note = 'db: ' + ((Invoke-Sql "SELECT item_type_name, group_security, enabled FROM item_types WHERE item_type_id = 1") -join ' ') + ' | fields: ' + ((Invoke-Sql "SELECT COUNT(*) n FROM item_type_custom_fields WHERE item_type_id = 1") -join '')

if ($mlmId) {
    $showMlm = Invoke-Page "$Ctl&option=show_multilevel_menu&multi_level_menu_id=$mlmId"; Record "GET  show_multilevel_menu ($mlmId)" $showMlm
    Submit-Form "POST update_multilevel_menu ($mlmId)" $showMlm @{ custom_field_id_2 = '2'; custom_field_sort_2 = '1'; custom_field_id_3 = '3'; custom_field_sort_3 = '2' } | Out-Null
    $script:Results[-1].Note = 'db: ' + (Invoke-Sql "SELECT menu_relationship FROM custom_fields WHERE custom_field_id = $mlmId")
    Submit-Form "POST add_multilevel_menu ($mlmId)" $newMlm @{ multi_level_menu_id = $mlmId; custom_field_id_2 = '2'; custom_field_sort_2 = '1' } | Out-Null
    $mlmItems = Invoke-Page "$Ctl&option=show_multilevel_menu_items&multi_level_menu_id=$mlmId"; Record "GET  show_multilevel_menu_items ($mlmId)" $mlmItems
    Invoke-Page "$BaseUrl/index.php?controller=administration_item_settings&option=update_multilevel_menu_items" @{ multi_level_menu_id = $mlmId; 'custom_field_id_2-1' = '2'; 'custom_field_id_3-1' = '9'; submit_button = 'Submit' } |
        ForEach-Object { Record "POST update_multilevel_menu_items ($mlmId, direct)" $_ }
} else {
    Record 'GET  show_multilevel_menu' 'HTTPERROR: skipped, multiLevelMenu test field was not created'
}

$fieldId = ((Invoke-Sql "SELECT custom_field_id FROM custom_fields WHERE custom_field_name = 'ZZ Smoke Text'") | ConvertFrom-Json).custom_field_id
Record "GET  delete_custom_field ($fieldId)" (Invoke-Page "$Ctl&option=delete_custom_field&custom_field_id=$fieldId")

# --- Report -------------------------------------------------------------------------------------------
foreach ($r in $script:Results) {
    Write-Host ('[{0,-10}] {1}' -f $r.Status, $r.Test)
    if ($r.Titles) { Write-Host "             titles: $($r.Titles)" }
    if ($r.Errors) { $r.Errors -split "`n" | ForEach-Object { Write-Host "             $_" } }
    if ($r.Note)   { Write-Host "             $($r.Note)" }
}
$summary = $script:Results | Group-Object Status | ForEach-Object { "$($_.Name)=$($_.Count)" }
Write-Host "`nSummary: $($summary -join ', ')"
