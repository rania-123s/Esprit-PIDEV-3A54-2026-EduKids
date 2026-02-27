<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\User;
use App\Entity\UserEvenementInteraction;
use App\Repository\Evenement\UserEvenementInteractionRepository;
use Doctrine\ORM\EntityManagerInterface;

class InteractionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserEvenementInteractionRepository $interactionRepo
    ) {}

    public function toggleLike(User $user, Evenement $evenement): array
    {
        return $this->toggleInteraction($user, $evenement, UserEvenementInteraction::TYPE_LIKE);
    }

    public function toggleDislike(User $user, Evenement $evenement): array
    {
        return $this->toggleInteraction($user, $evenement, UserEvenementInteraction::TYPE_DISLIKE);
    }

    public function toggleFavorite(User $user, Evenement $evenement): array
    {
        return $this->toggleInteraction($user, $evenement, UserEvenementInteraction::TYPE_FAVORITE);
    }

    private function toggleInteraction(User $user, Evenement $evenement, string $type): array
    {
        $existing = $this->interactionRepo->findInteraction($user, $evenement, $type);

        if ($existing) {
            // Remove interaction
            $this->em->remove($existing);
            $this->updateCounter($evenement, $type, -1);
            $this->em->flush();
            return ['action' => 'removed', 'type' => $type];
        }

        // Remove opposite interaction (like/dislike are mutually exclusive)
        if ($type === UserEvenementInteraction::TYPE_LIKE) {
            $this->removeIfExists($user, $evenement, UserEvenementInteraction::TYPE_DISLIKE);
        } elseif ($type === UserEvenementInteraction::TYPE_DISLIKE) {
            $this->removeIfExists($user, $evenement, UserEvenementInteraction::TYPE_LIKE);
        }

        // Add new interaction
        $interaction = new UserEvenementInteraction();
        $interaction->setUser($user);
        $interaction->setEvenement($evenement);
        $interaction->setTypeInteraction($type);

        $this->em->persist($interaction);
        $this->updateCounter($evenement, $type, 1);
        $this->em->flush();

        return ['action' => 'added', 'type' => $type];
    }

    private function removeIfExists(User $user, Evenement $evenement, string $type): void
    {
        $existing = $this->interactionRepo->findInteraction($user, $evenement, $type);
        if ($existing) {
            $this->em->remove($existing);
            $this->updateCounter($evenement, $type, -1);
        }
    }

    private function updateCounter(Evenement $evenement, string $type, int $delta): void
    {
        match ($type) {
            UserEvenementInteraction::TYPE_LIKE => $delta > 0 ? $evenement->incrementLikes() : $evenement->decrementLikes(),
            UserEvenementInteraction::TYPE_DISLIKE => $delta > 0 ? $evenement->incrementDislikes() : $evenement->decrementDislikes(),
            UserEvenementInteraction::TYPE_FAVORITE => $delta > 0 ? $evenement->incrementFavorites() : $evenement->decrementFavorites(),
        };
    }

    public function getUserInteractions(User $user, Evenement $evenement): array
    {
        return [
            'liked' => $this->interactionRepo->hasInteraction($user, $evenement, UserEvenementInteraction::TYPE_LIKE),
            'disliked' => $this->interactionRepo->hasInteraction($user, $evenement, UserEvenementInteraction::TYPE_DISLIKE),
            'favorited' => $this->interactionRepo->hasInteraction($user, $evenement, UserEvenementInteraction::TYPE_FAVORITE),
        ];
    }
}
