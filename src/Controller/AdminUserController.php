<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        // Get search term from query parameter
        $searchQuery = $request->query->get('q', '');
        
        // Search users based on the query
        $users = $userRepository->searchUsers($searchQuery ?: null);

        return $this->render('admin/users/index.html.twig', [
            'users' => $users,
            'searchQuery' => $searchQuery,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit')]
    public function edit(User $user, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createFormBuilder($user)
            ->add('firstName', TextType::class, ['label' => 'First Name'])
            ->add('lastName', TextType::class, ['label' => 'Last Name'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('roles', ChoiceType::class, [
                'label' => 'Role',
                'choices' => [
                    'Student (Eleve)' => 'ROLE_ELEVE',
                    'Parent' => 'ROLE_PARENT',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
            ])
            ->add('save', SubmitType::class, ['label' => 'Save Changes', 'attr' => ['class' => 'btn btn-primary']])
            ->getForm();

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

    #[Route('/{id}/block', name: 'admin_user_block')]
    public function block(User $user, EntityManagerInterface $em): Response
    {
        $user->setIsActive(!$user->isActive());
        $em->persist($user);
        $em->flush();

        $this->addFlash('success', $user->isActive() ? 'User unblocked.' : 'User blocked.');
        return $this->redirectToRoute('admin_user_index');
    }
}
