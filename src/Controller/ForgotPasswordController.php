<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\MailtrapEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ForgotPasswordController extends AbstractController
{
    private MailtrapEmailService $emailService;
    private string $appEnv;
    private LoggerInterface $logger;

    public function __construct(MailtrapEmailService $emailService, string $appEnv, LoggerInterface $logger)
    {
        $this->emailService = $emailService;
        $this->appEnv = $appEnv;
        $this->logger = $logger;
    }

    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function requestReset(Request $request, UserRepository $userRepository, SessionInterface $session): Response
    {
        $email = '';
        $error = '';

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            $this->logger->info('🔄 Forgot Password: Request received', [
                'email' => $email,
                'timestamp' => date('Y-m-d H:i:s'),
                'ip' => $request->getClientIp(),
            ]);

            // Check if user exists with this email
            $user = $userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                $this->logger->warning('❌ Forgot Password: User not found', ['email' => $email]);
                $error = 'Email not found in our system. Please check and try again.';
            } else {
                // Generate a 6-digit OTP
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                
                $this->logger->info('✅ Forgot Password: User found, generating OTP', [
                    'email' => $email,
                    'user_id' => $user->getId(),
                    'otp_generated' => $otp,
                ]);

                // Store OTP and email in session (expires in 10 minutes)
                $session->set('forgot_password_otp', $otp);
                $session->set('forgot_password_email', $email);
                $session->set('forgot_password_otp_time', time());

                $this->logger->debug('📝 Session data stored', [
                    'session_id' => $session->getId(),
                    'otp_stored' => $otp,
                    'email_stored' => $email,
                ]);

                // Send OTP to email
                $this->logger->info('📧 Attempting to send OTP email...', [
                    'email' => $email,
                    'environment' => $this->appEnv,
                ]);
                
                $emailSent = $this->emailService->sendOtpEmail($email, $otp);

                if (!$emailSent) {
                    $this->logger->error('❌ Failed to send OTP email', [
                        'email' => $email,
                        'otp' => $otp,
                        'environment' => $this->appEnv,
                    ]);
                    $error = 'Failed to send verification code. Please try again later.';
                } else {
                    $this->logger->notice('✅ OTP email sent successfully!', [
                        'email' => $email,
                        'environment' => $this->appEnv,
                    ]);
                    $session->set('forgot_password_otp_sent', true);
                    return $this->redirectToRoute('app_verify_otp');
                }
            }
        }

        return $this->render('security/forgot_password.html.twig', [
            'email' => $email,
            'error' => $error,
        ]);
    }

    #[Route('/verify-otp', name: 'app_verify_otp')]
    public function verifyOtp(Request $request, SessionInterface $session): Response
    {
        // Check if email was verified
        if (!$session->has('forgot_password_email')) {
            return $this->redirectToRoute('app_forgot_password');
        }

        $error = '';
        $email = $session->get('forgot_password_email');
        $debugOtp = $this->appEnv === 'dev' ? $session->get('forgot_password_otp') : null;

        if ($request->isMethod('POST')) {
            $otp = $request->request->get('otp');
            $storedOtp = $session->get('forgot_password_otp');
            $otpTime = $session->get('forgot_password_otp_time');

            // Check if OTP is expired (10 minutes)
            if (time() - $otpTime > 600) {
                $error = 'OTP has expired. Please request a new one.';
                $session->remove('forgot_password_otp');
                $session->remove('forgot_password_email');
                $session->remove('forgot_password_otp_time');
                return $this->redirectToRoute('app_forgot_password');
            }

            if ($otp !== $storedOtp) {
                $error = 'Invalid OTP. Please try again.';
            } else {
                // OTP verified, proceed to password reset
                $session->set('forgot_password_verified', true);
                return $this->redirectToRoute('app_reset_password');
            }
        }

        return $this->render('security/verify_otp.html.twig', [
            'email' => $email,
            'error' => $error,
            'debug_otp' => $debugOtp,
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function resetPassword(Request $request, SessionInterface $session, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        // Check if OTP was verified
        if (!$session->has('forgot_password_verified') || !$session->get('forgot_password_verified')) {
            return $this->redirectToRoute('app_forgot_password');
        }

        $error = '';
        $email = $session->get('forgot_password_email');

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validate passwords
            if (strlen($newPassword) < 8) {
                $error = 'Password must be at least 8 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                // Update user password
                $user = $userRepository->findOneBy(['email' => $email]);

                if ($user) {
                    $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                    $user->setPassword($hashedPassword);
                    $entityManager->flush();

                    // Send confirmation email
                    $userName = $user->getFirstName() . ' ' . $user->getLastName();
                    $this->emailService->sendPasswordResetConfirmation($email, $userName);

                    // Clear session data
                    $session->remove('forgot_password_otp');
                    $session->remove('forgot_password_email');
                    $session->remove('forgot_password_otp_time');
                    $session->remove('forgot_password_verified');

                    // Redirect to login with success message
                    $this->addFlash('success', 'Password reset successful! Please log in with your new password.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'email' => $email,
            'error' => $error,
        ]);
    }
}
