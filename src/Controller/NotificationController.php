<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(private readonly NotificationRepository $notificationRepository)
    {
    }

    #[Route('', name: 'notifications_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $limit = max(1, min((int) $request->query->get('limit', 15), 50));
        $notifications = $this->notificationRepository->findLatestForReceiver($user, $limit);

        return $this->json([
            'items' => array_map(fn (Notification $notification): array => $this->serializeNotification($notification), $notifications),
        ]);
    }

    #[Route('/unread-count', name: 'notifications_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $count = $this->notificationRepository->countUnreadForReceiver($user);

        return $this->json(['count' => $count]);
    }

    #[Route('/{id}/read', name: 'notifications_read', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function read(int $id, Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        /** @var User $user */
        $user = $this->getUser();

        $notification = $this->notificationRepository->findOneForReceiver($id, $user);
        if (!$notification instanceof Notification) {
            return $this->json(['error' => 'Notification introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$notification->isRead()) {
            $notification->setIsRead(true);
            $this->notificationRepository->getEntityManager()->flush();
        }

        return $this->json([
            'ok' => true,
            'id' => $notification->getId(),
            'unreadCount' => $this->notificationRepository->countUnreadForReceiver($user),
        ]);
    }

    #[Route('/read-all', name: 'notifications_read_all', methods: ['POST'])]
    public function readAll(Request $request): JsonResponse
    {
        $this->assertCsrf($request);
        /** @var User $user */
        $user = $this->getUser();

        $updated = $this->notificationRepository->markAllAsReadForReceiver($user);

        return $this->json([
            'ok' => true,
            'updated' => $updated,
            'unreadCount' => 0,
        ]);
    }

    private function serializeNotification(Notification $notification): array
    {
        $sender = $notification->getSender();
        $senderName = $this->buildDisplayName($sender);
        $text = $this->normalizeNotificationText($notification->getText(), $senderName);

        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'text' => $text,
            'isRead' => $notification->isRead(),
            'createdAt' => $notification->getCreatedAt()?->format(DATE_ATOM),
            'conversationId' => $notification->getConversationId(),
            'chatUrl' => $notification->getConversationId() !== null
                ? $this->generateUrl('chat_show', ['id' => $notification->getConversationId()])
                : $this->generateUrl('chat_index'),
            'sender' => [
                'id' => $sender?->getId(),
                'name' => $senderName,
                'initials' => $this->buildInitials($senderName),
                'avatarUrl' => null,
            ],
        ];
    }

    private function buildDisplayName(?User $user): string
    {
        if ($user === null) {
            return 'Utilisateur';
        }

        $first = trim((string) $user->getFirstName());
        $last = trim((string) $user->getLastName());
        $parts = array_values(array_filter([$first, $last]));

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return (string) $user->getEmail();
    }

    private function buildInitials(string $name): string
    {
        $parts = array_values(array_filter(explode(' ', trim($name))));
        if ($parts === []) {
            return 'U';
        }

        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'U';
    }

    private function normalizeNotificationText(string $rawText, string $senderName): string
    {
        $text = trim($rawText);
        if ($text === '') {
            return sprintf('%s a envoyé un message', $senderName);
        }

        return str_replace(
            [
                ' a envoye une piece jointe',
                ' a envoye un message',
                'a envoye une piece jointe',
                'a envoye un message',
            ],
            [
                ' a envoyé une pièce jointe',
                ' a envoyé un message',
                'a envoyé une pièce jointe',
                'a envoyé un message',
            ],
            $text
        );
    }

    private function assertCsrf(Request $request): void
    {
        $token = $request->headers->get('X-CSRF-TOKEN') ?? $request->request->get('_token');
        if (!$this->isCsrfTokenValid('notification_action', (string) $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
