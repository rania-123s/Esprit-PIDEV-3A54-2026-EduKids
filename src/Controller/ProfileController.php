<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/edit', name: 'user_profile_edit')]
    public function editProfile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createFormBuilder($user)
            ->add('firstName', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => ['class' => 'form-control']
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Update Profile',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($user);
                $em->flush();

                $this->logger->info('User profile updated', [
                    'userId' => $user->getId(),
                    'email' => $user->getEmail(),
                ]);

                $this->addFlash('success', 'Your profile has been updated successfully!');
                return $this->redirectToRoute('user_profile_edit');
            } catch (\Exception $e) {
                $this->logger->error('Failed to update profile', [
                    'userId' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);

                $this->addFlash('error', 'An error occurred while updating your profile. Please try again.');
            }
        }

        // Render different template based on role
        $template = $this->isGranted('ROLE_ADMIN') 
            ? 'admin/profile/edit.html.twig' 
            : 'profile/edit.html.twig';

        return $this->render($template, [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/settings', name: 'user_profile_settings')]
    public function accountSettings(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        // Render different template based on role
        $template = $this->isGranted('ROLE_ADMIN') 
            ? 'admin/profile/settings.html.twig' 
            : 'profile/settings.html.twig';

        return $this->render($template, [
            'user' => $user,
        ]);
    }

    #[Route('/help', name: 'user_profile_help')]
    public function help(): Response
    {
        // Render different template based on role
        $template = $this->isGranted('ROLE_ADMIN') 
            ? 'admin/profile/help.html.twig' 
            : 'profile/help.html.twig';

        return $this->render($template);
    }
}
