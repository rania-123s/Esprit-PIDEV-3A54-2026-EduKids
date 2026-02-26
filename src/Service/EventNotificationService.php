<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EventNotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private string $fromEmail = 'noreply@example.com',
        private string $fromName = 'Gestion Événements'
    ) {
    }

    /**
     * Envoie une notification par email à tous les utilisateurs actifs lorsqu'un nouvel événement est créé
     * 
     * @param Evenement $evenement L'événement à notifier
     * @param int|null $limit Limite le nombre d'emails à envoyer (null = tous les utilisateurs)
     * @param User|null $excludeUser Utilisateur à exclure de l'envoi (généralement l'admin qui crée l'événement)
     */
    public function notifyNewEvent(Evenement $evenement, ?int $limit = null, ?User $excludeUser = null): array
    {
        // Récupérer tous les utilisateurs actifs
        $users = $this->userRepository->findBy(['isActive' => true], null, $limit);
        
        if (empty($users)) {
            $this->logger->info('Aucun utilisateur actif trouvé pour la notification d\'événement.');
            return [
                'sent' => 0,
                'failed' => 0,
                'total' => 0
            ];
        }

        $sent = 0;
        $failed = 0;
        $emailsSent = []; // Pour éviter les doublons (un seul email par adresse)

        foreach ($users as $index => $user) {
            // Exclure l'utilisateur qui crée l'événement (généralement l'admin)
            if ($excludeUser && $user->getId() === $excludeUser->getId()) {
                $this->logger->info(sprintf(
                    'Utilisateur ID %d (%s) exclu de la notification (créateur de l\'événement).',
                    $user->getId(),
                    $user->getEmail()
                ));
                continue;
            }

            // Vérifier que l'utilisateur a un email valide
            if (!$user->getEmail()) {
                $this->logger->warning(sprintf('Utilisateur ID %d n\'a pas d\'email.', $user->getId()));
                $failed++;
                continue;
            }

            // Normaliser l'email (minuscules) pour éviter les doublons
            $emailAddress = strtolower(trim($user->getEmail()));
            
            // Vérifier si on a déjà envoyé un email à cette adresse
            if (isset($emailsSent[$emailAddress])) {
                $this->logger->info(sprintf(
                    'Email déjà envoyé à %s (utilisateur ID %d), ignoré pour éviter les doublons.',
                    $emailAddress,
                    $user->getId()
                ));
                continue;
            }

            // Valider le format de l'email pour éviter les erreurs de livraison
            if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning(sprintf(
                    'Email invalide ignoré : %s (utilisateur ID %d)',
                    $emailAddress,
                    $user->getId()
                ));
                $failed++;
                $emailsSent[$emailAddress] = true; // Marquer pour éviter de réessayer
                continue;
            }
            
            // Vérifier que l'email n'est pas l'email de l'expéditeur (pour éviter les boucles)
            if ($emailAddress === strtolower(trim($this->fromEmail))) {
                $this->logger->info(sprintf(
                    'Email de l\'expéditeur ignoré : %s (utilisateur ID %d)',
                    $emailAddress,
                    $user->getId()
                ));
                continue;
            }
            
            // Vérifier que l'email n'est pas dans une liste d'emails connus comme invalides
            // (Adresses qui ont causé des erreurs de livraison et des renvois d'erreurs)
            $invalidEmails = [
                'aziz@gmail.com',
                'issam@gmail.com', 
                'mohamed@gmail.com',
                'amin1@gmail.com',
                'test@example.com',
                'invalid@test.com'
            ];
            if (in_array($emailAddress, $invalidEmails)) {
                $this->logger->warning(sprintf(
                    'Email dans la liste noire ignoré (adresse invalide ou inexistante) : %s (utilisateur ID %d)',
                    $emailAddress,
                    $user->getId()
                ));
                $failed++;
                $emailsSent[$emailAddress] = true;
                continue;
            }

            try {
                // Créer un email unique pour chaque utilisateur avec son propre destinataire
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($emailAddress, $user->getFirstName() ?: $emailAddress))
                    ->replyTo(new Address($this->fromEmail, $this->fromName)) // Éviter les réponses aux erreurs
                    ->subject('Nouvel événement disponible : ' . $evenement->getTitre())
                    ->htmlTemplate('emails/new_event_notification.html.twig')
                    ->context([
                        'user' => $user,
                        'evenement' => $evenement,
                    ]);

                // Envoyer l'email individuellement pour chaque utilisateur
                $this->mailer->send($email);
                $sent++;
                $emailsSent[$emailAddress] = true; // Marquer cette adresse comme envoyée
                
                $this->logger->info(sprintf(
                    'Email de notification envoyé avec succès à %s (ID: %d) pour l\'événement "%s"',
                    $emailAddress,
                    $user->getId(),
                    $evenement->getTitre()
                ));
                
                // Ajouter un délai de 0.5 seconde entre chaque email pour Gmail SMTP
                // (sauf pour le dernier email)
                if ($index < count($users) - 1) {
                    usleep(500000); // 0.5 seconde en microsecondes
                }
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $failed++;
                
                // Logger l'erreur avec plus de détails pour le débogage
                $this->logger->error(sprintf(
                    'Erreur lors de l\'envoi de l\'email à %s (ID: %d) : %s',
                    $emailAddress,
                    $user->getId(),
                    $errorMessage
                ));
                
                // Si l'email est invalide ou n'existe pas, ne pas continuer à essayer
                if (strpos($errorMessage, '550') !== false || 
                    strpos($errorMessage, '550-5.1.1') !== false ||
                    strpos($errorMessage, 'not found') !== false ||
                    strpos($errorMessage, 'invalid') !== false) {
                    $this->logger->warning(sprintf(
                        'Adresse email invalide ou inexistante détectée : %s (ID: %d). Ignorée pour les prochains envois.',
                        $emailAddress,
                        $user->getId()
                    ));
                    // Marquer comme envoyé pour éviter de réessayer
                    $emailsSent[$emailAddress] = true;
                }
                
                // En cas d'erreur de limite de taux, attendre plus longtemps avant de continuer
                if (strpos($errorMessage, 'Too many emails per second') !== false || 
                    strpos($errorMessage, '550 5.7.0') !== false ||
                    strpos($errorMessage, 'rate limit') !== false) {
                    $this->logger->warning('Limite de taux atteinte, attente de 5 secondes supplémentaires...');
                    sleep(5);
                }
            }
        }

        return [
            'sent' => $sent,
            'failed' => $failed,
            'total' => count($users)
        ];
    }
}
