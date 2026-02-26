# 🚀 Guide Rapide - Test des Emails

## ⚡ Démarrage Rapide (3 étapes)

### 1️⃣ Configurer MAILER_DSN dans `.env`

Ouvrez votre fichier `.env` et ajoutez/modifiez :

```env
MAILER_DSN=smtp://localhost:1025
```

### 2️⃣ Démarrer Mailpit (pour capturer les emails)

```bash
docker-compose up -d mailer
```

### 3️⃣ Tester

1. Créez un nouvel événement via `/admin/evenement/new`
2. Ouvrez http://localhost:8025 pour voir les emails

---

## ⚠️ Important : Messenger (Emails Asynchrones)

Si vos emails sont configurés pour être envoyés de manière asynchrone (dans `messenger.yaml`), vous devez **consommer la queue** :

```bash
php bin/console messenger:consume async -vv
```

Ou pour tester en mode synchrone, modifiez temporairement `config/packages/messenger.yaml` :

```yaml
routing:
    # Symfony\Component\Mailer\Messenger\SendEmailMessage: async  # ← Commentez cette ligne
```

---

## ✅ Vérifications Rapides

### Vérifier que vous avez des utilisateurs :
```bash
php bin/console doctrine:query:sql "SELECT email, is_active FROM user WHERE is_active = 1"
```

### Vérifier les logs :
```bash
tail -f var/log/dev.log | grep EventNotification
```

### Vérifier la queue Messenger :
```bash
php bin/console messenger:stats
```

---

## 🔧 Si ça ne marche pas

1. **Videz le cache :**
   ```bash
   php bin/console cache:clear
   ```

2. **Vérifiez que Mailpit tourne :**
   ```bash
   docker-compose ps
   ```

3. **Testez avec null://null (pas d'envoi réel) :**
   ```env
   MAILER_DSN=null://null
   ```
   Cela permettra de voir si le code fonctionne sans erreur.
