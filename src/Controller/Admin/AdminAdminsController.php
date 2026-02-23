<?php

namespace App\Controller\Admin;

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

#[Route('/admin/admins')]
#[IsGranted('ROLE_ADMIN')]
class AdminAdminsController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'admin_admins_index')]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $searchQuery = $request->query->get('q', '');
        $sortBy = $request->query->get('sort', 'id');
        $sortOrder = $request->query->get('order', 'DESC');

        $admins = $userRepository->findByRole('ROLE_ADMIN', $searchQuery ?: null, $sortBy, $sortOrder);

        return $this->render('admin/admins/index.html.twig', [
            'users' => $admins,
            'searchQuery' => $searchQuery,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'userType' => 'Admin',
            'userTypeIcon' => 'bi-shield-fill',
            'userTypeBadgeClass' => 'bg-danger',
        ]);
    }

    #[Route('/new', name: 'admin_admins_new')]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);
        
        $form = $this->createForm(AdminUserFormType::class, $user, [
            'is_create' => true,
            'fixed_role' => 'ROLE_ADMIN',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $user->setRoles(['ROLE_ADMIN']);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Admin created successfully.');
                return $this->redirectToRoute('admin_admins_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on admin create', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/admins/new.html.twig', [
            'form' => $form->createView(),
            'userType' => 'Admin',
        ]);
    }

    #[Route('/{id}', name: 'admin_admins_show', requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createNotFoundException('Admin not found.');
        }

        return $this->render('admin/admins/show.html.twig', [
            'user' => $user,
            'userType' => 'Admin',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_admins_edit', requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createNotFoundException('Admin not found.');
        }

        $form = $this->createForm(AdminUserFormType::class, $user, [
            'is_create' => false,
            'fixed_role' => 'ROLE_ADMIN',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Admin updated successfully.');
                return $this->redirectToRoute('admin_admins_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on admin edit', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/admins/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'userType' => 'Admin',
        ]);
    }

    #[Route('/{id}/block', name: 'admin_admins_block', requirements: ['id' => '\d+'])]
    public function block(User $user, EntityManagerInterface $em): Response
    {
        // Prevent blocking yourself
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'You cannot block your own account.');
            return $this->redirectToRoute('admin_admins_index');
        }

        $user->setIsActive(!$user->isActive());
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', $user->isActive() ? 'Admin unblocked.' : 'Admin blocked.');
        return $this->redirectToRoute('admin_admins_index');
    }

    #[Route('/{id}/delete', name: 'admin_admins_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        // Prevent deleting yourself
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'You cannot delete your own account from here.');
            return $this->redirectToRoute('admin_admins_index');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Admin deleted successfully.');
        }

        return $this->redirectToRoute('admin_admins_index');
    }
}
