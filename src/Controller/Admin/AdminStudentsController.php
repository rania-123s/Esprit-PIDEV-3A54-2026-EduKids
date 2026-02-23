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

#[Route('/admin/students')]
#[IsGranted('ROLE_ADMIN')]
class AdminStudentsController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'admin_students_index')]
    public function index(UserRepository $userRepository, Request $request): Response
    {
        $searchQuery = $request->query->get('q', '');
        $sortBy = $request->query->get('sort', 'id');
        $sortOrder = $request->query->get('order', 'DESC');

        $students = $userRepository->findByRole('ROLE_ELEVE', $searchQuery ?: null, $sortBy, $sortOrder);

        return $this->render('admin/students/index.html.twig', [
            'users' => $students,
            'searchQuery' => $searchQuery,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'userType' => 'Student',
            'userTypeIcon' => 'bi-mortarboard-fill',
            'userTypeBadgeClass' => 'bg-info',
        ]);
    }

    #[Route('/new', name: 'admin_students_new')]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $user->setRoles(['ROLE_ELEVE']);
        
        $form = $this->createForm(AdminUserFormType::class, $user, [
            'is_create' => true,
            'fixed_role' => 'ROLE_ELEVE',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $user->setRoles(['ROLE_ELEVE']);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Student created successfully.');
                return $this->redirectToRoute('admin_students_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on student create', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/students/new.html.twig', [
            'form' => $form->createView(),
            'userType' => 'Student',
        ]);
    }

    #[Route('/{id}', name: 'admin_students_show', requirements: ['id' => '\d+'])]
    public function show(User $user): Response
    {
        // Ensure user is a student
        if (!in_array('ROLE_ELEVE', $user->getRoles())) {
            throw $this->createNotFoundException('Student not found.');
        }

        return $this->render('admin/students/show.html.twig', [
            'user' => $user,
            'userType' => 'Student',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_students_edit', requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if (!in_array('ROLE_ELEVE', $user->getRoles())) {
            throw $this->createNotFoundException('Student not found.');
        }

        $form = $this->createForm(AdminUserFormType::class, $user, [
            'is_create' => false,
            'fixed_role' => 'ROLE_ELEVE',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($user);
                $em->flush();
                $this->addFlash('success', 'Student updated successfully.');
                return $this->redirectToRoute('admin_students_index');
            } catch (UniqueConstraintViolationException $e) {
                $this->logger->error('Duplicate email on student edit', ['email' => $user->getEmail()]);
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already used by another account.'));
            }
        }

        return $this->render('admin/students/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'userType' => 'Student',
        ]);
    }

    #[Route('/{id}/block', name: 'admin_students_block', requirements: ['id' => '\d+'])]
    public function block(User $user, EntityManagerInterface $em): Response
    {
        $user->setIsActive(!$user->isActive());
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', $user->isActive() ? 'Student unblocked.' : 'Student blocked.');
        return $this->redirectToRoute('admin_students_index');
    }

    #[Route('/{id}/delete', name: 'admin_students_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, User $user, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Student deleted successfully.');
        }

        return $this->redirectToRoute('admin_students_index');
    }
}
