# 🤖 Recommandation d'Images avec Gemini AI

Ce projet intègre Google Gemini AI pour recommander automatiquement des images pertinentes basées sur le titre et la description des événements.

## 📋 Prérequis

- Un compte Google
- Une clé API Google Gemini (gratuite)

## 🔑 Étape 1 : Obtenir votre clé API Gemini

### Instructions détaillées :

1. **Allez sur Google AI Studio**
   - Visitez : https://aistudio.google.com/apikey
   - Connectez-vous avec votre compte Google

2. **Créez une nouvelle clé API**
   - Cliquez sur "Create API Key" ou "Créer une clé API"
   - Sélectionnez un projet Google Cloud (ou créez-en un nouveau)
   - Votre clé API sera générée automatiquement

3. **Copiez votre clé API**
   - La clé ressemble à : `AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX`
   - ⚠️ **Important** : Gardez cette clé secrète et ne la partagez jamais publiquement

## ⚙️ Étape 2 : Configurer la clé API dans le projet

### Option 1 : Fichier `.env.local` (Recommandé)

1. Ouvrez le fichier `.env.local` à la racine du projet
   - Si le fichier n'existe pas, créez-le en copiant `.env`

2. Ajoutez vos clés API :
   ```env
   GEMINI_API_KEY=AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
   PEXELS_API_KEY=votre_cle_pexels_ici
   ```
   - **GEMINI_API_KEY** (obligatoire) : Pour générer les mots-clés intelligents
   - **PEXELS_API_KEY** (optionnel mais recommandé) : Pour des images très pertinentes. Obtenez-la gratuitement sur https://www.pexels.com/api/

3. Sauvegardez le fichier

### Option 2 : Variables d'environnement système

Vous pouvez aussi définir la variable d'environnement directement dans votre système.

## ✅ Étape 3 : Vérifier la configuration

1. Videz le cache Symfony :
   ```bash
   php bin/console cache:clear
   ```

2. Testez la fonctionnalité :
   - Allez sur `/admin/evenement/new`
   - Remplissez le titre et la description d'un événement
   - Cliquez sur "Recommander une image avec l'IA"
   - Une image pertinente devrait apparaître

## 🎯 Comment ça fonctionne ?

1. **Génération de mots-clés** : Gemini AI analyse le titre et la description de l'événement et génère des mots-clés en anglais pertinents
2. **Recherche d'image** : Les mots-clés sont utilisés pour rechercher une image sur Unsplash
3. **Affichage** : L'image recommandée est affichée avec la possibilité de l'utiliser directement

## 🔒 Sécurité

- ⚠️ **Ne commitez JAMAIS** votre fichier `.env.local` dans Git
- Le fichier `.env.local` est déjà dans `.gitignore` par défaut
- Ne partagez jamais votre clé API publiquement

## 📝 Notes

- La clé API Gemini est gratuite avec des limites généreuses
- Si la clé API n'est pas configurée, le système utilisera un fallback avec des mots-clés basiques
- Les images sont récupérées depuis Unsplash (gratuit, 50 requêtes/heure sans clé)

## 🆘 Dépannage

### Erreur : "Environment variable not found: GEMINI_API_KEY"
- Vérifiez que vous avez bien ajouté la clé dans `.env.local`
- Videz le cache : `php bin/console cache:clear`

### Erreur : "Gemini API returned status code: 404"
- Vérifiez que votre clé API est valide
- Vérifiez que vous avez activé l'API Gemini dans Google Cloud Console

### Aucune image ne s'affiche
- Vérifiez les logs dans `var/log/dev.log`
- Assurez-vous que votre connexion internet fonctionne
- L'API Unsplash peut avoir des limites de taux
