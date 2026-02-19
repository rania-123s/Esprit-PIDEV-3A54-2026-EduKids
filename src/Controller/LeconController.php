<?php

namespace App\Controller;

use App\Entity\Lecon;
use App\Form\LeconType;
use App\Repository\LeconRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/lecon')]
final class LeconController extends AbstractController
{
    // ================= LIST =================
    #[Route('/', name: 'app_lecon_index')]
    public function index(LeconRepository $leconRepository, Request $request, PaginatorInterface $paginator): Response
    {
        // Backward compatibility: old URLs used ?sort=...
        if ($request->query->has('sort') && !$request->query->has('order')) {
            $params = $request->query->all();
            $params['order'] = $params['sort'];
            unset($params['sort']);

            return $this->redirectToRoute('app_lecon_index', $params);
        }

        $searchQuery = trim((string) $request->query->get('search', ''));
        $sort = $request->query->get('order');

        if ($searchQuery) {
            $queryBuilder = $leconRepository->searchLecons($searchQuery, $sort);
        } else {
            $queryBuilder = $leconRepository->findAllSorted($sort);
        }

        $lecons = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            3
        );

        return $this->render('lecon/index.html.twig', [
            'lecons' => $lecons,
            'searchQuery' => $searchQuery,
            'sort' => $sort,
            'withCoursCount' => $leconRepository->countWithCours(),
            'withoutCoursCount' => $leconRepository->countWithoutCours(),
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
            $uploadedPdf = $form->get('pdf_file')->getData();
            $uploadedVideo = $form->get('video_file')->getData();
            $youtubeUrl = trim((string) $form->get('youtube_url')->getData());
            $lecon->setMediaType('pdf_video');

            if (!$uploadedPdf) {
                $form->get('pdf_file')->addError(new FormError('Ajoutez un fichier PDF'));
            }
            if (!$uploadedVideo) {
                $form->get('video_file')->addError(new FormError('Ajoutez un fichier video'));
            }
            if ($youtubeUrl === '') {
                $form->get('youtube_url')->addError(new FormError('Ajoutez un lien YouTube'));
            }

            $rawAiImageFileName = trim((string) $request->request->get('ai_generated_lesson_image', ''));
            $aiImageFileName = '';
            if ($rawAiImageFileName !== '') {
                $sanitizedAiImageFileName = $this->sanitizeLessonImageFilename($rawAiImageFileName);
                if ($sanitizedAiImageFileName === null || !$this->isCourseImageAvailable($sanitizedAiImageFileName)) {
                    $form->addError(new FormError('Image IA invalide. Regenerer l image puis reessayez.'));
                } else {
                    $aiImageFileName = $sanitizedAiImageFileName;
                }
            }

            if (
                $form->getErrors(true)->count() > 0
            ) {
                return $this->render('lecon/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

            $pdfFileName = md5(uniqid('pdf_', true)).'.'.$uploadedPdf->guessExtension();
            $videoFileName = $uploadedVideo ? md5(uniqid('video_', true)).'.'.$uploadedVideo->guessExtension() : null;

            try {
                $uploadedPdf->move($this->getParameter('media_directory'), $pdfFileName);
                if ($uploadedVideo && $videoFileName) {
                    $uploadedVideo->move($this->getParameter('media_directory'), $videoFileName);
                    $lecon->setVideoUrl('/uploads/media/'.$videoFileName);
                }
                if ($youtubeUrl !== '') {
                    $lecon->setYoutubeUrl($youtubeUrl);
                }
                $lecon->setMediaUrl('/uploads/media/'.$pdfFileName);
                $lecon->setImage($aiImageFileName !== '' ? $aiImageFileName : null);
            } catch (FileException $e) {
                $form->get('video_file')->addError(new FormError('Erreur lors du telechargement des fichiers media'));

                return $this->render('lecon/new.html.twig', [
                    'form' => $form->createView(),
                ]);
            }

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
        $oldVideoUrl = $lecon->getVideoUrl();
        $oldYoutubeUrl = $lecon->getYoutubeUrl();
        $oldImage = $lecon->getImage();
        $oldCours = $lecon->getCours();

        $form = $this->createForm(LeconType::class, $lecon);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedPdf = $form->get('pdf_file')->getData();
            $uploadedVideo = $form->get('video_file')->getData();
            $youtubeUrl = trim((string) $form->get('youtube_url')->getData());
            $mediaUrl = trim((string) $lecon->getMediaUrl());
            $lecon->setMediaType('pdf_video');

            if ($uploadedPdf) {
                $pdfFileName = md5(uniqid('pdf_', true)).'.'.$uploadedPdf->guessExtension();

                try {
                    $uploadedPdf->move($this->getParameter('media_directory'), $pdfFileName);
                    $lecon->setMediaUrl('/uploads/media/'.$pdfFileName);
                } catch (FileException $e) {
                    $form->get('pdf_file')->addError(new FormError('Erreur lors du telechargement du fichier PDF'));
                }
            }

            if ($uploadedVideo) {
                $videoFileName = md5(uniqid('video_', true)).'.'.$uploadedVideo->guessExtension();

                try {
                    $uploadedVideo->move($this->getParameter('media_directory'), $videoFileName);
                    $lecon->setVideoUrl('/uploads/media/'.$videoFileName);
                } catch (FileException $e) {
                    $form->get('video_file')->addError(new FormError('Erreur lors du telechargement du fichier video'));
                }
            }

            if ($youtubeUrl !== '') {
                $lecon->setYoutubeUrl($youtubeUrl);
            }

            if ($mediaUrl === '') {
                $form->get('pdf_file')->addError(new FormError('Le fichier PDF est obligatoire'));
            }

            if (trim((string) $lecon->getVideoUrl()) === '') {
                $form->get('video_file')->addError(new FormError('Le fichier video est obligatoire'));
            }
            if (trim((string) $lecon->getYoutubeUrl()) === '') {
                $form->get('youtube_url')->addError(new FormError('Le lien YouTube est obligatoire'));
            }

            $rawAiImageFileName = trim((string) $request->request->get('ai_generated_lesson_image', ''));
            if ($rawAiImageFileName !== '') {
                $sanitizedAiImageFileName = $this->sanitizeLessonImageFilename($rawAiImageFileName);
                if ($sanitizedAiImageFileName === null || !$this->isCourseImageAvailable($sanitizedAiImageFileName)) {
                    $form->addError(new FormError('Image IA invalide. Regenerer l image puis reessayez.'));
                } else {
                    $lecon->setImage($sanitizedAiImageFileName);
                }
            } else {
                $lecon->setImage($oldImage);
            }

            if (
                $form->getErrors(true)->count() > 0
            ) {
                $lecon->setTitre($oldTitre);
                $lecon->setOrdre($oldOrdre);
                $lecon->setMediaType($oldMediaType);
                $lecon->setMediaUrl($oldMediaUrl);
                $lecon->setVideoUrl($oldVideoUrl);
                $lecon->setYoutubeUrl($oldYoutubeUrl);
                $lecon->setImage($oldImage);
                $lecon->setCours($oldCours);

                return $this->render('lecon/edit.html.twig', [
                    'form' => $form->createView(),
                    'lecon' => $lecon,
                ]);
            }

            $em->flush();

            $this->addFlash('success', 'Leçon modifiée avec succès !');
            return $this->redirectToRoute('app_lecon_index');
        } else if ($form->isSubmitted() && !$form->isValid()) {
            // Form submission failed validation, restore old values to avoid null errors
            $lecon->setTitre($oldTitre);
            $lecon->setOrdre($oldOrdre);
            $lecon->setMediaType($oldMediaType);
            $lecon->setMediaUrl($oldMediaUrl);
            $lecon->setVideoUrl($oldVideoUrl);
            $lecon->setYoutubeUrl($oldYoutubeUrl);
            $lecon->setImage($oldImage);
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

    private function sanitizeLessonImageFilename(string $fileName): ?string
    {
        $trimmedFileName = trim($fileName);
        if ($trimmedFileName === '') {
            return null;
        }

        if ($trimmedFileName !== basename($trimmedFileName)) {
            return null;
        }

        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $trimmedFileName)) {
            return null;
        }

        if (!preg_match('/\.(png|jpg|jpeg|webp|svg)$/i', $trimmedFileName)) {
            return null;
        }

        return $trimmedFileName;
    }

    private function isCourseImageAvailable(string $fileName): bool
    {
        $imagesDirectory = (string) $this->getParameter('images_directory');
        $targetPath = rtrim($imagesDirectory, '/\\') . DIRECTORY_SEPARATOR . 'cours' . DIRECTORY_SEPARATOR . $fileName;

        return is_file($targetPath);
    }
}
