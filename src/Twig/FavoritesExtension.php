<?php

namespace App\Twig;

use App\Repository\Evenement\UserEvenementInteractionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class FavoritesExtension extends AbstractExtension
{
    private Security $security;
    private UserEvenementInteractionRepository $interactionRepository;

    public function __construct(Security $security, UserEvenementInteractionRepository $interactionRepository)
    {
        $this->security = $security;
        $this->interactionRepository = $interactionRepository;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_favorites_count', [$this, 'getFavoritesCount']),
        ];
    }

    public function getFavoritesCount(): int
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            return 0;
        }

        return $this->interactionRepository->count([
            'user' => $user,
            'typeInteraction' => 'favorite'
        ]);
    }
}
