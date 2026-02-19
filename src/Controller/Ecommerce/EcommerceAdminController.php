<?php

namespace App\Controller\Ecommerce;

use App\Entity\Ecommerce\CategoryProduit;
use App\Entity\Ecommerce\Commande;
use App\Entity\Ecommerce\Produit;
use App\Entity\Ecommerce\Review;
use App\Form\Ecommerce\CategoryProduitType;
use App\Form\Ecommerce\CommandeStatutType;
use App\Form\Ecommerce\ProduitType;
use App\Repository\Ecommerce\CategoryProduitRepository;
use App\Repository\Ecommerce\CommandeRepository;
use App\Repository\Ecommerce\ProduitRepository;
use App\Repository\Ecommerce\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/admin/ecommerce')]
#[IsGranted('ROLE_ADMIN')]
class EcommerceAdminController extends AbstractController
{
    private const POLLINATIONS_IMAGE_BASE = 'https://gen.pollinations.ai/image';

    public function __construct(
        private ProduitRepository $produitRepository,
        private CategoryProduitRepository $categoryProduitRepository,
        private CommandeRepository $commandeRepository,
        private ReviewRepository $reviewRepository,
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private string $pollinationsApiKey = '',
    ) {
    }

    #[Route('', name: 'ecommerce_admin_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('back_office/ecommerce/index.html.twig');
    }

    // ---------- Catégories CRUD ----------

    #[Route('/categories', name: 'ecommerce_admin_category_index', methods: ['GET'])]
    public function categoryIndex(Request $request): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'nom');
        $order = $request->query->get('order', 'ASC');
        $categories = $this->categoryProduitRepository->searchAndSort($q, $sort, $order);
        return $this->render('back_office/ecommerce/category_index.html.twig', [
            'categories' => $categories,
            'searchQuery' => $q,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/categories/new', name: 'ecommerce_admin_category_new', methods: ['GET', 'POST'])]
    public function categoryNew(Request $request): Response
    {
        $category = new CategoryProduit();
        $form = $this->createForm(CategoryProduitType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categoryProduitRepository->save($category, true);
            $this->addFlash('success', 'Catégorie créée.');
            return $this->redirectToRoute('ecommerce_admin_category_index');
        }

        return $this->render('back_office/ecommerce/category_form.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/categories/{id}/edit', name: 'ecommerce_admin_category_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function categoryEdit(CategoryProduit $category, Request $request): Response
    {
        $form = $this->createForm(CategoryProduitType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->categoryProduitRepository->save($category, true);
            $this->addFlash('success', 'Catégorie mise à jour.');
            return $this->redirectToRoute('ecommerce_admin_category_index');
        }

        return $this->render('back_office/ecommerce/category_form.html.twig', [
            'category' => $category,
            'form' => $form,
        ]);
    }

    #[Route('/categories/{id}/delete', name: 'ecommerce_admin_category_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function categoryDelete(CategoryProduit $category, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_category' . $category->getId(), $request->request->get('_token'))) {
            $this->categoryProduitRepository->remove($category, true);
            $this->addFlash('success', 'Catégorie supprimée.');
        }
        return $this->redirectToRoute('ecommerce_admin_category_index');
    }

    // ---------- Produits CRUD ----------

    #[Route('/produits/generate-description', name: 'ecommerce_admin_produit_generate_description', methods: ['POST'])]
    public function generateDescription(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('generate_description', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token invalide.'], 403);
        }
        $title = $request->request->get('title') ?? $request->query->get('title') ?? '';
        $title = trim((string) $title);
        if ('' === $title) {
            return new JsonResponse(['description' => '', 'error' => 'Le titre est requis.'], 400);
        }
        $description = $this->generateDescriptionFromTitle($title);
        return new JsonResponse(['description' => $description]);
    }

    private function generateDescriptionFromTitle(string $title): string
    {
        $intro = "Découvrez « " . $title . " », un produit éducatif conçu pour accompagner votre apprentissage.";
        $body = "Ce contenu de qualité vous permet de progresser à votre rythme. Idéal pour les élèves, les parents et tous les curieux. Parfait pour compléter les cours ou approfondir une thématique.";
        return $intro . "\n\n" . $body;
    }

    /**
     * Returns JSON with a URL that proxies Pollinations-generated image (based on product title).
     * Store that URL in the product imageUrl field.
     */
    #[Route('/produits/generate-image-url', name: 'ecommerce_admin_produit_generate_image_url', methods: ['POST'])]
    public function generateImageUrl(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('generate_image_url', $request->request->get('_token'))) {
            return new JsonResponse(['error' => 'Token invalide.'], 403);
        }
        if ($this->pollinationsApiKey === '') {
            return new JsonResponse(['error' => 'POLLINATIONS_API_KEY non configurée.'], 503);
        }
        $prompt = trim((string) ($request->request->get('prompt') ?? $request->request->get('title') ?? ''));
        if ($prompt === '') {
            return new JsonResponse(['error' => 'Indiquez un nom de produit (prompt).'], 400);
        }
        $proxyUrl = $this->generateUrl('ecommerce_admin_produit_generated_image', ['prompt' => $prompt], true);
        return new JsonResponse(['url' => $proxyUrl]);
    }

    /**
     * Proxies image generation to Pollinations.ai; key is kept server-side.
     */
    #[Route('/produits/generated-image', name: 'ecommerce_admin_produit_generated_image', methods: ['GET'])]
    public function generatedImage(Request $request): Response
    {
        if ($this->pollinationsApiKey === '') {
            return new Response('Image generation not configured.', 503);
        }
        $prompt = $request->query->get('prompt', '');
        $prompt = trim((string) $prompt);
        if ($prompt === '') {
            return new Response('Missing prompt.', 400);
        }
        $imagePrompt = $prompt . ', educational product, clean illustration, professional';
        $encodedPrompt = rawurlencode($imagePrompt);
        $url = self::POLLINATIONS_IMAGE_BASE . '/' . $encodedPrompt . '?model=flux';
        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->pollinationsApiKey,
            ],
            'timeout' => 60,
        ]);
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return new Response('Image generation failed.', $statusCode);
        }
        $headers = $response->getHeaders(false);
        $contentType = $headers['content-type'][0] ?? 'image/png';
        return new StreamedResponse(function () use ($response): void {
            foreach ($this->httpClient->stream($response) as $chunk) {
                echo $chunk->getContent();
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }
        }, 200, ['Content-Type' => $contentType]);
    }

    #[Route('/produits', name: 'ecommerce_admin_produit_index', methods: ['GET'])]
    public function produitIndex(Request $request): Response
    {
        $q = $request->query->get('q');
        $categoryId = $request->query->get('category') ? (int) $request->query->get('category') : null;
        $sort = $request->query->get('sort', 'nom');
        $order = $request->query->get('order', 'ASC');
        $produits = $this->produitRepository->searchAndSort($q, $categoryId, $sort, $order);
        $categories = $this->categoryProduitRepository->searchAndSort(null, 'nom', 'ASC');
        return $this->render('back_office/ecommerce/produit_index.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'searchQuery' => $q,
            'filterCategoryId' => $categoryId,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/produits/new', name: 'ecommerce_admin_produit_new', methods: ['GET', 'POST'])]
    public function produitNew(Request $request): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->produitRepository->save($produit, true);
            $this->addFlash('success', 'Produit créé.');
            return $this->redirectToRoute('ecommerce_admin_produit_index');
        }

        return $this->render('back_office/ecommerce/produit_form.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/produits/{id}/edit', name: 'ecommerce_admin_produit_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function produitEdit(Produit $produit, Request $request): Response
    {
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->produitRepository->save($produit, true);
            $this->addFlash('success', 'Produit mis à jour.');
            return $this->redirectToRoute('ecommerce_admin_produit_index');
        }

        return $this->render('back_office/ecommerce/produit_form.html.twig', [
            'produit' => $produit,
            'form' => $form,
        ]);
    }

    #[Route('/produits/{id}/delete', name: 'ecommerce_admin_produit_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function produitDelete(Produit $produit, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $produit->getId(), $request->request->get('_token'))) {
            $this->produitRepository->remove($produit, true);
            $this->addFlash('success', 'Produit supprimé.');
        }
        return $this->redirectToRoute('ecommerce_admin_produit_index');
    }

    // ---------- Commandes ----------

    #[Route('/commandes', name: 'ecommerce_admin_commande_index', methods: ['GET'])]
    public function commandeIndex(Request $request): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'date');
        $order = $request->query->get('order', 'DESC');
        $commandes = $this->commandeRepository->searchAndSort($q, $sort, $order);
        return $this->render('back_office/ecommerce/commande_index.html.twig', [
            'commandes' => $commandes,
            'searchQuery' => $q,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/commandes/{id}', name: 'ecommerce_admin_commande_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function commandeShow(Commande $commande): Response
    {
        return $this->render('back_office/ecommerce/commande_show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/commandes/{id}/statut', name: 'ecommerce_admin_commande_statut', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function commandeStatut(Commande $commande, Request $request): Response
    {
        $form = $this->createForm(CommandeStatutType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Statut de la commande mis à jour.');
            return $this->redirectToRoute('ecommerce_admin_commande_show', ['id' => $commande->getId()]);
        }

        return $this->render('back_office/ecommerce/commande_statut.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }
    #[Route('/commandes/{id}/confirm', name: 'ecommerce_admin_commande_confirm', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function confirmCommande(Commande $commande, Request $request, MailerInterface $mailer): Response
    {
        if ($this->isCsrfTokenValid('confirm' . $commande->getId(), $request->request->get('_token'))) {
            $commande->setStatut(Commande::STATUT_CONFIRME);
            $this->em->flush();

            try {
                $email = (new Email())
                    ->from('ahmedeslaiti@gmail.com')
                    ->to($commande->getUser()->getEmail())
                    ->subject('Confirmation de votre commande #' . $commande->getId())
                    ->text('Bonjour, votre commande #' . $commande->getId() . ' a été confirmée. Merci de votre confiance !')
                    ->html('<p>Bonjour,</p><p>Votre commande <strong>#' . $commande->getId() . '</strong> a été confirmée.</p><p>Merci de votre confiance !</p>');

                $mailer->send($email);
                $this->addFlash('success', 'Commande confirmée et email envoyé.');
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Commande confirmée mais erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('ecommerce_admin_commande_index');
    }

    #[Route('/commandes/{id}/reject', name: 'ecommerce_admin_commande_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejectCommande(Commande $commande, Request $request, MailerInterface $mailer): Response
    {
        if ($this->isCsrfTokenValid('reject' . $commande->getId(), $request->request->get('_token'))) {
            $commande->setStatut(Commande::STATUT_REJETE);
            $this->em->flush();

            try {
                $email = (new Email())
                    ->from('ahmedeslaiti@gmail.com')
                    ->to($commande->getUser()->getEmail())
                    ->subject('Refus de votre commande #' . $commande->getId())
                    ->text('Bonjour, votre commande #' . $commande->getId() . ' a été rejetée. Veuillez nous contacter pour plus d\'informations.')
                    ->html('<p>Bonjour,</p><p>Votre commande <strong>#' . $commande->getId() . '</strong> a été rejetée.</p><p>Veuillez nous contacter pour plus d\'informations.</p>');

                $mailer->send($email);
                $this->addFlash('success', 'Commande rejetée et email envoyé.');
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Commande rejetée mais erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('ecommerce_admin_commande_index');
    }

    #[Route('/commandes/{id}/delete', name: 'ecommerce_admin_commande_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteCommande(Commande $commande, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete' . $commande->getId(), $request->request->get('_token'))) {
            $this->commandeRepository->remove($commande, true);
            $this->addFlash('success', 'Commande #' . $commande->getId() . ' supprimée.');
        }
        return $this->redirectToRoute('ecommerce_admin_commande_index');
    }

    // ---------- Avis / Reviews ----------

    #[Route('/reviews', name: 'ecommerce_admin_review_index', methods: ['GET'])]
    public function reviewIndex(Request $request): Response
    {
        $status = $request->query->get('status', 'pending');
        $reviews = $status === 'pending'
            ? $this->reviewRepository->findPending()
            : $this->reviewRepository->findAllOrdered(200);
        return $this->render('back_office/ecommerce/review_index.html.twig', [
            'reviews' => $reviews,
            'currentStatus' => $status,
        ]);
    }

    #[Route('/reviews/{id}/approve', name: 'ecommerce_admin_review_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reviewApprove(Review $review, Request $request): Response
    {
        if ($this->isCsrfTokenValid('review_approve' . $review->getId(), $request->request->get('_token'))) {
            $review->setStatus(Review::STATUS_APPROVED);
            $this->em->flush();
            $this->addFlash('success', 'Avis #' . $review->getId() . ' approuvé.');
        }
        return $this->redirectToRoute('ecommerce_admin_review_index');
    }

    #[Route('/reviews/{id}/reject', name: 'ecommerce_admin_review_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reviewReject(Review $review, Request $request): Response
    {
        if ($this->isCsrfTokenValid('review_reject' . $review->getId(), $request->request->get('_token'))) {
            $review->setStatus(Review::STATUS_REJECTED);
            $this->em->flush();
            $this->addFlash('success', 'Avis #' . $review->getId() . ' rejeté.');
        }
        return $this->redirectToRoute('ecommerce_admin_review_index');
    }

    // ---------- Analytics & Export ----------

    #[Route('/analytics', name: 'ecommerce_admin_analytics', methods: ['GET'])]
    public function analytics(Request $request): Response
    {
        $now = new \DateTimeImmutable();
        $thisMonthStart = $now->modify('first day of this month')->setTime(0, 0, 0);
        $lastMonthStart = $now->modify('first day of last month')->setTime(0, 0, 0);
        $lastMonthEnd = $now->modify('last day of last month')->setTime(23, 59, 59);

        $paidStatuses = [Commande::STATUT_PAYE, Commande::STATUT_CONFIRME, Commande::STATUT_LIVRE];
        $ordersThisMonth = $this->commandeRepository->countOrdersInPeriod($thisMonthStart, $now, $paidStatuses);
        $ordersLastMonth = $this->commandeRepository->countOrdersInPeriod($lastMonthStart, $lastMonthEnd, $paidStatuses);
        $revenueThisMonth = $this->commandeRepository->sumRevenueInPeriod($thisMonthStart, $now);
        $revenueLastMonth = $this->commandeRepository->sumRevenueInPeriod($lastMonthStart, $lastMonthEnd);

        $ordersByStatus = $this->commandeRepository->countByStatus();
        $topData = $this->commandeRepository->getTopProductsByQuantity(10, null, null);
        $topIds = array_keys($topData);
        $topProduits = [];
        if (!empty($topIds)) {
            $produitsById = [];
            foreach ($this->produitRepository->findBy(['id' => $topIds]) as $p) {
                $produitsById[$p->getId()] = $p;
            }
            foreach ($topIds as $id) {
                $topProduits[] = [
                    'produit' => $produitsById[$id] ?? null,
                    'qty' => $topData[$id]['total_qty'],
                    'revenue' => $topData[$id]['total_revenue'],
                ];
            }
        }

        $salesChart = $this->commandeRepository->getSalesByPeriod('day', 30);

        return $this->render('back_office/ecommerce/analytics.html.twig', [
            'ordersThisMonth' => $ordersThisMonth,
            'ordersLastMonth' => $ordersLastMonth,
            'revenueThisMonth' => $revenueThisMonth,
            'revenueLastMonth' => $revenueLastMonth,
            'ordersByStatus' => $ordersByStatus,
            'topProduits' => $topProduits,
            'salesLabels' => $salesChart['labels'],
            'salesValues' => $salesChart['values'],
        ]);
    }

    #[Route('/export', name: 'ecommerce_admin_export', methods: ['GET'])]
    public function export(Request $request): Response|StreamedResponse
    {
        $format = $request->query->get('format');
        $dateFrom = $request->query->get('date_from') ? new \DateTimeImmutable($request->query->get('date_from') . ' 00:00:00') : null;
        $dateTo = $request->query->get('date_to') ? new \DateTimeImmutable($request->query->get('date_to') . ' 23:59:59') : null;
        $status = $request->query->get('status');
        $statuses = $status !== null && $status !== '' ? [$status] : null;

        if ($format === 'csv') {
            $commandes = $this->commandeRepository->searchAndSort(null, 'date', 'DESC');
            if ($dateFrom || $dateTo || $statuses !== null) {
                $commandes = array_filter($commandes, function (Commande $c) use ($dateFrom, $dateTo, $statuses) {
                    if ($dateFrom && $c->getDate() < $dateFrom) {
                        return false;
                    }
                    if ($dateTo && $c->getDate() > $dateTo) {
                        return false;
                    }
                    if ($statuses !== null && !in_array($c->getStatut(), $statuses, true)) {
                        return false;
                    }
                    return true;
                });
            }
            $response = new StreamedResponse(function () use ($commandes) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['ID', 'Date', 'Client', 'Email', 'Statut', 'Montant (centimes)', 'Montant (€)'], ';');
                foreach ($commandes as $c) {
                    fputcsv($out, [
                        $c->getId(),
                        $c->getDate()?->format('Y-m-d H:i'),
                        $c->getUser() ? $c->getUser()->getFirstName() . ' ' . $c->getUser()->getLastName() : '',
                        $c->getUser()?->getEmail() ?? '',
                        $c->getStatut(),
                        $c->getMontantTotal(),
                        number_format($c->getMontantTotal() / 100, 2, ',', ''),
                    ], ';');
                }
                fclose($out);
            });
            $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'commandes_' . date('Y-m-d') . '.csv'
            ));
            return $response;
        }

        return $this->render('back_office/ecommerce/export.html.twig', [
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
            'status' => $status ?? '',
        ]);
    }
}
