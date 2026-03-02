<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegisterFormType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
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
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
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

                $entityManager->persist($user);
                $entityManager->flush();

                $this->logger->info('✅ User registered successfully', [
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                ]);

                // do anything else you need here, like send an email
                return $this->redirectToRoute('app_login');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('❌ Registration failed: Email already exists', [
                    'email' => $user->getEmail(),
                    'exception' => $e->getMessage(),
                ]);

                // Add a user-friendly error message
                $form->get('email')->addError(
                    new FormError('This email address is already registered. Please try another email or sign in.')
                );
            } catch (\Exception $e) {
                $this->logger->error('❌ Registration failed: Unexpected error', [
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

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
