<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Form\EvenementType;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/evenement')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'app_evenement_index', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenementRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_new' => true,
            'current_image' => null,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageUpload = $form->get('imageUpload')->getData();
            if ($imageUpload) {
                $originalFilename = pathinfo($imageUpload->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'event-' . $safeFilename . '-' . uniqid() . '.' . $imageUpload->guessExtension();

                try {
                    $imageUpload->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    $evenement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image.');
                }
            }

            $entityManager->persist($evenement);
            $entityManager->flush();

            $this->addFlash('success', 'L\'événement a été créé avec succès.');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/new.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'])]
    public function show(Evenement $evenement): Response
    {
        return $this->render('evenement/show.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $form = $this->createForm(EvenementType::class, $evenement, [
            'is_new' => false,
            'current_image' => $evenement->getImage(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageUpload = $form->get('imageUpload')->getData();
            if ($imageUpload) {
                $originalFilename = pathinfo($imageUpload->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = 'event-' . $safeFilename . '-' . uniqid() . '.' . $imageUpload->guessExtension();

                try {
                    $imageUpload->move(
                        $this->getParameter('uploads_directory'),
                        $newFilename
                    );
                    
                    // Delete old image if exists
                    $oldImage = $evenement->getImage();
                    if ($oldImage) {
                        $oldImagePath = $this->getParameter('uploads_directory') . '/' . $oldImage;
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    
                    $evenement->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'upload de l\'image.');
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'L\'événement a été modifié avec succès.');

            return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('evenement/edit.html.twig', [
            'evenement' => $evenement,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_evenement_delete', methods: ['POST'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$evenement->getId(), $request->getPayload()->getString('_token'))) {
            // Delete image if exists
            $image = $evenement->getImage();
            if ($image) {
                $imagePath = $this->getParameter('uploads_directory') . '/' . $image;
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $entityManager->remove($evenement);
            $entityManager->flush();

            $this->addFlash('success', 'L\'événement a été supprimé avec succès.');
        }

        return $this->redirectToRoute('app_evenement_index', [], Response::HTTP_SEE_OTHER);
    }
}
