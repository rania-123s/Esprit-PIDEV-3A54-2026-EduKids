<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegisterFormType;
use App\Repository\UserRepository;
use App\Service\MailtrapEmailService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, MailtrapEmailService $mailtrapEmailService): Response
    {
        $user = new User();
        $form = $this->createForm(RegisterFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Get the selected role from the form
                $selectedRole = $form->get('roles')->getData();
                
                // Set the role(s) as an array
                $user->setRoles([$selectedRole]);

                // encode the plain password
                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );

                // User is NOT verified yet
                $user->setIsVerified(false);

                $entityManager->persist($user);
                $entityManager->flush();

                $this->logger->info('User registered successfully', [
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ]);

                // Generate verification token and URL
                $token = bin2hex(random_bytes(32));
                $request->getSession()->set('verification_token_' . $user->getId(), $token);
                $request->getSession()->set('verification_token_expiry_' . $user->getId(), time() + 86400); // 24 hours

                $verificationUrl = $this->generateUrl('app_verify_email', [
                    'id' => $user->getId(),
                    'token' => $token,
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                // Send verification email
                $mailtrapEmailService->sendVerificationEmail(
                    $user->getEmail(),
                    $verificationUrl,
                    $user->getFirstName() . ' ' . $user->getLastName()
                );

                $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');
                return $this->redirectToRoute('app_login');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Registration failed: Email already exists', [
                    'email' => $user->getEmail(),
                    'exception' => $e->getMessage(),
                ]);

                $form->get('email')->addError(
                    new FormError('This email address is already registered. Please try another email or sign in.')
                );
            } catch (\Exception $e) {
                $this->logger->error('Registration failed: Unexpected error', [
                    'email' => $user->getEmail(),
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                $this->addFlash('error', 'An error occurred during registration. Please try again.');
            }
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify-email/{id}/{token}', name: 'app_verify_email')]
    public function verifyEmail(int $id, string $token, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $user = $userRepository->find($id);

        if (!$user) {
            $this->addFlash('error', 'Invalid verification link.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('info', 'Your email is already verified. You can log in.');
            return $this->redirectToRoute('app_login');
        }

        // Validate token from session
        $storedToken = $request->getSession()->get('verification_token_' . $user->getId());
        $expiry = $request->getSession()->get('verification_token_expiry_' . $user->getId());

        if (!$storedToken || $storedToken !== $token) {
            $this->addFlash('error', 'Invalid or expired verification link. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        if ($expiry && time() > $expiry) {
            $this->addFlash('error', 'Verification link has expired. Please register again.');
            return $this->redirectToRoute('app_register');
        }

        // Mark as verified
        $user->setIsVerified(true);
        $entityManager->flush();

        // Clean up session
        $request->getSession()->remove('verification_token_' . $user->getId());
        $request->getSession()->remove('verification_token_expiry_' . $user->getId());

        $this->logger->info('User email verified', ['email' => $user->getEmail()]);
        $this->addFlash('success', 'Your email has been verified! You can now log in.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}