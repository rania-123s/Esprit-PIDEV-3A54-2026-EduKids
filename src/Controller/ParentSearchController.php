<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ParentSearchController extends AbstractController
{
    public function __construct(private readonly UserRepository $userRepository)
    {
    }

    #[Route('/api/parents', name: 'api_parents_search', methods: ['GET'])]
    #[Route('/chat/parents/search', name: 'chat_parent_search', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function search(Request $request): JsonResponse
    {
        /** @var User $actor */
        $actor = $this->getUser();

        if (!$this->canUseParentChat($actor)) {
            return $this->json(['error' => 'Only admins and parents can search parents.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $query = trim((string) $request->query->get('q', ''));
        if (mb_strlen($query) < 2) {
            return $this->json([]);
        }

        $parents = $this->userRepository->searchParentsByName($query, $actor->getId(), 20);
        $data = array_map(fn (User $parent): array => [
            'id' => $parent->getId(),
            'firstName' => $parent->getFirstName(),
            'lastName' => $parent->getLastName(),
            'name' => $this->buildDisplayName($parent),
            'email' => $parent->getEmail(),
        ], $parents);

        return $this->json($data);
    }

    private function canUseParentChat(User $user): bool
    {
        $roles = $user->getRoles();

        return in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_PARENT', $roles, true);
    }

    private function buildDisplayName(User $user): string
    {
        $parts = array_filter([$user->getFirstName(), $user->getLastName()]);
        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return (string) $user->getEmail();
    }
}
