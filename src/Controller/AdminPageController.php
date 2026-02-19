<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminPageController extends AbstractController
{
    #[Route('', name: 'app_admin_page')]
    public function index(): Response
    {
        return $this->render('admin_page/index.html.twig', [
            'controller_name' => 'AdminPageController',
        ]);
    }


    
}
