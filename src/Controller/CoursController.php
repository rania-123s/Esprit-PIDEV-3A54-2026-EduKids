<?php

namespace App\Controller;

use App\Entity\Cours;
use App\Form\CoursType;
use App\Repository\CoursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cours')]
final class CoursController extends AbstractController
{
    // ================= LIST =================
    #[Route('/', name: 'app_cours_index')]
    public function index(CoursRepository $coursRepository): Response
    {
        return $this->render('cours/index.html.twig', [
            'cours' => $coursRepository->findAll(),
        ]);
    }

    // ================= CREATE =================
    #[Route('/new', name: 'app_cours_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $cours = new Cours();
        $form = $this->createForm(CoursType::class, $cours);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cours);
            $em->flush();

            $this->addFlash('success', 'Cours ajouté avec succès !');
            return $this->redirectToRoute('app_cours_index');
        }

        return $this->render('cours/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // ================= SHOW =================
    #[Route('/{id}', name: 'app_cours_show', requirements: ['id' => '\d+'])]
    public function show(Cours $cours): Response
    {
        return $this->render('cours/show.html.twig', [
            'cours' => $cours,
        ]);
    }

    // ================= EDIT =================
    #[Route('/{id}/edit', name: 'app_cours_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Cours $cours, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CoursType::class, $cours);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Cours modifié avec succès !');
            return $this->redirectToRoute('app_cours_index');
        }

        return $this->render('cours/edit.html.twig', [
            'form' => $form->createView(),
            'cours' => $cours,
        ]);
    }

    // ================= DELETE =================
    #[Route('/{id}/delete', name: 'app_cours_delete', methods: ['POST'])]
    public function delete(Request $request, Cours $cours, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$cours->getId(), $request->request->get('_token'))) {
            $em->remove($cours);
            $em->flush();
            $this->addFlash('success', 'Cours supprimé avec succès !');
        }

        return $this->redirectToRoute('app_cours_index');
    }
}
