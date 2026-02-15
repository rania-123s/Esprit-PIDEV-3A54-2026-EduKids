<?php

namespace App\Mailer\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends email via Gmail SMTP using OAuth2 (refresh_token -> access_token).
 */
class GmailOAuth2Transport extends AbstractTransport
{
    public function __construct(
        private string $clientId,
        #[\SensitiveParameter] private string $clientSecret,
        #[\SensitiveParameter] private string $refreshToken,
        private string $userEmail,
        private HttpClientInterface $httpClient,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    protected function doSend(SentMessage $message): void
    {
        $accessToken = $this->fetchAccessToken();
        $smtp = new EsmtpTransport('smtp.gmail.com', 465, true, $this->dispatcher, $this->logger);
        $smtp->setUsername($this->userEmail);
        $smtp->setPassword($accessToken);
        $smtp->setAuthenticators([new XOAuth2Authenticator()]);
        $smtp->send($message->getMessage(), $message->getEnvelope());
    }

    private function fetchAccessToken(): string
    {
        $response = $this->httpClient->request('POST', 'https://oauth2.googleapis.com/token', [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $this->refreshToken,
                'grant_type' => 'refresh_token',
            ],
        ]);
        $data = $response->toArray();
        if (empty($data['access_token'])) {
            throw new \RuntimeException('Google OAuth2: no access_token in response.');
        }
        return $data['access_token'];
    }

    public function __toString(): string
    {
        return sprintf('gmail+oauth2://%s@smtp.gmail.com', $this->userEmail);
    }
}
