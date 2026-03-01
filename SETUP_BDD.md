# Mise en place de la base de données – ÉduKids

À faire **après** avoir cloné le projet et lancé `composer install`.

## 1. Créer la base de données

Dans MySQL/MariaDB, crée la base **EduKids** (ou adapte `DATABASE_URL` dans `.env` si tu utilises un autre nom) :

```sql
CREATE DATABASE IF NOT EXISTS EduKids CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Ou en une commande (PowerShell) :

```powershell
php bin/console doctrine:database:create --if-not-exists
```

## 2. Créer toutes les tables

**Option A – Mise à jour du schéma (recommandé)**  
Crée ou met à jour les tables pour qu’elles correspondent aux entités :

```powershell
php bin/console doctrine:schema:update --force
```

**Option B – Migrations**  
Si tu préfères utiliser les migrations :

```powershell
php bin/console doctrine:migrations:migrate --no-interaction
```

En cas d’erreur du type « table already exists », utilise l’option A.

## 3. (Optionnel) Données de test pour le chat

Pour avoir des utilisateurs et des conversations de test :

```powershell
php bin/console app:chat:load-fixtures
```

Comptes créés :

| Email                 | Mot de passe |
|-----------------------|--------------|
| chat_parent1@test.com | test123      |
| chat_parent2@test.com | test123      |
| chat_admin@test.com    | test123      |

## Résumé en 3 commandes

```powershell
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:update --force
php bin/console app:chat:load-fixtures
```

Tu peux copier-coller ce bloc dans un terminal à la racine du projet.
