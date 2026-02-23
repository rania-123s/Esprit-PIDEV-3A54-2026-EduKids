<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints\File;

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
    public function editProfile(Request $request, EntityManagerInterface $em, SluggerInterface $slugger): Response
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
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Photo',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, GIF, or WebP).',
                    ])
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Update Profile',
                'attr' => ['class' => 'btn btn-primary']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle avatar upload
                $avatarFile = $form->get('avatarFile')->getData();
                if ($avatarFile) {
                    $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

                    try {
                        $avatarFile->move(
                            $this->getParameter('kernel.project_dir') . '/public/uploads/avatars',
                            $newFilename
                        );

                        // Delete old avatar if exists
                        $oldAvatar = $user->getAvatar();
                        if ($oldAvatar) {
                            $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars/' . $oldAvatar;
                            if (file_exists($oldPath)) {
                                unlink($oldPath);
                            }
                        }

                        $user->setAvatar($newFilename);
                    } catch (FileException $e) {
                        $this->addFlash('error', 'Failed to upload avatar. Please try again.');
                    }
                }

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

    #[Route('/change-password', name: 'user_change_password')]
    public function changePassword(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            
            // Verify current password
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $this->addFlash('error', 'Your current password is incorrect.');
                return $this->redirectToRoute('user_change_password');
            }

            $newPassword = $form->get('newPassword')->getData();
            $user->setPassword($passwordHasher->hashPassword($user, $newPassword));

            $em->persist($user);
            $em->flush();

            $this->logger->info('User changed password', ['userId' => $user->getId()]);
            $this->addFlash('success', 'Your password has been changed successfully!');
            return $this->redirectToRoute('user_profile_settings');
        }

        $template = $this->isGranted('ROLE_ADMIN')
            ? 'admin/profile/change_password.html.twig'
            : 'profile/change_password.html.twig';

        return $this->render($template, [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/delete-account', name: 'user_delete_account', methods: ['POST'])]
    public function deleteAccount(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete-account', $request->request->get('_token'))) {
            // Invalidate session before deleting
            $request->getSession()->invalidate();
            $this->container->get('security.token_storage')->setToken(null);

            // Delete old avatar if exists
            $oldAvatar = $user->getAvatar();
            if ($oldAvatar) {
                $oldPath = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars/' . $oldAvatar;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $em->remove($user);
            $em->flush();

            $this->logger->info('User deleted their account', ['userId' => $user->getId()]);

            return $this->redirectToRoute('app_login');
        }

        $this->addFlash('error', 'Invalid request. Account was not deleted.');
        return $this->redirectToRoute('user_profile_settings');
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
