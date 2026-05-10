# Deploiement Web Via Git

Ce projet Symfony peut etre heberge via Git, mais pas avec GitHub Pages. Il faut un hebergeur qui supporte PHP 8.2+, MySQL/MariaDB et Composer.

## 1. Envoyer le projet sur GitHub

Depuis le dossier Symfony:

```powershell
git init
git add .
git commit -m "Prepare Symfony web deployment"
git branch -M main
git remote add origin https://github.com/USER/REPOSITORY.git
git push -u origin main
```

Ne poussez jamais `.env`, `.env.local`, `vendor/`, `var/` ou les vrais mots de passe. Ils sont ignores par `.gitignore`.

## 2. Configurer l'hebergeur

Sur l'hebergeur:

- PHP: 8.2 ou plus
- Document root: `public/`
- Base de donnees: MySQL/MariaDB
- Variables d'environnement: copier `.env.prod.example`, puis remplacer les valeurs par les vraies valeurs du serveur

Variables minimum:

```env
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=une_cle_longue_et_secrete
DATABASE_URL="mysql://user:password@host:3306/database?serverVersion=10.11.2-MariaDB&charset=utf8mb4"
DEFAULT_URI="https://votre-domaine.com"
```

## 3. Fonctions IA en production

Les fonctions IA ne doivent pas recevoir leurs cles depuis GitHub. Le code est pousse avec Git, puis les vraies cles sont ajoutees dans le panel de l'hebergeur ou dans un fichier `.env.local` prive sur le serveur.

Variables IA utilisees par le projet:

```env
POLLINATIONS_API_KEY=cle_pour_generation_images_quiz_ecommerce
GEMINI_API_KEY=cle_gemini_pour_generation_cours
GOOGLE_GEMINI_API_KEY=cle_gemini_alternative
GEMINI_MODEL=gemini-1.5-flash
OPENAI_API_KEY=cle_openai_pour_resume_cours_tuteur_images
OPENAI_MODEL=gpt-4o-mini
OPENAI_IMAGE_MODEL=gpt-image-1
GROQ_API_KEY=cle_groq_pour_recommandations_activites
OPENROUTER_API_KEY=cle_openrouter_pour_services_chat_texte
OPENROUTER_API_URL=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_MODEL=meta-llama/llama-3.1-8b-instruct:free
ATTACHMENT_SUMMARY_API_KEY=cle_pour_resume_piece_jointe_si_utilisee
```

Services locaux:

```env
OLLAMA_BASE_URL=
OLLAMA_TEXT_MODEL=
LIBRETRANSLATE_URL=
```

Sur un hebergement mutualise, Ollama et LibreTranslate locaux ne fonctionneront pas sauf si vous avez une URL publique vers ces services. Les autres IA fonctionnent normalement si les cles API sont configurees et si l'hebergeur autorise les requetes HTTP sortantes.

## 4. Commandes apres chaque pull Git

Dans le dossier du projet sur le serveur:

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console assets:install public --env=prod
php bin/console cache:clear --env=prod
```

Si l'hebergeur ne donne pas acces SSH, lancer ces commandes depuis le terminal/cron fourni par le panel, ou utiliser l'option Composer/Deploy du panel.

## 5. Verification

Apres deploiement:

- ouvrir `/`
- ouvrir `/courses`
- tester la connexion
- tester une generation IA de cours/quiz/e-commerce selon les cles configurees
- verifier les images dans `public/uploads`
- verifier que les cours `DRAFT` et `ARCHIVED` ne sont pas visibles cote student

## 6. Notes importantes

- `public/.htaccess` est fourni pour Apache/cPanel afin que les routes Symfony fonctionnent.
- La base locale `EduKids` doit etre exportee puis importee dans la base MySQL de l'hebergeur.
- Les uploads ne sont pas stockes dans Git. Pour garder les images deja ajoutees, copier le dossier `public/uploads` manuellement vers le serveur.
