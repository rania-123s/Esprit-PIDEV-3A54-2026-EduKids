<?php

namespace App\Controller;

use App\Entity\Lecon;
use App\Form\LeconType;
use App\Repository\LeconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/lecon')]
final class LeconController extends AbstractController
{
    // ================= LIST =================
    #[Route('/', name: 'app_lecon_index')]
    public function index(LeconRepository $leconRepository): Response
    {
        return $this->render('lecon/index.html.twig', [
            'lecons' => $leconRepository->findAll(),
        ]);
    }

    // ================= CREATE =================
    #[Route('/new', name: 'app_lecon_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $lecon = new Lecon();
        $form = $this->createForm(LeconType::class, $lecon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($lecon);
            $em->flush();

            $this->addFlash('success', 'Leçon ajoutée avec succès !');
            return $this->redirectToRoute('app_lecon_index');
        }

        return $this->render('lecon/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ================= SHOW =================
    #[Route('/{id}', name: 'app_lecon_show', requirements: ['id' => '\d+'])]
    public function show(Lecon $lecon): Response
    {
        return $this->render('lecon/show.html.twig', [
            'lecon' => $lecon,
        ]);
    }

    // ================= EDIT =================
    #[Route('/{id}/edit', name: 'app_lecon_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Lecon $lecon, EntityManagerInterface $em): Response
    {
        // Store the old values before form handling
        $oldTitre = $lecon->getTitre();
        $oldOrdre = $lecon->getOrdre();
        $oldMediaType = $lecon->getMediaType();
        $oldMediaUrl = $lecon->getMediaUrl();
        $oldCours = $lecon->getCours();

        $form = $this->createForm(LeconType::class, $lecon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Leçon modifiée avec succès !');
            return $this->redirectToRoute('app_lecon_index');
        } else if ($form->isSubmitted() && !$form->isValid()) {
            // Form submission failed validation, restore old values to avoid null errors
            $lecon->setTitre($oldTitre);
            $lecon->setOrdre($oldOrdre);
            $lecon->setMediaType($oldMediaType);
            $lecon->setMediaUrl($oldMediaUrl);
            $lecon->setCours($oldCours);
        }

        return $this->render('lecon/edit.html.twig', [
            'form' => $form->createView(),
            'lecon' => $lecon,
        ]);
    }

    // ================= DELETE =================
    #[Route('/{id}/delete', name: 'app_lecon_delete', methods: ['POST'])]
    public function delete(Request $request, Lecon $lecon, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lecon->getId(), $request->request->get('_token'))) {
            $em->remove($lecon);
            $em->flush();
            $this->addFlash('success', 'Leçon supprimée avec succès !');
        }

        return $this->redirectToRoute('app_lecon_index');
    }
}
