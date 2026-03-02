# 📧 Pourquoi j'ai reçu beaucoup d'emails ?

## Explication

Le système envoie **automatiquement un email à CHAQUE utilisateur actif** dans votre base de données lorsqu'un événement est créé.

### Situation actuelle

- **Nombre d'utilisateurs actifs** : 11
- **Emails envoyés par événement** : 11 (un par utilisateur)
- **Si vous créez 3 événements** : 33 emails au total

## Solutions

### Option 1 : Limiter le nombre d'emails envoyés

Dans `src/Controller/EvenementController.php`, ligne 65, modifiez :

```php
// Envoyer à seulement 5 utilisateurs (au lieu de tous)
$result = $eventNotificationService->notifyNewEvent($evenement, 5);
```

### Option 2 : Désactiver complètement l'envoi automatique

Dans `src/Controller/EvenementController.php`, commentez les lignes 63-78 :

```php
// Envoyer les notifications par email à tous les utilisateurs
/*
try {
    $result = $eventNotificationService->notifyNewEvent($evenement, null);
    // ...
} catch (\Exception $e) {
    // ...
}
*/
```

### Option 3 : Envoyer seulement aux administrateurs

Modifiez le service pour filtrer par rôle :

```php
// Dans EventNotificationService.php, ligne 30
$users = $this->userRepository->findBy([
    'isActive' => true,
    'roles' => ['ROLE_ADMIN'] // Seulement les admins
]);
```

### Option 4 : Ajouter une case à cocher dans le formulaire

Ajoutez une option "Envoyer une notification par email" dans le formulaire de création d'événement.

## Recommandation

Pour les tests, utilisez l'**Option 1** avec un nombre limité (ex: 2-3 utilisateurs) pour éviter de recevoir trop d'emails.

Pour la production, gardez l'envoi à tous les utilisateurs, c'est le comportement attendu.
