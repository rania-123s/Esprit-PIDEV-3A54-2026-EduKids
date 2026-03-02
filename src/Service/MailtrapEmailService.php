<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailtrapEmailService
{
    public function __construct(
        private readonly string $mailtrapApiToken,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer
    ) {
    }

    public function sendOtpEmail(string $email, string $otp): bool
    {
        try {
            $message = (new Email())
                ->from('hello@demomailtrap.co')
                ->to($email)
                ->subject('Your Password Reset Code - Eduport')
                ->html($this->getOtpEmailTemplate($email, $otp));

            $this->mailer->send($message);
            $this->logger->info('[MAILER] OTP email sent', [
                'email' => $email,
                'mailtrap_token_length' => strlen($this->mailtrapApiToken),
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('[MAILER] OTP email failed', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            return getenv('APP_ENV') !== 'prod';
        }
    }

    public function sendPasswordResetConfirmation(string $email, string $userName = ''): bool
    {
        try {
            $message = (new Email())
                ->from('hello@demomailtrap.co')
                ->to($email)
                ->subject('Password Changed Successfully - Eduport')
                ->html($this->getPasswordResetConfirmationTemplate($email, $userName));

            $this->mailer->send($message);
            $this->logger->info('[MAILER] Password reset confirmation sent', [
                'email' => $email,
            ]);

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('[MAILER] Password reset confirmation failed', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            return getenv('APP_ENV') !== 'prod';
        }
    }

    private function getOtpEmailTemplate(string $email, string $otp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<body>
    <h2>Password reset code</h2>
    <p>Hello,</p>
    <p>Your verification code is: <strong>{$otp}</strong></p>
    <p>This code expires in 10 minutes.</p>
    <p>If you did not request this, please ignore this email.</p>
    <hr>
    <small>Account email: {$email}</small>
</body>
</html>
HTML;
    }

    private function getPasswordResetConfirmationTemplate(string $email, string $userName): string
    {
        $greeting = $userName !== '' ? 'Hello ' . $userName : 'Hello';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<body>
    <h2>Password changed successfully</h2>
    <p>{$greeting},</p>
    <p>Your password has been updated.</p>
    <p>If this was not you, contact support immediately.</p>
    <hr>
    <small>Account email: {$email}</small>
</body>
</html>
HTML;
    }
}
