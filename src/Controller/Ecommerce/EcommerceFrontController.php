<?php

namespace App\Controller\Ecommerce;

use App\Entity\Ecommerce\Commande;
use App\Entity\Ecommerce\LigneCommande;
use App\Entity\Ecommerce\Produit;
use App\Entity\Ecommerce\Review;
use App\Form\Ecommerce\ReviewType;
use App\Repository\Ecommerce\CategoryProduitRepository;
use App\Repository\Ecommerce\LigneCommandeRepository;
use App\Repository\Ecommerce\ProduitRepository;
use App\Repository\Ecommerce\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/ecommerce')]
class EcommerceFrontController extends AbstractController
{
    private const SESSION_PANIER = 'ecommerce_panier';

    public function __construct(
        private ProduitRepository $produitRepository,
        private CategoryProduitRepository $categoryProduitRepository,
        private ReviewRepository $reviewRepository,
        private LigneCommandeRepository $ligneCommandeRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Chatbot endpoint: accepts a message and returns a reply.
     */
    #[Route('/chat', name: 'ecommerce_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('ecommerce_chat', $request->request->get('_token'))) {
            return new JsonResponse(['reply' => 'Session expirée. Rechargez la page.'], 403);
        }
        $message = trim((string) ($request->request->get('message') ?? ''));
        $reply = $this->getChatbotReply($message);
        return new JsonResponse(['reply' => $reply]);
    }

    private function getChatbotReply(string $message): string
    {
        $m = mb_strtolower($message);
        if (str_contains($m, 'prix') || str_contains($m, 'tarif') || str_contains($m, 'coût')) {
            return 'Les prix sont indiqués sur chaque fiche produit (en euros). Vous pouvez aussi filtrer par prix min/max dans le catalogue.';
        }
        if (str_contains($m, 'commande') || str_contains($m, 'acheter') || str_contains($m, 'panier')) {
            return 'Ajoutez les produits au panier, puis cliquez sur "Valider la commande". Vous devez être connecté pour finaliser. Le paiement est simulé.';
        }
        if (str_contains($m, 'livraison') || str_contains($m, 'livrer')) {
            return 'Les produits éducatifs sont accessibles après validation de la commande. Pour les contenus numériques, l’accès est immédiat.';
        }
        if (str_contains($m, 'bonjour') || str_contains($m, 'salut') || str_contains($m, 'hello')) {
            return 'Bonjour ! Comment puis-je vous aider ? Vous pouvez me demander des infos sur les prix, la commande ou le panier.';
        }
        if (str_contains($m, 'merci')) {
            return 'Avec plaisir ! N’hésitez pas si vous avez d’autres questions.';
        }
        return 'Merci pour votre message. Pour des questions sur les prix ou la commande, précisez votre demande. Sinon, contactez le support.';
    }

    /**
     * Catalogue with filters (category, price), search and sorting.
     */
    #[Route('', name: 'ecommerce_catalogue', methods: ['GET'])]
    public function catalogue(Request $request): Response
    {
        $categoryId = $request->query->get('category') !== '' && $request->query->get('category') !== null
            ? (int) $request->query->get('category') : null;
        $prixMin = $request->query->get('prix_min') !== '' && $request->query->get('prix_min') !== null
            ? (int) $request->query->get('prix_min') : null;
        $prixMax = $request->query->get('prix_max') !== '' && $request->query->get('prix_max') !== null
            ? (int) $request->query->get('prix_max') : null;
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'nom');
        $order = $request->query->get('order', 'ASC');

        if ($prixMin !== null) {
            $prixMin = $prixMin * 100;
        }
        if ($prixMax !== null) {
            $prixMax = $prixMax * 100;
        }

        $produits = $this->produitRepository->findByFilters($categoryId, $prixMin, $prixMax, $q, $sort, $order);
        $categories = $this->categoryProduitRepository->searchAndSort(null, 'nom', 'ASC');
        $ratingByProduit = $this->reviewRepository->getAverageAndCountByProduits($produits);
        $reviewsByProduit = $this->reviewRepository->findApprovedByProduitIdsGrouped($produits, 5);

        return $this->render('ecommerce/catalogue.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'ratingByProduit' => $ratingByProduit,
            'reviewsByProduit' => $reviewsByProduit,
            'filter_category_id' => $categoryId,
            'filter_prix_min' => $prixMin !== null ? $prixMin / 100 : null,
            'filter_prix_max' => $prixMax !== null ? $prixMax / 100 : null,
            'searchQuery' => $q,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/produit/{id}', name: 'ecommerce_produit_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function produitShow(Produit $produit): Response
    {
        $reviews = $this->reviewRepository->findByProduitApproved($produit);
        $avgRating = $this->reviewRepository->getAverageRatingForProduit($produit);
        $reviewCount = $this->reviewRepository->getCountForProduit($produit);
        $user = $this->getUser();
        $canReview = $user && !$this->reviewRepository->userHasReviewed($user, $produit);
        $reviewerIds = array_map(fn (Review $r) => $r->getUser()->getId(), $reviews);
        $verifiedUserIds = array_fill_keys(
            $this->ligneCommandeRepository->userIdsWhoPurchasedProduct($produit, $reviewerIds),
            true
        );

        $reviewForm = null;
        if ($canReview) {
            $newReview = new Review();
            $newReview->setUser($user);
            $newReview->setProduit($produit);
            $reviewForm = $this->createForm(ReviewType::class, $newReview);
        }

        return $this->render('ecommerce/produit_show.html.twig', [
            'produit' => $produit,
            'reviews' => $reviews,
            'avgRating' => $avgRating,
            'reviewCount' => $reviewCount,
            'canReview' => $canReview,
            'verifiedUserIds' => $verifiedUserIds,
            'reviewForm' => $reviewForm,
        ]);
    }

    #[Route('/produit/{id}/review', name: 'ecommerce_review_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reviewSubmit(Produit $produit, Request $request): Response
    {
        $user = $this->getUser();
        if ($this->reviewRepository->userHasReviewed($user, $produit)) {
            $this->addFlash('error', 'Vous avez déjà laissé un avis sur ce produit.');
            return $this->redirectToRoute('ecommerce_produit_show', ['id' => $produit->getId()]);
        }

        $review = new Review();
        $review->setUser($user);
        $review->setProduit($produit);
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->reviewRepository->save($review, true);
            $this->addFlash('success', 'Merci ! Votre avis a été enregistré et sera visible après modération.');
            return $this->redirectToRoute('ecommerce_produit_show', ['id' => $produit->getId()]);
        }

        foreach ($form->getErrors(true) as $error) {
            $this->addFlash('error', $error->getMessage());
        }
        return $this->redirectToRoute('ecommerce_produit_show', ['id' => $produit->getId()]);
    }

    #[Route('/panier', name: 'ecommerce_panier', methods: ['GET'])]
    public function panier(Request $request): Response
    {
        $panier = $this->getPanierData($request);
        return $this->render('ecommerce/panier.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/panier/ajouter/{id}', name: 'ecommerce_panier_ajouter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function panierAjouter(Produit $produit, Request $request): Response
    {
        if ($this->isCsrfTokenValid('panier_ajouter_' . $produit->getId(), $request->request->get('_token'))) {
            $qte = (int) $request->request->get('quantite', 1);
            if ($qte < 1 || $qte > 9999) {
                $this->addFlash('error', 'La quantité doit être entre 1 et 9999.');
            } else {
                $session = $request->getSession();
                $panier = $session->get(self::SESSION_PANIER, []);
                $id = $produit->getId();
                $panier[$id] = ($panier[$id] ?? 0) + $qte;
                $session->set(self::SESSION_PANIER, $panier);
                $this->addFlash('success', 'Produit ajouté au panier.');
            }
        }
        $referer = $request->headers->get('referer', $this->generateUrl('ecommerce_catalogue'));
        return $this->redirect($referer);
    }

    #[Route('/panier/retirer/{id}', name: 'ecommerce_panier_retirer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function panierRetirer(Produit $produit, Request $request): Response
    {
        if ($this->isCsrfTokenValid('panier_retirer_' . $produit->getId(), $request->request->get('_token'))) {
            $session = $request->getSession();
            $panier = $session->get(self::SESSION_PANIER, []);
            $id = $produit->getId();
            if (isset($panier[$id])) {
                $qte = (int) $request->request->get('quantite', 1);
                $panier[$id] = max(0, $panier[$id] - $qte);
                if ($panier[$id] <= 0) {
                    unset($panier[$id]);
                }
                $session->set(self::SESSION_PANIER, $panier);
                $this->addFlash('success', 'Article retiré du panier.');
            }
        }
        return $this->redirectToRoute('ecommerce_panier');
    }

    #[Route('/panier/supprimer/{id}', name: 'ecommerce_panier_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function panierSupprimer(Produit $produit, Request $request): Response
    {
        if ($this->isCsrfTokenValid('panier_supprimer_' . $produit->getId(), $request->request->get('_token'))) {
            $session = $request->getSession();
            $panier = $session->get(self::SESSION_PANIER, []);
            unset($panier[$produit->getId()]);
            $session->set(self::SESSION_PANIER, $panier);
            $this->addFlash('success', 'Article supprimé du panier.');
        }
        return $this->redirectToRoute('ecommerce_panier');
    }

    #[Route('/commande/recapitulatif', name: 'ecommerce_commande_recapitulatif', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function commandeRecapitulatif(Request $request): Response
    {
        $panier = $this->getPanierData($request);
        if (empty($panier['items'])) {
            $this->addFlash('warning', 'Votre panier est vide.');
            return $this->redirectToRoute('ecommerce_catalogue');
        }
        return $this->render('ecommerce/commande_recapitulatif.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/commande/valider', name: 'ecommerce_commande_valider', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function commandeValider(Request $request): Response
    {
        $panier = $request->getSession()->get(self::SESSION_PANIER, []);
        if (empty($panier)) {
            $this->addFlash('warning', 'Panier vide.');
            return $this->redirectToRoute('ecommerce_catalogue');
        }
        if (!$this->isCsrfTokenValid('ecommerce_commande_valider', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('ecommerce_panier');
        }

        $user = $this->getUser();
        $commande = new Commande();
        $commande->setUser($user);
        $commande->setDate(new \DateTimeImmutable());
        $commande->setStatut(Commande::STATUT_PAYE);
        $commande->setMontantTotal(0);

        $montantTotal = 0;
        foreach ($panier as $produitId => $quantite) {
            $produit = $this->produitRepository->find($produitId);
            if (!$produit || $quantite <= 0) {
                continue;
            }
            $ligne = new LigneCommande();
            $ligne->setProduit($produit);
            $ligne->setQuantite($quantite);
            $ligne->setPrixUnitaire($produit->getPrix());
            $commande->addLigneCommande($ligne);
            $montantTotal += $produit->getPrix() * $quantite;
        }
        $commande->setMontantTotal($montantTotal);

        $this->em->persist($commande);
        $this->em->flush();

        $request->getSession()->set(self::SESSION_PANIER, []);
        $this->addFlash('success', 'Commande enregistrée. Paiement simulé avec succès.');

        return $this->redirectToRoute('ecommerce_catalogue');
    }

    private function getPanierData(Request $request): array
    {
        $panier = $request->getSession()->get(self::SESSION_PANIER, []);
        $items = [];
        $total = 0;
        foreach ($panier as $produitId => $quantite) {
            if ($quantite <= 0) {
                continue;
            }
            $produit = $this->produitRepository->find($produitId);
            if (!$produit) {
                continue;
            }
            $sousTotal = $produit->getPrix() * $quantite;
            $total += $sousTotal;
            $items[] = [
                'produit' => $produit,
                'quantite' => $quantite,
                'sous_total' => $sousTotal,
            ];
        }
        return [
            'items' => $items,
            'total' => $total,
            'count' => array_sum($panier),
        ];
    }
}
