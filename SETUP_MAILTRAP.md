# 🚀 Configuration Mailtrap - Guide Rapide

## ✅ Configuration déjà ajoutée dans `.env.local`

La ligne suivante a été ajoutée :
```env
MAILER_DSN=smtp://username:password@smtp.mailtrap.io:2525
```

## 📝 Étapes pour obtenir vos identifiants Mailtrap

### 1. Créer un compte Mailtrap (GRATUIT)

1. Allez sur **https://mailtrap.io**
2. Cliquez sur **"Sign Up"** (gratuit)
3. Créez votre compte

### 2. Récupérer vos identifiants SMTP

1. Une fois connecté, dans la barre latérale gauche, cliquez sur **"Sandboxes"** (sous la section "Transactional")
2. Vous verrez votre sandbox par défaut (ou créez-en une si nécessaire)
3. Cliquez sur votre sandbox pour l'ouvrir
4. Allez dans l'onglet **"SMTP Settings"** ou **"Integrations"**
5. Sélectionnez **"PHP"** ou **"SMTP"** dans la liste déroulante
6. Vous verrez quelque chose comme :
   ```
   Host: smtp.mailtrap.io
   Port: 2525
   Username: abc123def456
   Password: xyz789uvw012
   ```

**Note :** Si vous ne voyez pas "Sandboxes", cherchez dans la section **"Transactional"** de la barre latérale gauche.

### 3. Mettre à jour `.env.local`

Remplacez `username` et `password` dans votre fichier `.env.local` :

```env
MAILER_DSN=smtp://abc123def456:xyz789uvw012@smtp.mailtrap.io:2525
```

**Exemple concret :**
Si votre username est `abc123def456` et password `xyz789uvw012`, la ligne sera :
```env
MAILER_DSN=smtp://abc123def456:xyz789uvw012@smtp.mailtrap.io:2525
```

### 4. Vider le cache Symfony

```bash
php bin/console cache:clear
```

## 🧪 Tester la fonctionnalité

1. **Assurez-vous d'avoir des utilisateurs** dans votre base de données avec :
   - `is_active = 1`
   - Un email valide

2. **Créez un nouvel événement** via `/admin/evenement/new`

3. **Vérifiez dans Mailtrap** :
   - Allez dans **"Sandboxes"** (section Transactional)
   - Ouvrez votre sandbox
   - Vous verrez tous les emails envoyés dans la liste
   - Cliquez sur un email pour voir le contenu complet

## ✅ Avantages de Mailtrap

- ✅ **Gratuit** (500 emails/mois)
- ✅ **Capture tous les emails** sans les envoyer réellement
- ✅ **Interface web** pour voir les emails
- ✅ **Parfait pour le développement** et les tests
- ✅ **Pas besoin de configurer un vrai serveur SMTP**

## 🔍 Vérification

### Vérifier que la configuration est chargée :
```bash
php bin/console debug:container --parameter=env(MAILER_DSN)
```

### Vérifier les logs :
```bash
tail -f var/log/dev.log | grep EventNotification
```

### Vérifier les utilisateurs actifs :
```bash
php bin/console doctrine:query:sql "SELECT email, is_active FROM user WHERE is_active = 1"
```

## 🎉 C'est tout !

Une fois que vous avez remplacé `username` et `password` dans `.env.local` avec vos vrais identifiants Mailtrap, vous pouvez tester en créant un événement !
