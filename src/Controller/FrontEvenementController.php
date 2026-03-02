<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenements')]
class FrontEvenementController extends AbstractController
{
    #[Route('/', name: 'app_front_evenement_index', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        return $this->render('front_evenement/index.html.twig', [
            'evenements' => $evenementRepository->findAll(),
        ]);
    }

    #[Route('/{id}', name: 'app_front_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('front_evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }
}
