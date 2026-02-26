# 🚀 Guide de Configuration - API Groq pour Génération d'Activités

Ce guide vous explique comment remplacer l'API Gemini par Groq pour la génération d'activités.

## 📋 Prérequis

- Un compte Groq (gratuit)
- Une clé API Groq

## 🔑 Étape 1 : Obtenir votre clé API Groq

### Instructions détaillées :

1. **Allez sur Groq Console**
   - Visitez : https://console.groq.com/
   - Connectez-vous ou créez un compte (gratuit)

2. **Créez une nouvelle clé API**
   - Allez dans la section "API Keys" ou "Clés API"
   - Cliquez sur "Create API Key" ou "Créer une clé API"
   - Donnez un nom à votre clé (ex: "Gestion Événements - Activités")
   - Votre clé API sera générée automatiquement

3. **Copiez votre clé API**
   - La clé ressemble à : `gsk_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX`
   - ⚠️ **Important** : Gardez cette clé secrète et ne la partagez jamais publiquement

## ⚙️ Étape 2 : Configurer la clé API dans le projet

### 1. Ajouter la clé API dans `.env.local`

1. Ouvrez le fichier `.env.local` à la racine du projet
   - Si le fichier n'existe pas, créez-le en copiant `.env`

2. Ajoutez votre clé API Groq :
   ```env
   GROQ_API_KEY=gsk_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
   ```

3. Sauvegardez le fichier

### 2. Vider le cache Symfony

```bash
php bin/console cache:clear
```

## ✅ Étape 3 : Vérifier la configuration

1. Testez la fonctionnalité :
   - Allez sur la page de création/édition d'un programme
   - Sélectionnez un événement
   - Cliquez sur "Générer avec l'IA"
   - Les activités devraient être générées avec Groq

## 🎯 Modèles disponibles sur Groq

Groq propose plusieurs modèles rapides :
- `llama-3.1-70b-versatile` (recommandé)
- `llama-3.1-8b-instant`
- `mixtral-8x7b-32768`
- `gemma-7b-it`

## 🔒 Sécurité

- ⚠️ **Ne commitez JAMAIS** votre fichier `.env.local` dans Git
- Le fichier `.env.local` est déjà dans `.gitignore` par défaut
- Ne partagez jamais votre clé API publiquement

## 📝 Notes

- La clé API Groq est gratuite avec des limites généreuses
- Groq est très rapide grâce à son infrastructure optimisée
- Si la clé API n'est pas configurée, le système utilisera un fallback avec des activités basiques

## 🆘 Dépannage

### Erreur : "Environment variable not found: GROQ_API_KEY"
- Vérifiez que vous avez bien ajouté la clé dans `.env.local`
- Videz le cache : `php bin/console cache:clear`

### Erreur : "Groq API returned status code: 401"
- Vérifiez que votre clé API est valide
- Vérifiez que vous avez bien copié la clé complète

### Erreur : "Groq API returned status code: 429"
- Vous avez atteint la limite de requêtes
- Attendez quelques minutes et réessayez
