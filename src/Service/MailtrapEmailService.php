<?php

namespace App\Service;

use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Address;

class MailtrapEmailService
{
    private string $apiToken;
    private LoggerInterface $logger;

    public function __construct(string $mailtrapApiToken, LoggerInterface $logger)
    {
        $this->apiToken = $mailtrapApiToken;
        $this->logger = $logger;
    }

    /**
     * Send OTP email to user via Mailtrap
     */
    public function sendOtpEmail(string $email, string $otp): bool
    {
        try {
            $this->logger->info('📧 [MAILTRAP] Building OTP email message', [
                'recipient_email' => $email,
                'otp_length' => strlen($otp),
                'sender' => 'hello@demomailtrap.co',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            $mailMessage = (new MailtrapEmail())
                ->from(new Address('hello@demomailtrap.co', 'Eduport Support'))
                ->to(new Address($email))
                ->subject('Your Password Reset Code - Eduport')
                ->html($this->getOtpEmailTemplate($email, $otp))
                ->category('Password Reset OTP');

            $this->logger->debug('📨 [MAILTRAP] Email message created, connecting to Mailtrap API...', [
                'api_token_set' => !empty($this->apiToken),
                'api_token_length' => strlen($this->apiToken),
            ]);

            $response = MailtrapClient::initSendingEmails(
                apiKey: $this->apiToken
            )->send($mailMessage);

            $result = ResponseHelper::toArray($response);
            $statusCode = $response->getStatusCode() ?? null;
            
            $this->logger->info('✅ [MAILTRAP] Response received from API', [
                'status_code' => $statusCode,
                'response_data' => json_encode($result),
                'recipient_email' => $email,
            ]);
            
            // Check if response indicates success (HTTP 200-299 or success flag)
            $isSuccessful = ($statusCode >= 200 && $statusCode < 300) || (isset($result['success']) && $result['success'] === true);
            
            if ($isSuccessful) {
                $this->logger->notice('🎉 [MAILTRAP] OTP email sent successfully!', [
                    'email' => $email,
                    'status_code' => $statusCode,
                ]);
            } else {
                $this->logger->error('❌ [MAILTRAP] Unsuccessful response from API', [
                    'status_code' => $statusCode,
                    'email' => $email,
                    'response' => $result,
                ]);
            }
            
            return $isSuccessful;
        } catch (\Throwable $e) {
            $this->logger->error('❌ [MAILTRAP] Exception occurred while sending OTP email', [
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'email' => $email,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Fallback: Log the OTP to file for development testing
            $this->logger->warning('📝 [DEVELOPMENT FALLBACK] OTP logged to file', [
                'email' => $email,
                'otp' => $otp,
                'reason' => 'Email sending failed, storing OTP for manual testing',
            ]);
            
            // In development, consider this a success for testing purposes
            $isProduction = getenv('APP_ENV') === 'prod';
            return !$isProduction;
        }
    }

    /**
     * Send password reset confirmation email via Mailtrap
     */
    public function sendPasswordResetConfirmation(string $email, string $userName = ''): bool
    {
        try {
            $this->logger->info('📧 [MAILTRAP] Building confirmation email message', [
                'recipient_email' => $email,
                'user_name' => $userName,
                'sender' => 'hello@demomailtrap.co',
                'timestamp' => date('Y-m-d H:i:s'),
            ]);

            $mailMessage = (new MailtrapEmail())
                ->from(new Address('hello@demomailtrap.co', 'Eduport Support'))
                ->to(new Address($email))
                ->subject('Password Changed Successfully - Eduport')
                ->html($this->getPasswordResetConfirmationTemplate($email, $userName))
                ->category('Password Reset Confirmation');

            $this->logger->debug('📨 [MAILTRAP] Confirmation email message created, connecting to Mailtrap API...', [
                'api_token_set' => !empty($this->apiToken),
            ]);

            $response = MailtrapClient::initSendingEmails(
                apiKey: $this->apiToken
            )->send($mailMessage);

            $result = ResponseHelper::toArray($response);
            $statusCode = $response->getStatusCode() ?? null;
            
            $this->logger->info('✅ [MAILTRAP] Response received from API', [
                'status_code' => $statusCode,
                'response_data' => json_encode($result),
                'recipient_email' => $email,
            ]);
            
            // Check if response indicates success (HTTP 200-299 or success flag)
            $isSuccessful = ($statusCode >= 200 && $statusCode < 300) || (isset($result['success']) && $result['success'] === true);
            
            if ($isSuccessful) {
                $this->logger->notice('🎉 [MAILTRAP] Confirmation email sent successfully!', [
                    'email' => $email,
                    'status_code' => $statusCode,
                ]);
            } else {
                $this->logger->error('❌ [MAILTRAP] Unsuccessful response from API', [
                    'status_code' => $statusCode,
                    'email' => $email,
                    'response' => $result,
                ]);
            }
            
            return $isSuccessful;
        } catch (\Throwable $e) {
            $this->logger->error('❌ [MAILTRAP] Exception occurred while sending confirmation email', [
                'exception_type' => get_class($e),
                'exception_message' => $e->getMessage(),
                'exception_code' => $e->getCode(),
                'email' => $email,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString(),
            ]);
            
            // Fallback: Log the confirmation for development
            $this->logger->warning('📝 [DEVELOPMENT FALLBACK] Password reset confirmation logged', [
                'email' => $email,
                'reason' => 'Email sending failed, logging for record',
            ]);
            
            // In development, consider this a success for testing purposes
            $isProduction = getenv('APP_ENV') === 'prod';
            return !$isProduction;
        }
    }

    /**
     * Get HTML template for OTP email
     */
    private function getOtpEmailTemplate(string $email, string $otp): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Code</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-align: center;
            padding: 30px 20px;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .email-body {
            padding: 30px;
        }
        .email-body p {
            margin: 15px 0;
        }
        .otp-box {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
            text-align: center;
        }
        .otp-code {
            font-size: 36px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #667eea;
            word-break: break-all;
            font-family: 'Courier New', monospace;
        }
        .otp-expiry {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
            font-style: italic;
        }
        .email-footer {
            background-color: #f8f9fa;
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #eee;
        }
        .warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>🔐 Password Reset</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>Hello,</p>
            
            <p>We received a request to reset your password for your Eduport account. Below is your verification code:</p>
            
            <!-- OTP Box -->
            <div class="otp-box">
                <div class="otp-code">{$otp}</div>
                <div class="otp-expiry">This code expires in 10 minutes</div>
            </div>

            <p>To complete the password reset process:</p>
            <ol>
                <li>Visit the password reset page on Eduport</li>
                <li>Enter the verification code above</li>
                <li>Create and confirm your new password</li>
            </ol>

            <!-- Warning -->
            <div class="warning">
                <strong>⚠️ Security Notice:</strong> If you didn't request a password reset, please ignore this email or contact our support team immediately.
            </div>

            <p>For security reasons, never share this code with anyone, including Eduport staff.</p>

            <p>Best regards,<br>
            <strong>The Eduport Team</strong></p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>© 2026 Eduport. All rights reserved.</p>
            <p>This is an automated email. Please do not reply directly to this message.</p>
            <p>Account Email: {$email}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get HTML template for password reset confirmation email
     */
    private function getPasswordResetConfirmationTemplate(string $email, string $userName): string
    {
        $name = !empty($userName) ? "Dear {$userName}" : "Hello";
        
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Changed</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: #ffffff;
            text-align: center;
            padding: 30px 20px;
        }
        .email-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .email-body {
            padding: 30px;
        }
        .email-body p {
            margin: 15px 0;
        }
        .success-box {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
            text-align: center;
            color: #155724;
        }
        .email-footer {
            background-color: #f8f9fa;
            text-align: center;
            padding: 20px;
            color: #666;
            font-size: 12px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <h1>✓ Password Changed Successfully</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            <p>{$name},</p>
            
            <p>Your password has been successfully changed.</p>
            
            <!-- Success Box -->
            <div class="success-box">
                <strong>✓ Your account is now secure with your new password</strong>
            </div>

            <p>You can now log in to Eduport using your new password.</p>

            <p>If you did not make this change or if you encounter any issues, please contact our support team immediately.</p>

            <p>Best regards,<br>
            <strong>The Eduport Team</strong></p>
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <p>© 2026 Eduport. All rights reserved.</p>
            <p>This is an automated email. Please do not reply directly to this message.</p>
            <p>Account Email: {$email}</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
