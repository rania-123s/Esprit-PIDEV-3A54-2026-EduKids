<?php

namespace App\Controller\Admin;

use App\Entity\Cours;
use App\Entity\Lecon;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/admin/dashboard', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_cours_index');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('EduKids Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::section('Gestion des cours');
        yield MenuItem::linkToCrud('Cours', 'fas fa-book', Cours::class);
        yield MenuItem::linkToCrud('Lecons', 'fas fa-list', Lecon::class);
        yield MenuItem::section('Navigation');
        yield MenuItem::linkToRoute('Front Office', 'fas fa-globe', 'home');
    }
}
