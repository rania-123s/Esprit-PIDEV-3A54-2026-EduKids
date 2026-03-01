# Script pour créer la base et toutes les tables (à lancer à la racine du projet)
# Usage: .\setup-bdd.ps1   ou   powershell -ExecutionPolicy Bypass -File setup-bdd.ps1

Set-Location $PSScriptRoot

Write-Host "1. Creation de la base de donnees..." -ForegroundColor Cyan
php bin/console doctrine:database:create --if-not-exists

Write-Host "2. Creation / mise a jour des tables..." -ForegroundColor Cyan
php bin/console doctrine:schema:update --force

Write-Host "3. (Optionnel) Donnees de test du chat..." -ForegroundColor Cyan
$load = Read-Host "Charger les fixtures du chat ? (o/n)"
if ($load -eq "o" -or $load -eq "O" -or $load -eq "y" -or $load -eq "Y") {
    php bin/console app:chat:load-fixtures
}

Write-Host "Termine. Tu peux lancer le serveur avec: symfony serve  ou  php -S 127.0.0.1:8000 -t public" -ForegroundColor Green
