# 🔧 Correction Problème SSL Gmail

## Problèmes identifiés

1. **Erreur dans MAILER_DSN** : `fersimouadh25@gmail.com@gmail.com` (double @gmail.com)
2. **Erreur SSL** : "certificate verify failed"

## Solutions

### Solution 1 : Corriger MAILER_DSN (OBLIGATOIRE)

Dans `.env.local`, corrigez la ligne `MAILER_DSN` :

**❌ Incorrect :**
```env
MAILER_DSN=smtp://fersimouadh25@gmail.com@gmail.com:iektutlompxmyusn@smtp.gmail.com:587
```

**✅ Correct :**
```env
MAILER_DSN=smtp://fersimouadh25@gmail.com:iektutlompxmyusn@smtp.gmail.com:587
```

### Solution 2 : Résoudre l'erreur SSL

#### Option A : Utiliser le port 465 avec SSL (Recommandé)

Changez le port de 587 à 465 dans `.env.local` :

```env
MAILER_DSN=smtp://fersimouadh25@gmail.com:iektutlompxmyusn@smtp.gmail.com:465
```

#### Option B : Désactiver la vérification SSL (Développement uniquement)

Créez/modifiez `config/packages/dev/mailer.yaml` :

```yaml
framework:
    mailer:
        dsn: '%env(MAILER_DSN)%'
        # Désactiver la vérification SSL pour le développement
        # ⚠️ NE PAS utiliser en production !
```

Et ajoutez dans votre code PHP (temporairement) ou utilisez une variable d'environnement.

**Meilleure solution :** Utilisez le port 465 qui utilise SSL directement au lieu de STARTTLS.

## Étapes

1. **Corrigez `.env.local`** :
   ```env
   MAILER_DSN=smtp://fersimouadh25@gmail.com:iektutlompxmyusn@smtp.gmail.com:465
   ```

2. **Videz le cache** :
   ```bash
   php bin/console cache:clear
   ```

3. **Testez à nouveau**
