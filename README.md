# EduKids

## Description

EduKids est une plateforme éducative interactive conçue pour les enfants, développée avec Symfony 7.4. Cette application offre une gestion complète des événements éducatifs, un système de chat, des quiz générés par IA, et bien plus. Elle intègre des technologies modernes comme Doctrine ORM, EasyAdmin, et des APIs IA (Gemini, OpenAI, etc.) pour enrichir l'expérience d'apprentissage.

## Fonctionnalités principales

- Gestion des événements éducatifs
- Système de chat pour les parents et éducateurs
- Génération de quiz et de cours via IA
- Interface d'administration avec EasyAdmin
- Notifications par email et Mercure pour les mises à jour en temps réel
- Support pour les paiements et e-commerce

## Prérequis

- PHP 8.2 ou supérieur
- MySQL/MariaDB
- Composer
- Node.js et Yarn (pour les assets)
- Docker (optionnel, pour la base de données)

## Installation

### 1. Cloner le projet

```bash
git clone https://github.com/votre-utilisateur/edukids.git
cd edukids
```

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer l'environnement

Copiez le fichier `.env` et ajustez les variables :

```bash
cp .env.example .env
```

Variables importantes :
- `DATABASE_URL` : URL de la base de données
- `APP_SECRET` : Clé secrète de l'application
- Clés API pour les services IA (GEMINI_API_KEY, OPENAI_API_KEY, etc.)

### 4. Configurer la base de données

#### Avec Docker (recommandé)

Lancez les conteneurs Docker :

```bash
docker-compose up -d
```

Cela démarre MySQL sur le port 5599 et phpMyAdmin sur le port 8080.

#### Sans Docker

Créez la base de données manuellement :

```sql
CREATE DATABASE IF NOT EXISTS EduKids CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Puis mettez à jour le schéma :

```bash
php bin/console doctrine:schema:update --force
```

Ou utilisez les migrations :

```bash
php bin/console doctrine:migrations:migrate
```

### 5. Installer les assets

```bash
php bin/console asset-map:compile
```

### 6. Charger les données de test (optionnel)

Pour le chat :

```bash
php bin/console app:chat:load-fixtures
```

## Utilisation

### Lancer le serveur de développement

```bash
symfony server:start
```

Ou avec PHP :

```bash
php bin/console cache:clear
php -S localhost:8000 -t public
```

Accédez à l'application sur `http://localhost:8000`.

### Accès à phpMyAdmin (avec Docker)

`http://localhost:8080`

## Déploiement

Voir le fichier [DEPLOYMENT_GIT.md](DEPLOYMENT_GIT.md) pour les instructions de déploiement via Git.

## Tests

Lancez les tests avec PHPUnit :

```bash
php bin/phpunit
```

## Contribution

1. Forkez le projet
2. Créez une branche pour votre fonctionnalité (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commitez vos changements (`git commit -am 'Ajoute une nouvelle fonctionnalité'`)
4. Poussez vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Ouvrez une Pull Request

## Licence

Ce projet est sous licence propriétaire.

## Auteurs

- [Votre nom] - Développeur principal

## Remerciements

- Symfony Framework
- Doctrine ORM
- EasyAdmin Bundle
- APIs IA (Google Gemini, OpenAI, etc.)
