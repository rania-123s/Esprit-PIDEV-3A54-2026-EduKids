# Push de la branche integration

L’historique a été nettoyé (`.env.local` retiré). Pour pousser :

## 1. Ouvrir un terminal dans le projet

```bash
cd C:\Users\assil\Desktop\feriel\gestion-evenements
```

## 2. Lancer le push (force nécessaire car l’historique a été réécrit)

```bash
git push origin integration --force-with-lease
```

Si tu as une erreur du type **"remote contains work"** ou **"rejected"**, utilise :

```bash
git push origin integration --force
```

## 3. Si GitHub demande une authentification

- **HTTPS** : utilise un **Personal Access Token** (PAT) comme mot de passe, pas ton mot de passe GitHub.
  - Créer un token : GitHub → Settings → Developer settings → Personal access tokens.
- **SSH** : vérifie que `git remote -v` pointe vers `git@github.com:...` et que ta clé SSH est ajoutée (`ssh -T git@github.com`).

## 4. Vérifier le remote

```bash
git remote -v
```

Si l’URL est en HTTPS et que le push échoue, tu peux passer en SSH :

```bash
git remote set-url origin git@github.com:rania-123s/EduKids.git
git push origin integration --force-with-lease
```
