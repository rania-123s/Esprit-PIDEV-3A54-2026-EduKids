# Guide de Test - Fonctionnalité d'Envoi d'Emails

## 📋 Ce qui est nécessaire pour que la fonctionnalité fonctionne

### 1. Configuration du Mailer (MAILER_DSN)

Vous devez configurer la variable d'environnement `MAILER_DSN` dans votre fichier `.env`.

#### Option A : Mode Test (Mailpit - Recommandé pour le développement)
Mailpit capture tous les emails envoyés et les affiche dans une interface web.

```env
MAILER_DSN=smtp://localhost:1025
```

Puis démarrez Mailpit avec Docker :
```bash
docker-compose up -d mailer
```

Accédez à l'interface Mailpit : http://localhost:8025

#### Option B : Mode Null (Pour tester sans envoyer d'emails)
Les emails ne seront pas envoyés mais le code s'exécutera sans erreur.

```env
MAILER_DSN=null://null
```

#### Option C : SMTP Réel (Pour la production)
```env
MAILER_DSN=smtp://username:password@smtp.example.com:587
```

Exemple avec Gmail :
```env
MAILER_DSN=smtp://votre-email@gmail.com:votre-mot-de-passe@smtp.gmail.com:587
```

⚠️ **Note** : Pour Gmail, vous devez activer "Accès aux applications moins sécurisées" ou utiliser un "Mot de passe d'application".

---

## 🧪 Comment tester la fonctionnalité

### Étape 1 : Vérifier que vous avez des utilisateurs dans la base de données

Assurez-vous d'avoir au moins un utilisateur avec :
- `isActive = true`
- Un email valide dans le champ `email`

### Étape 2 : Configurer MAILER_DSN

Ajoutez dans votre fichier `.env` :
```env
MAILER_DSN=smtp://localhost:1025
```

### Étape 3 : Démarrer Mailpit (si vous utilisez l'option A)

```bash
docker-compose up -d mailer
```

### Étape 4 : Créer un nouvel événement

1. Connectez-vous en tant qu'administrateur
2. Allez sur `/admin/evenement/new`
3. Remplissez le formulaire et créez un nouvel événement
4. Après la création, vous devriez voir un message de succès indiquant le nombre d'emails envoyés

### Étape 5 : Vérifier les emails

**Si vous utilisez Mailpit :**
- Ouvrez http://localhost:8025 dans votre navigateur
- Vous verrez tous les emails envoyés avec leur contenu

**Si vous utilisez un SMTP réel :**
- Vérifiez la boîte de réception des utilisateurs

---

## 🔍 Vérification du code

### Fichiers créés/modifiés :

1. ✅ `src/Service/EventNotificationService.php` - Service d'envoi d'emails
2. ✅ `templates/emails/new_event_notification.html.twig` - Template d'email
3. ✅ `src/Controller/EvenementController.php` - Intégration dans le contrôleur
4. ✅ `config/services.yaml` - Configuration du service

### Points à vérifier :

- [ ] La variable `MAILER_DSN` est configurée dans `.env`
- [ ] Vous avez au moins un utilisateur actif avec un email
- [ ] Le service `EventNotificationService` est bien injecté dans le contrôleur
- [ ] Les logs montrent que les emails sont envoyés (vérifiez `var/log/dev.log`)

---

## 🐛 Dépannage

### Problème : "TransportException" ou erreur de connexion SMTP

**Solution :**
- Vérifiez que `MAILER_DSN` est correctement configuré
- Si vous utilisez Mailpit, assurez-vous qu'il est démarré : `docker-compose ps`
- Vérifiez les logs : `tail -f var/log/dev.log`

### Problème : Aucun email n'est envoyé

**Vérifications :**
1. Y a-t-il des utilisateurs actifs dans la base ?
   ```sql
   SELECT * FROM user WHERE is_active = 1 AND email IS NOT NULL;
   ```

2. Les emails sont-ils dans la queue Messenger ?
   - Vérifiez la table `messenger_messages` dans la base de données
   - Si oui, exécutez : `php bin/console messenger:consume async -vv`

3. Vérifiez les logs pour voir les erreurs :
   ```bash
   tail -f var/log/dev.log | grep EventNotification
   ```

### Problème : Erreur "Class 'App\Service\EventNotificationService' not found"

**Solution :**
```bash
composer dump-autoload
php bin/console cache:clear
```

---

## 📝 Exemple de test manuel

1. **Créer un utilisateur de test :**
   ```sql
   INSERT INTO user (email, password, roles, is_active, first_name, last_name)
   VALUES ('test@example.com', '$2y$13$...', '["ROLE_USER"]', 1, 'Test', 'User');
   ```

2. **Créer un événement via l'interface admin**

3. **Vérifier dans Mailpit** (http://localhost:8025) que l'email est bien reçu

---

## 🚀 Pour la production

1. Configurez un SMTP réel dans `.env.prod` :
   ```env
   MAILER_DSN=smtp://user:pass@smtp.example.com:587
   ```

2. Modifiez l'email d'expéditeur dans `config/services.yaml` :
   ```yaml
   App\Service\EventNotificationService:
       arguments:
           $fromEmail: 'noreply@votre-domaine.com'
           $fromName: 'Votre Plateforme'
   ```

3. Testez d'abord avec un compte de test avant de passer en production

---

## ✅ Checklist de test

- [ ] Configuration `MAILER_DSN` dans `.env`
- [ ] Au moins un utilisateur actif avec email
- [ ] Mailpit démarré (si mode test)
- [ ] Création d'un événement test
- [ ] Vérification de l'email dans Mailpit/boîte de réception
- [ ] Vérification du message de succès dans l'interface admin
- [ ] Vérification des logs pour confirmer l'envoi
