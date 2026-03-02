<?php

namespace App\Controller;

use App\Entity\Ressource;
use App\Form\RessourceType;
use App\Repository\RessourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/ressource')]
class RessourceController extends AbstractController
{
    #[Route('/', name: 'app_ressource_index', methods: ['GET'])]
    public function index(RessourceRepository $ressourceRepository): Response
    {
        return $this->render('ressource/index.html.twig', [
            'ressources' => $ressourceRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_ressource_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $ressource = new Ressource();
        $form = $this->createForm(RessourceType::class, $ressource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $fichierUpload = $form->get('fichierUpload')->getData();
            if ($fichierUpload) {
                $originalFilename = pathinfo($fichierUpload->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$fichierUpload->guessExtension();

                try {
                    $fichierUpload->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $ressource->setFichier($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload du fichier.');
                }
            }

            $entityManager->persist($ressource);
            $entityManager->flush();

            $this->addFlash('success', 'La ressource a été créée avec succès.');

            return $this->redirectToRoute('app_ressource_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ressource/new.html.twig', [
            'ressource' => $ressource,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ressource_show', methods: ['GET'])]
    public function show(Ressource $ressource): Response
    {
        return $this->render('ressource/show.html.twig', [
            'ressource' => $ressource,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_ressource_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Ressource $ressource, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(RessourceType::class, $ressource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle file upload
            $fichierUpload = $form->get('fichierUpload')->getData();
            if ($fichierUpload) {
                $originalFilename = pathinfo($fichierUpload->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$fichierUpload->guessExtension();

                try {
                    $fichierUpload->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    
                    // Delete old file if exists
                    $oldFile = $ressource->getFichier();
                    if ($oldFile) {
                        $oldFilePath = $this->getParameter('uploads_directory').'/'.$oldFile;
                        if (file_exists($oldFilePath)) {
                            unlink($oldFilePath);
                        }
                    }
                    
                    $ressource->setFichier($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload du fichier.');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'La ressource a été modifiée avec succès.');

            return $this->redirectToRoute('app_ressource_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ressource/edit.html.twig', [
            'ressource' => $ressource,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_ressource_delete', methods: ['POST'])]
    public function delete(Request $request, Ressource $ressource, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ressource->getId(), $request->getPayload()->getString('_token'))) {
            // Delete file if exists
            $fichier = $ressource->getFichier();
            if ($fichier) {
                $filePath = $this->getParameter('uploads_directory').'/'.$fichier;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            $entityManager->remove($ressource);
            $entityManager->flush();

            $this->addFlash('success', 'La ressource a été supprimée avec succès.');
        }

        return $this->redirectToRoute('app_ressource_index', [], Response::HTTP_SEE_OTHER);
    }
}
