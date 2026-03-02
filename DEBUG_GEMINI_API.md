# 🔍 Guide de Débogage - API Gemini pour Génération d'Activités

## ❌ Problème : Erreur 404 lors de la génération d'activités

Si vous voyez l'erreur "Impossible de générer des activités", suivez ces étapes :

### 1. Vérifier la clé API Gemini

**Vérifiez que votre clé API est bien configurée :**

1. Ouvrez le fichier `.env.local` à la racine du projet
2. Vérifiez que cette ligne existe :
   ```env
   GEMINI_API_KEY=AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
   ```
3. Remplacez `AIzaSyXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX` par votre vraie clé API

### 2. Obtenir/Créer une clé API Gemini

Si vous n'avez pas de clé API :

1. Allez sur : https://aistudio.google.com/apikey
2. Connectez-vous avec votre compte Google
3. Cliquez sur "Create API Key" ou "Créer une clé API"
4. Sélectionnez un projet Google Cloud (ou créez-en un nouveau)
5. Copiez la clé générée
6. Ajoutez-la dans `.env.local`

### 3. Activer l'API Generative Language

**Important :** L'API doit être activée dans Google Cloud Console :

**Méthode 1 : Via le menu de navigation**

1. Vous êtes déjà sur https://console.cloud.google.com/ (projet : davincis-data)
2. Dans le menu de gauche (☰), cliquez sur **"APIs & Services"** (APIs et services)
3. Cliquez sur **"Library"** (Bibliothèque) dans le sous-menu
4. Dans la barre de recherche en haut, tapez : **"Generative Language API"**
5. Cliquez sur le résultat **"Generative Language API"**
6. Cliquez sur le bouton bleu **"ENABLE"** ou **"ACTIVER"**
7. Attendez quelques secondes que l'activation se termine

**Méthode 2 : Lien direct**

1. Cliquez directement sur ce lien : https://console.cloud.google.com/apis/library/generativelanguage.googleapis.com?project=davincis-data
2. Cliquez sur le bouton bleu **"ENABLE"** ou **"ACTIVER"**

**Vérification :**
- Après activation, vous devriez voir un message de confirmation
- Le bouton devrait changer en "MANAGE" (Gérer)
- L'API devrait apparaître dans la liste des APIs activées

### 4. Vérifier les logs

**Pour voir l'erreur exacte :**

1. Ouvrez le fichier `var/log/dev.log`
2. Recherchez les lignes contenant "Gemini API" ou "ActivityRecommendationService"
3. Vous verrez l'erreur exacte retournée par l'API

### 5. Tester la clé API

**Test rapide avec curl (optionnel) :**

```bash
curl "https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=VOTRE_CLE_API" \
  -H 'Content-Type: application/json' \
  -d '{"contents":[{"parts":[{"text":"Test"}]}]}'
```

Si vous obtenez une erreur, la clé API n'est pas valide ou l'API n'est pas activée.

### 6. Vider le cache

Après avoir modifié `.env.local` :

```bash
php bin/console cache:clear
```

### 7. Vérifier la connexion internet

Assurez-vous que votre serveur a accès à internet pour contacter l'API Gemini.

## ✅ Solutions courantes

| Erreur | Solution |
|--------|----------|
| 404 Not Found | Vérifiez que l'API Generative Language est activée dans Google Cloud Console |
| 403 Forbidden | Vérifiez que votre clé API est valide et n'a pas expiré |
| 401 Unauthorized | Vérifiez que la clé API est correctement copiée dans `.env.local` |
| Timeout | Vérifiez votre connexion internet |

## 📝 Notes

- La clé API Gemini est gratuite avec des limites généreuses
- Si vous avez plusieurs projets Google Cloud, assurez-vous d'utiliser la bonne clé API
- La clé API peut prendre quelques minutes à être activée après création
