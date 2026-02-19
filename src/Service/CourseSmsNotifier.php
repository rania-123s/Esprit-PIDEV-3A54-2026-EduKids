<?php

namespace App\Service;

use App\Entity\Cours;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CourseSmsNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{
     *     configured: bool,
     *     total: int,
     *     sent: int,
     *     failed: int,
     *     reason: null|'missing_twilio_credentials'|'invalid_twilio_from_number'|'missing_recipients'
     * }
     */
    public function notifyStudentsAboutNewCourse(Cours $cours): array
    {
        $accountSid = $this->readEnv('TWILIO_ACCOUNT_SID');
        $authToken = $this->readEnv('TWILIO_AUTH_TOKEN');
        $fromNumber = $this->normalizePhoneNumber($this->readEnv('TWILIO_FROM_NUMBER'));
        $recipients = $this->parseRecipients($this->readEnv('STUDENT_SMS_RECIPIENTS'));

        if ($accountSid === '' || $authToken === '') {
            $this->logger->warning('SMS non configure: credentials Twilio manquants.');
            return [
                'configured' => false,
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'reason' => 'missing_twilio_credentials',
            ];
        }

        if ($fromNumber === null) {
            $this->logger->warning('SMS non configure: TWILIO_FROM_NUMBER invalide.');
            return [
                'configured' => false,
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'reason' => 'invalid_twilio_from_number',
            ];
        }

        if ($recipients === []) {
            $this->logger->warning('SMS non configure: aucun destinataire.');
            return [
                'configured' => false,
                'total' => 0,
                'sent' => 0,
                'failed' => 0,
                'reason' => 'missing_recipients',
            ];
        }

        $total = count($recipients);
        $sent = 0;
        $failed = 0;
        $message = $this->buildCourseMessage($cours);
        $endpoint = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            rawurlencode($accountSid)
        );

        foreach ($recipients as $recipient) {
            try {
                $response = $this->httpClient->request('POST', $endpoint, [
                    'auth_basic' => [$accountSid, $authToken],
                    'body' => [
                        'To' => $recipient,
                        'From' => $fromNumber,
                        'Body' => $message,
                    ],
                    'timeout' => 15,
                ]);

                $statusCode = $response->getStatusCode();
                if ($statusCode >= 200 && $statusCode < 300) {
                    $sent++;
                } else {
                    $failed++;
                    $this->logger->warning('SMS Twilio non envoye', [
                        'status_code' => $statusCode,
                        'to' => $recipient,
                    ]);
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->error('Erreur envoi SMS Twilio', [
                    'to' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'configured' => true,
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'reason' => null,
        ];
    }

    private function buildCourseMessage(Cours $cours): string
    {
        $title = trim((string) $cours->getTitre());
        $message = $title === ''
            ? 'EduKids: Un nouveau cours a ete ajoute.'
            : sprintf('EduKids: Un nouveau cours a ete ajoute - "%s".', $title);

        return mb_substr($message, 0, 300);
    }

    /**
     * @return list<string>
     */
    private function parseRecipients(string $rawRecipients): array
    {
        if ($rawRecipients === '') {
            return [];
        }

        $parts = preg_split('/[,\n;\r]+/', $rawRecipients);
        if (!is_array($parts)) {
            return [];
        }

        $recipients = [];
        foreach ($parts as $part) {
            $phone = $this->normalizePhoneNumber((string) $part);
            if ($phone !== null) {
                $recipients[] = $phone;
            }
        }

        return array_values(array_unique($recipients));
    }

    private function normalizePhoneNumber(string $rawPhone): ?string
    {
        $phone = trim($rawPhone);
        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/[\s\-\(\)]/', '', $phone) ?? '';
        if ($phone === '') {
            return null;
        }

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (!preg_match('/^\+[1-9]\d{7,14}$/', $phone)) {
            return null;
        }

        return $phone;
    }

    private function readEnv(string $key): string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return is_string($value) ? trim($value) : '';
    }
}
