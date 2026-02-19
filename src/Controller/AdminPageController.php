<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminPageController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_page')]
    public function index(): Response
    {
        return $this->render('admin_page/index.html.twig', [
            'controller_name' => 'AdminPageController',
        ]);
    }


    
}
