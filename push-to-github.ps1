# =====================================================================
#  Pousser « Messagerie IA Conciergerie » vers GitHub — script unique
# =====================================================================
#
#  AVANT DE LANCER :
#   1. Sur github.com, cree un depot VIDE (sans README, sans .gitignore).
#      Exemple de nom : messagerie-ia-conciergerie
#   2. Copie son URL HTTPS, ex : https://github.com/TONCOMPTE/messagerie-ia-conciergerie.git
#
#  POUR LANCER (dans PowerShell, depuis ce dossier) :
#   .\push-to-github.ps1 -RepoUrl "https://github.com/TONCOMPTE/messagerie-ia-conciergerie.git"
#
#  Git demandera ton identifiant GitHub + un token (mot de passe). Si tu n'as
#  pas de token : github.com > Settings > Developer settings > Personal access
#  tokens > Fine-grained > genere un token avec acces "Contents: Read and write"
#  sur le depot, puis colle-le quand le mot de passe est demande.
# =====================================================================

param(
    [Parameter(Mandatory = $true)]
    [string]$RepoUrl
)

$ErrorActionPreference = "Stop"
Set-Location -Path $PSScriptRoot

Write-Host "1/5  Nettoyage d'un eventuel depot Git casse..." -ForegroundColor Cyan
if (Test-Path ".git") { Remove-Item -Recurse -Force ".git" }

Write-Host "2/5  Initialisation du depot..." -ForegroundColor Cyan
git init | Out-Null
git branch -M main

# Identite locale (ne touche pas a ta config globale)
git config user.email "conciergerieliberty@gmail.com"
git config user.name  "Conciergerie Liberty"

Write-Host "3/5  Ajout des fichiers + commit..." -ForegroundColor Cyan
git add -A
git commit -m "V1 - Messagerie IA Conciergerie (Lodgify + IA, mode hybride)" | Out-Null

Write-Host "4/5  Liaison au depot GitHub..." -ForegroundColor Cyan
git remote remove origin 2>$null
git remote add origin $RepoUrl

Write-Host "5/5  Envoi vers GitHub (push)..." -ForegroundColor Cyan
git push -u origin main

Write-Host ""
Write-Host "Termine ! Code pousse sur $RepoUrl" -ForegroundColor Green
Write-Host "Rappel : config/config.php est ignore par Git, tes secrets ne partent pas." -ForegroundColor Yellow
