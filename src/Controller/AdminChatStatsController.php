<?php

namespace App\Controller;

use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminChatStatsController extends AbstractController
{
    private const APP_TIMEZONE = 'Africa/Tunis';

    #[Route('/chat/stats', name: 'admin_chat_stats', methods: ['GET'])]
    public function index(
        Request $request,
        MessageRepository $messageRepository,
        UserRepository $userRepository
    ): Response {
        $period = $request->query->getString('period', '7d');
        $stats = $this->buildDashboardData($messageRepository, $userRepository, $period);

        return $this->render('admin/chat_stats/index.html.twig', $stats);
    }

    #[Route('/chat/stats/data', name: 'admin_chat_stats_data', methods: ['GET'])]
    public function data(
        Request $request,
        MessageRepository $messageRepository,
        UserRepository $userRepository
    ): JsonResponse {
        $period = $request->query->getString('period', '7d');
        $stats = $this->buildDashboardData($messageRepository, $userRepository, $period);

        $topUsers = array_map(
            static function (array $user): array {
                $lastActivity = $user['lastActivity'] ?? null;

                return [
                    'username' => (string) ($user['username'] ?? ''),
                    'email' => (string) ($user['email'] ?? ''),
                    'messageCount' => (int) ($user['messageCount'] ?? 0),
                    'lastActivity' => $lastActivity instanceof \DateTimeImmutable ? $lastActivity->format(DATE_ATOM) : null,
                ];
            },
            $stats['topUsers']
        );

        return $this->json([
            'timezone' => $stats['timezone'],
            'generatedAt' => $stats['generatedAt'] instanceof \DateTimeImmutable
                ? $stats['generatedAt']->format(DATE_ATOM)
                : null,
            'kpis' => $stats['kpis'],
            'charts' => $stats['charts'],
            'topUsers' => $topUsers,
            'period' => $period,
        ]);
    }

    private function buildDashboardData(
        MessageRepository $messageRepository,
        UserRepository $userRepository,
        string $period
    ): array {
        $timezone = new \DateTimeZone(self::APP_TIMEZONE);
        $now = new \DateTimeImmutable('now', $timezone);
        $todayStart = $now->setTime(0, 0, 0);
        $weekStart = $now->modify('monday this week')->setTime(0, 0, 0);
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $chartDays = $this->resolvePeriodDays($period);

        $topUser = $messageRepository->getTopUser();
        $topUserDisplayName = 'Aucun';
        $topUserMessageCount = 0;
        if (is_array($topUser)) {
            $email = (string) ($topUser['email'] ?? '');
            $topUserDisplayName = $this->buildDisplayName(
                $topUser['firstName'] ?? null,
                $topUser['lastName'] ?? null,
                $email
            );
            $topUserMessageCount = (int) ($topUser['messageCount'] ?? 0);
        }

        $topUsers = array_map(
            function (array $row) use ($timezone): array {
                $email = (string) ($row['email'] ?? '');

                return [
                    'username' => $this->buildDisplayName(
                        $row['firstName'] ?? null,
                        $row['lastName'] ?? null,
                        $email
                    ),
                    'email' => $email,
                    'messageCount' => (int) ($row['messageCount'] ?? 0),
                    'lastActivity' => $this->normalizeDateTime($row['lastActivity'] ?? null, $timezone),
                ];
            },
            $messageRepository->getTopUsers(10)
        );

        $hourRows = $messageRepository->getMessagesByHour($chartDays);
        $dayRows = $messageRepository->getMessagesByDay($chartDays);

        $hourLabels = array_map(
            static fn (array $row): string => sprintf('%02d:00', (int) $row['hour']),
            $hourRows
        );
        $hourData = array_map(
            static fn (array $row): int => (int) $row['count'],
            $hourRows
        );
        $dayLabels = array_map(
            static function (array $row) use ($timezone): string {
                $rawDate = (string) ($row['date'] ?? '');
                $date = \DateTimeImmutable::createFromFormat('Y-m-d', $rawDate, $timezone);
                if (!$date instanceof \DateTimeImmutable) {
                    $date = new \DateTimeImmutable($rawDate, $timezone);
                }

                return $date->format('d/m');
            },
            $dayRows
        );
        $dayData = array_map(
            static fn (array $row): int => (int) ($row['count'] ?? 0),
            $dayRows
        );

        return [
            'timezone' => self::APP_TIMEZONE,
            'generatedAt' => $now,
            'kpis' => [
                'totalMessages' => $messageRepository->getTotalMessages(),
                'totalUsers' => $userRepository->count([]),
                'activeUsers7' => $messageRepository->getActiveUsersCount(7),
                'activeUsers30' => $messageRepository->getActiveUsersCount(30),
                'topUserName' => $topUserDisplayName,
                'topUserMessages' => $topUserMessageCount,
                'messagesToday' => $messageRepository->getMessagesCountBetween($todayStart, $now),
                'messagesWeek' => $messageRepository->getMessagesCountBetween($weekStart, $now),
                'messagesMonth' => $messageRepository->getMessagesCountBetween($monthStart, $now),
            ],
            'charts' => [
                'hourly' => [
                    'labels' => $hourLabels,
                    'data' => $hourData,
                ],
                'daily' => [
                    'labels' => $dayLabels,
                    'data' => $dayData,
                ],
            ],
            'topUsers' => $topUsers,
        ];
    }

    private function resolvePeriodDays(string $period): int
    {
        return match ($period) {
            'today' => 1,
            '30d' => 30,
            default => 7,
        };
    }

    private function buildDisplayName(?string $firstName, ?string $lastName, string $fallbackEmail): string
    {
        $parts = array_values(array_filter([
            trim((string) $firstName),
            trim((string) $lastName),
        ]));

        return $parts !== [] ? implode(' ', $parts) : $fallbackEmail;
    }

    private function normalizeDateTime(mixed $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTimezone($timezone);
        }

        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)->setTimezone($timezone);
        }

        if (is_string($value) && $value !== '') {
            return new \DateTimeImmutable($value, $timezone);
        }

        return null;
    }
}
