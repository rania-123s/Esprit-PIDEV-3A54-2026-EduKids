<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Repository\CoursRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminPageController extends AbstractController
{
    #[Route('', name: 'app_admin_page')]
    public function index(UserRepository $userRepository, CoursRepository $coursRepository): Response
    {
        // Get user statistics
        $totalUsers = $userRepository->countAll();
        $totalStudents = $userRepository->countByRole('ROLE_ELEVE');
        $totalParents = $userRepository->countByRole('ROLE_PARENT');
        $totalAdmins = $userRepository->countByRole('ROLE_ADMIN');
        $activeUsers = $userRepository->countActive();

        // Get recent users
        $recentUsers = $userRepository->findBy([], ['id' => 'DESC'], 5);

        // Get course count (if repository method exists)
        $totalCourses = 0;
        try {
            $totalCourses = $coursRepository->count([]);
        } catch (\Exception $e) {
            // Repository may not have count method or no courses entity
        }

        return $this->render('admin_page/index.html.twig', [
            'totalUsers' => $totalUsers,
            'totalStudents' => $totalStudents,
            'totalParents' => $totalParents,
            'totalAdmins' => $totalAdmins,
            'activeUsers' => $activeUsers,
            'totalCourses' => $totalCourses,
            'recentUsers' => $recentUsers,
        ]);
    }
}
