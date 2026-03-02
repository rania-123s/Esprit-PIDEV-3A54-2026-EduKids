# 📧 Configuration Gmail SMTP pour Envoi Réel

## ⚠️ Important : Étapes à suivre

### 1. Activer l'authentification à deux facteurs (2FA)

1. Allez sur https://myaccount.google.com/security
2. Activez **"Validation en deux étapes"** si ce n'est pas déjà fait
3. Suivez les instructions pour configurer la 2FA

### 2. Créer un mot de passe d'application

1. Allez sur https://myaccount.google.com/apppasswords
2. Si vous ne voyez pas cette page, activez d'abord la 2FA
3. Sélectionnez **"Application"** : `Mail`
4. Sélectionnez **"Appareil"** : `Autre (nom personnalisé)`
5. Entrez un nom : `Gestion Evenements`
6. Cliquez sur **"Générer"**
7. **Copiez le mot de passe** (16 caractères, espacés en groupes de 4)
   - Exemple : `abcd efgh ijkl mnop`
   - ⚠️ **Important** : Vous ne pourrez plus voir ce mot de passe après ! Copiez-le maintenant.

### 3. Mettre à jour `.env.local`

Ouvrez votre fichier `.env.local` et remplacez la ligne `MAILER_DSN` par :

```env
MAILER_DSN=smtp://votre-email@gmail.com:votre-mot-de-passe-app@smtp.gmail.com:587
```

**Exemple concret :**
Si votre email est `monemail@gmail.com` et votre mot de passe d'application est `abcd efgh ijkl mnop`, la ligne sera :

```env
MAILER_DSN=smtp://monemail@gmail.com:abcdefghijklmnop@smtp.gmail.com:587
```

**Note :** Supprimez les espaces du mot de passe d'application dans la configuration !

### 4. Mettre à jour l'email d'expéditeur

Dans `config/services.yaml`, modifiez :

```yaml
App\Service\EventNotificationService:
    arguments:
        $fromEmail: 'votre-email@gmail.com'  # Votre email Gmail
        $fromName: 'Gestion Événements'
```

### 5. Vider le cache

```bash
php bin/console cache:clear
```

## 🧪 Tester

1. Créez un nouvel événement via `/admin/evenement/new`
2. Vérifiez les boîtes de réception Gmail des utilisateurs
3. Vérifiez aussi le dossier **"Spam"** au cas où

## ⚠️ Limitations Gmail

- **500 emails/jour** en version gratuite
- **100 destinataires par email** maximum
- Les emails peuvent être marqués comme spam si vous en envoyez trop rapidement

## 🔒 Sécurité

- ✅ Utilisez toujours un **mot de passe d'application**, jamais votre mot de passe Gmail normal
- ✅ Ne commitez JAMAIS `.env.local` dans Git (il est déjà dans `.gitignore`)
- ✅ Le mot de passe d'application est spécifique à cette application

## 🐛 Dépannage

### Erreur "Username and Password not accepted"
- Vérifiez que vous utilisez un **mot de passe d'application**, pas votre mot de passe Gmail
- Vérifiez que la 2FA est activée

### Erreur "Connection timeout"
- Vérifiez votre connexion Internet
- Vérifiez que le port 587 n'est pas bloqué par votre firewall

### Emails dans le spam
- C'est normal pour les premiers envois
- Les utilisateurs doivent marquer comme "Non spam"
- Après plusieurs envois légitimes, Gmail apprendra que vos emails sont valides
