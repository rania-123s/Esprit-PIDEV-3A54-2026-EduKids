# 📧 Configuration SMTP pour les Emails

## ⚡ Configuration Rapide

### 1. Créer le fichier `.env.local`

Créez un fichier `.env.local` à la racine du projet et ajoutez :

```env
# Configuration SMTP
MAILER_DSN=smtp://user:password@smtp.example.com:587

# Email d'expéditeur (optionnel)
MAILER_FROM_EMAIL=noreply@votre-domaine.com
MAILER_FROM_NAME=Gestion Événements
```

---

## 🔧 Options SMTP Disponibles

### Option 1 : Gmail (Recommandé pour tester)

1. **Activez l'authentification à deux facteurs** sur votre compte Gmail
2. **Générez un mot de passe d'application** :
   - Allez sur https://myaccount.google.com/apppasswords
   - Créez un mot de passe d'application
   - Utilisez ce mot de passe (pas votre mot de passe Gmail normal)

3. **Configuration dans `.env.local`** :
```env
MAILER_DSN=smtp://votre-email@gmail.com:votre-mot-de-passe-app@smtp.gmail.com:587
MAILER_FROM_EMAIL=votre-email@gmail.com
MAILER_FROM_NAME=Gestion Événements
```

### Option 2 : Mailtrap (Parfait pour les tests - GRATUIT)

1. **Créez un compte gratuit** sur https://mailtrap.io
2. **Récupérez vos identifiants SMTP** dans votre inbox
3. **Configuration dans `.env.local`** :
```env
MAILER_DSN=smtp://username:password@smtp.mailtrap.io:2525
MAILER_FROM_EMAIL=test@example.com
MAILER_FROM_NAME=Gestion Événements
```

**Avantage** : Mailtrap capture tous les emails sans les envoyer réellement. Parfait pour tester !

### Option 3 : Autre serveur SMTP

```env
MAILER_DSN=smtp://username:password@smtp.votre-serveur.com:587
MAILER_FROM_EMAIL=noreply@votre-domaine.com
MAILER_FROM_NAME=Gestion Événements
```

### Option 4 : Mode Null (Pour tester le code sans envoyer)

```env
MAILER_DSN=null://null
```

---

## ✅ Test Rapide

1. **Créez `.env.local`** avec votre configuration SMTP
2. **Videz le cache** :
   ```bash
   php bin/console cache:clear
   ```
3. **Créez un nouvel événement** via `/admin/evenement/new`
4. **Vérifiez** :
   - Si Gmail : Vérifiez votre boîte de réception
   - Si Mailtrap : Vérifiez votre inbox Mailtrap
   - Si autre SMTP : Vérifiez les logs : `tail -f var/log/dev.log`

---

## 🔍 Vérification

### Vérifier que la configuration est chargée :
```bash
php bin/console debug:container --parameter=env(MAILER_DSN)
```

### Vérifier les logs :
```bash
tail -f var/log/dev.log | grep EventNotification
```

### Tester avec un utilisateur :
Assurez-vous d'avoir au moins un utilisateur actif avec un email :
```bash
php bin/console doctrine:query:sql "SELECT email, is_active FROM user WHERE is_active = 1"
```

---

## ⚠️ Notes Importantes

1. **Messenger est désactivé** : Les emails sont envoyés de manière synchrone pour faciliter les tests
2. **Sécurité** : Ne commitez JAMAIS `.env.local` dans Git (il est déjà dans `.gitignore`)
3. **Gmail** : Utilisez toujours un mot de passe d'application, pas votre mot de passe normal
4. **Mailtrap** : Limite de 500 emails/mois en version gratuite

---

## 🐛 Dépannage

### Erreur "Connection refused"
- Vérifiez que le serveur SMTP est accessible
- Vérifiez le port (587 pour TLS, 465 pour SSL, 25 pour non sécurisé)

### Erreur "Authentication failed"
- Vérifiez vos identifiants
- Pour Gmail, utilisez un mot de passe d'application

### Aucun email reçu
- Vérifiez les logs : `var/log/dev.log`
- Vérifiez que vous avez des utilisateurs actifs avec email
- Vérifiez le dossier spam
