# =====================================================================
#  Mise a jour GitHub — a relancer apres chaque modif du code par Claude
# =====================================================================
#
#  USAGE (PowerShell, depuis ce dossier) :
#     .\update-github.ps1
#  ou avec un message personnalise :
#     .\update-github.ps1 -Message "Ajout page statistiques"
#
#  Le depot doit deja avoir ete cree via push-to-github.ps1.
# =====================================================================

param(
    [string]$Message = ""
)

$ErrorActionPreference = "Stop"
Set-Location -Path $PSScriptRoot

if (-not (Test-Path ".git")) {
    Write-Host "Aucun depot Git ici. Lance d'abord push-to-github.ps1." -ForegroundColor Red
    exit 1
}

if ([string]::IsNullOrWhiteSpace($Message)) {
    $Message = "Mise a jour du " + (Get-Date -Format "dd/MM/yyyy HH:mm")
}

Write-Host "Ajout des changements..." -ForegroundColor Cyan
git add -A

# Ne committe que s'il y a vraiment des changements
$changes = git status --porcelain
if ([string]::IsNullOrWhiteSpace($changes)) {
    Write-Host "Aucun changement a envoyer." -ForegroundColor Yellow
    exit 0
}

git commit -m $Message | Out-Null
Write-Host "Envoi vers GitHub..." -ForegroundColor Cyan
git push

Write-Host ""
Write-Host "A jour ! ($Message)" -ForegroundColor Green
