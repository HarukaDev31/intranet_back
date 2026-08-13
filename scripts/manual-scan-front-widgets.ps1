# Regenera config/manual_usuario_page_widgets.php desde el código Vue del front.
# Uso (desde la raíz del back, PowerShell):
#   .\scripts\manual-scan-front-widgets.ps1
#   .\scripts\manual-scan-front-widgets.ps1 -Only pages/curso
#   .\scripts\manual-scan-front-widgets.ps1 -Front "C:\path\probusiness_intranetv3"
#   .\scripts\manual-scan-front-widgets.ps1 -DryRun
param(
    [string]$Front = "",
    [string]$Only = "",
    [switch]$DryRun,
    [switch]$Backup
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

if (-not $Front) {
    $Front = $env:FRONT_PATH
}
if (-not $Front) {
    $Front = $env:MANUAL_USUARIO_FRONT_PATH
}
if (-not $Front) {
    $sibling = Join-Path (Split-Path $Root -Parent) "probusiness_intranetv3"
    $nested = Join-Path $Root "probusiness_intranetv3"
    if (Test-Path (Join-Path $sibling "pages")) { $Front = $sibling }
    elseif (Test-Path (Join-Path $nested "pages")) { $Front = $nested }
}

if (-not $Front -or -not (Test-Path (Join-Path $Front "pages"))) {
    Write-Error "No se encontró el front Vue (pages/). Usa -Front o MANUAL_USUARIO_FRONT_PATH."
}

$argsList = @("artisan", "manual:scan-front-widgets", "--front=$Front")
if (-not $DryRun) {
    $argsList += "--write"
    if (-not $Backup) { $argsList += "--no-backup" }
}
if ($Only) { $argsList += "--only=$Only" }

Write-Host "Front: $Front"
& php @argsList
& php artisan config:clear
Write-Host "Listo. Commitéa config/manual_usuario_page_widgets.php si cambió."
