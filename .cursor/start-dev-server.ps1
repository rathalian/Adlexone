# Start Inlay's PHP built-in server on port 8000 (idempotent).
$ErrorActionPreference = 'Stop'
Set-Location (Join-Path $PSScriptRoot '..')

function Resolve-PhpExe {
    $cmd = Get-Command php -ErrorAction SilentlyContinue
    if ($cmd -and $cmd.Source -and (Test-Path -LiteralPath $cmd.Source)) {
        return $cmd.Source
    }

    $candidates = @()

    $wingetRoot = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages'
    if (Test-Path -LiteralPath $wingetRoot) {
        $candidates += Get-ChildItem -LiteralPath $wingetRoot -Directory -Filter 'PHP.PHP.*' -ErrorAction SilentlyContinue |
            ForEach-Object { Join-Path $_.FullName 'php.exe' }
    }

    foreach ($base in @(
            (Join-Path ${env:ProgramFiles} 'PHP'),
            (Join-Path ${env:ProgramFiles} 'php'),
            (Join-Path ${env:ProgramFiles(x86)} 'PHP'),
            (Join-Path ${env:ProgramFiles(x86)} 'php')
        )) {
        if (-not (Test-Path -LiteralPath $base)) { continue }
        $candidates += Join-Path $base 'php.exe'
        $candidates += Get-ChildItem -LiteralPath $base -Directory -ErrorAction SilentlyContinue |
            ForEach-Object { Join-Path $_.FullName 'php.exe' }
    }

    foreach ($path in ($candidates | Select-Object -Unique)) {
        if ($path -and (Test-Path -LiteralPath $path)) {
            return $path
        }
    }

    return $null
}

$port = 8000
$existing = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "Inlay already listening on port $port"
    exit 0
}

$php = Resolve-PhpExe
if (-not $php) {
    Write-Error "php.exe not found. Install PHP 8.1+ (pdo_sqlite, dom, libxml, xml), then reopen this folder."
    exit 1
}

Write-Host "Using PHP: $php"
Write-Host "Starting Inlay at http://127.0.0.1:$port/"
& $php -S 0.0.0.0:$port
