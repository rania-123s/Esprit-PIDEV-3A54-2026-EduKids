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

#[Route('/admin/parents')]
#[IsGranted('ROLE_ADMIN')]
class AdminParentsController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'admin_parents_index')]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $searchQuery = $request->query->get('q', '');
        $sortBy = $request->query->get('sort', 'id');
        $sortOrder = $request->query->get('order', 'DESC');

        $parents = $userRepository->findByRole('ROLE_PARENT', $searchQuery ?: null, $sortBy, $sortOrder);

        return $this->render('admin/parents/index.html.twig', [
            'users' => $parents,
            'searchQuery' => $searchQuery,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'userType' => 'Parent',
            'userTypeIcon' => 'bi-people-fill',
            'userTypeBadgeClass' => 'bg-success',
        ]);
    }

    #[Route('/new', name: 'admin_parents_new')]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $user->setRoles(['ROLE_PARENT']);
        
        $form = $this->createForm(AdminUserFormType::class, $user, [
            'is_create' => true,
            'fixed_role' => 'ROLE_PARENT',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $user->setRoles(['ROLE_PARENT']);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Parent created successfully.');
                return $this->redirectToRoute('admin_parents_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on parent create', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/parents/new.html.twig', [
            'form' => $form->createView(),
            'userType' => 'Parent',
        ]);
    }

    #[Route('/{id}', name: 'admin_parents_show', requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        if (!in_array('ROLE_PARENT', $user->getRoles())) {
            throw $this->createNotFoundException('Parent not found.');
        }

        return $this->render('admin/parents/show.html.twig', [
            'user' => $user,
            'userType' => 'Parent',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_parents_edit', requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!in_array('ROLE_PARENT', $user->getRoles())) {
            throw $this->createNotFoundException('Parent not found.');
        }

        $form = $this->createForm(AdminUserFormType::class, $user, [
            'is_create' => false,
            'fixed_role' => 'ROLE_PARENT',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Parent updated successfully.');
                return $this->redirectToRoute('admin_parents_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on parent edit', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/parents/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'userType' => 'Parent',
        ]);
    }

    #[Route('/{id}/block', name: 'admin_parents_block', requirements: ['id' => '\d+'])]
    public function block(User $user, EntityManagerInterface $em): Response
    {
        $user->setIsActive(!$user->isActive());
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', $user->isActive() ? 'Parent unblocked.' : 'Parent blocked.');
        return $this->redirectToRoute('admin_parents_index');
    }

    #[Route('/{id}/delete', name: 'admin_parents_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Parent deleted successfully.');
        }

        return $this->redirectToRoute('admin_parents_index');
    }
}
