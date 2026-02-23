<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\AdminUserFormType;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'admin_user_index')]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $searchQuery = $request->query->get('q', '');
        $roleFilter = $request->query->get('role', '');
        $sortBy = $request->query->get('sort', 'id');
        $sortOrder = $request->query->get('order', 'DESC');

        $users = $userRepository->searchUsers(
            $searchQuery ?: null, 
            $roleFilter ?: null,
            $sortBy,
            $sortOrder
        );

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'searchQuery' => $searchQuery,
            'roleFilter' => $roleFilter,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
        ]);
    }

    #[Route('/new', name: 'admin_user_new')]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(AdminUserFormType::class, $user, ['is_create' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'User created successfully.');
                return $this->redirectToRoute('admin_user_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on admin create', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/users/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'admin_user_show', requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        return $this->render('admin/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AdminUserFormType::class, $user, ['is_create' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'User updated successfully.');
                return $this->redirectToRoute('admin_user_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on admin edit', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/users/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/block', name: 'admin_user_block', requirements: ['id' => '\d+'])]
    public function block(User $user, EntityManagerInterface $em): Response
    {
        $user->setIsActive(!$user->isActive());
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', $user->isActive() ? 'User unblocked.' : 'User blocked.');
        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            // Prevent deleting yourself
            if ($user === $this->getUser()) {
                $this->addFlash('error', 'You cannot delete your own account from here.');
                return $this->redirectToRoute('admin_user_index');
            }

            $em->remove($user);
            $em->flush();

            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('admin_user_index');
    }
}
