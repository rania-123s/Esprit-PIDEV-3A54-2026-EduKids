# 🚀 Configuration Gmail SMTP - Guide Rapide

## ⚡ Étapes Rapides

### 1. Obtenir un mot de passe d'application Gmail

1. **Activez la 2FA** (si pas déjà fait) : https://myaccount.google.com/security
2. **Créez un mot de passe d'application** : https://myaccount.google.com/apppasswords
   - Application : `Mail`
   - Appareil : `Autre (nom personnalisé)` → Entrez : `Gestion Evenements`
   - Cliquez "Générer"
   - **Copiez le mot de passe** (16 caractères, ex: `abcd efgh ijkl mnop`)

### 2. Mettre à jour `.env.local`

Remplacez la ligne `MAILER_DSN` par :

```env
MAILER_DSN=smtp://votre-email@gmail.com:VOTRE_MOT_DE_PASSE_APP@smtp.gmail.com:587
```

**Exemple :**
Si votre email est `monemail@gmail.com` et le mot de passe d'app est `abcd efgh ijkl mnop`, utilisez :

```env
MAILER_DSN=smtp://monemail@gmail.com:abcdefghijklmnop@smtp.gmail.com:587
```

⚠️ **Important :** Supprimez les espaces du mot de passe !

### 3. Mettre à jour l'email d'expéditeur

Dans `config/services.yaml`, ligne 34, changez :

```yaml
$fromEmail: 'votre-email@gmail.com'  # Votre email Gmail
```

### 4. Vider le cache

```bash
php bin/console cache:clear
```

### 5. Tester

Créez un événement et vérifiez les boîtes de réception Gmail !

---

## ⚠️ Notes Importantes

- ✅ Utilisez un **mot de passe d'application**, pas votre mot de passe Gmail
- ✅ Limite Gmail : 500 emails/jour (gratuit)
- ✅ Les premiers emails peuvent aller dans le spam (normal)
