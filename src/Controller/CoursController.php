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
use Symfony\Component\HttpFoundation\File\Exception\FileException;

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
            $file = $form->get('image')->getData();
            if ($file) {
                $fileName = md5(uniqid()).'.'.$file->guessExtension();
                try{
                    $file->move(
                        $this->getParameter('images_directory').'/cours',
                        $fileName
                    );
                    $cours->setImage($fileName);
                } catch (FileException $e){}
            }
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
    // Store the old image filename before form handling
    $oldImage = $cours->getImage();
    
    $form = $this->createForm(CoursType::class, $cours);
    // Clear the image field before handling request so form validation works properly
    $form->get('image')->setData(null);
    $form->handleRequest($request);
    
    if ($form->isSubmitted() && $form->isValid()) {
        // Get the uploaded file from the form
        $file = $form->get('image')->getData();
        
        if ($file) {
            // A new file was uploaded
            $fileName = md5(uniqid()) . '.' . $file->guessExtension();
            
            try {
                $file->move(
                    $this->getParameter('images_directory') . '/cours',
                    $fileName
                );
                
                // Set the new filename
                $cours->setImage($fileName);
                
                // Optional: Delete the old image file if it exists
                if ($oldImage) {
                    $oldImagePath = $this->getParameter('images_directory') . '/cours/' . $oldImage;
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }
            } catch (FileException $e) {
                // If upload fails, keep the old image
                $cours->setImage($oldImage);
                $this->addFlash('error', 'Erreur lors du téléchargement de l\'image');
            }
        } else {
            // No new file uploaded, keep the old image
            $cours->setImage($oldImage);
        }
        
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