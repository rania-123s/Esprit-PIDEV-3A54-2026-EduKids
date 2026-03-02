<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ImageRecommendationService
{
    private HttpClientInterface $httpClient;
    private ?string $geminiApiKey;
    private ?string $pexelsApiKey;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ?string $geminiApiKey = null,
        ?string $pexelsApiKey = null
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->geminiApiKey = !empty($geminiApiKey) ? $geminiApiKey : null;
        $this->pexelsApiKey = !empty($pexelsApiKey) ? $pexelsApiKey : null;
    }

    /**
     * Recommande une image basée sur le titre et la description de l'événement
     * Retourne un tableau avec 'imageUrl', 'keywords', et 'searchUrl'
     */
    public function recommendImage(string $titre, string $description): array
    {
        // Générer les mots-clés avec Gemini AI
        $keywords = $this->generateKeywords($titre, $description);
        
        // Récupérer une image pertinente (Pexels en priorité, sinon Unsplash)
        $imageUrl = $this->getRelevantImage($keywords);
        
        return [
            'imageUrl' => $imageUrl,
            'keywords' => $keywords,
            'searchUrl' => $this->generateImageSearchUrl($keywords)
        ];
    }

    /**
     * Génère des mots-clés en anglais pour la recherche d'images avec Gemini AI
     */
    private function generateKeywords(string $titre, string $description): array
    {
        if (!$this->geminiApiKey) {
            $this->logger->warning('Gemini API Key non configurée. Utilisation de mots-clés par défaut.');
            return $this->getFallbackKeywords($titre, $description);
        }

        try {
            $prompt = "You are an expert at generating precise image search keywords. Based on the following event title and description, generate 6-10 specific English keywords for finding a highly relevant image. 

IMPORTANT RULES:
- Use English keywords only (image search APIs work best in English)
- Be very specific and descriptive
- Focus on the main subject/activity
- Include context (e.g., 'soccer match' not just 'soccer', 'children learning' not just 'children')
- Avoid generic words like 'event', 'activity', 'meeting'
- Separate keywords with commas only
- NO explanations, NO additional text, ONLY keywords

Title: {$titre}
Description: {$description}

Keywords:";

            // Essayer gemini-1.5-flash (récent), sinon gemini-pro
            $models = ['gemini-1.5-flash', 'gemini-1.5-flash-latest', 'gemini-pro'];
            $lastException = null;
            
            foreach ($models as $model) {
                try {
                    $response = $this->httpClient->request('POST', 
                        "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$this->geminiApiKey}",
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                    ],
                    'timeout' => 15,
                ]
            );

                    $statusCode = $response->getStatusCode();
                    if ($statusCode !== 200) {
                        continue;
                    }

                    $data = $response->toArray();
                    
                    if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                        continue;
                    }
                    
                    $keywordsText = $data['candidates'][0]['content']['parts'][0]['text'];

                    // Nettoyer et parser les mots-clés
                    $keywordsText = preg_replace('/^\d+[\.\)]\s*/', '', $keywordsText);
                    $keywordsArray = array_map(function($keyword) {
                        $keyword = trim($keyword);
                        $keyword = trim($keyword, '.,;:!?()[]{}"\'-');
                        return strtolower($keyword);
                    }, explode(',', $keywordsText));
                    
                    $keywordsArray = array_filter($keywordsArray, function($keyword) {
                        return !empty($keyword) && strlen($keyword) > 2 && !is_numeric($keyword);
                    });

                    $keywords = array_slice(array_values($keywordsArray), 0, 10);
                    
                    if (!empty($keywords)) {
                        return $keywords;
                    }
                } catch (\Exception $e) {
                    $lastException = $e;
                    continue;
                }
            }

            if ($lastException) {
                $this->logger->error('Gemini API: ' . $lastException->getMessage());
            }
            return $this->getFallbackKeywords($titre, $description);
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            $this->logger->error('Gemini API client error: ' . $e->getMessage());
            $response = $e->getResponse();
            $errorContent = $response ? $response->getContent(false) : 'No response';
            $this->logger->error('Gemini API error response: ' . $errorContent);
            return $this->getFallbackKeywords($titre, $description);
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
            $this->logger->error('Gemini API exception: ' . $e->getMessage());
            return $this->getFallbackKeywords($titre, $description);
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error with Gemini API: ' . $e->getMessage());
            return $this->getFallbackKeywords($titre, $description);
        }
    }

    /**
     * Récupère une image pertinente basée sur les mots-clés
     * Utilise Pexels en priorité (images très pertinentes), sinon Picsum en fallback
     */
    private function getRelevantImage(array $keywords): ?string
    {
        if (empty($keywords)) {
            return null;
        }

        // Construire une requête optimisée avec les 4 meilleurs mots-clés en anglais
        $genericWords = ['event', 'activity', 'meeting', 'occasion', 'gathering'];
        $filteredKeywords = array_filter($keywords, function($keyword) use ($genericWords) {
            $keyword = strtolower(trim($keyword));
            return !in_array($keyword, $genericWords) && strlen($keyword) > 2;
        });
        
        $query = implode(' ', array_slice(array_values($filteredKeywords), 0, 4));
        
        if (empty($query)) {
            $query = implode(' ', array_slice($keywords, 0, 3));
        }

        // 1. Essayer Pexels (images très pertinentes, gratuit avec clé API)
        if ($this->pexelsApiKey) {
            try {
                $imageUrl = $this->getImageFromPexels($query);
                if ($imageUrl) {
                    return $imageUrl;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Pexels API: ' . $e->getMessage());
            }
        }

        // 2. Fallback: Picsum avec seed basé sur la requête (image cohérente pour même recherche)
        $seed = abs(crc32($query));
        return "https://picsum.photos/seed/{$seed}/800/600";
    }

    /**
     * Récupère une image depuis Pexels API (images très pertinentes)
     */
    private function getImageFromPexels(string $query): ?string
    {
        if (!$this->pexelsApiKey) {
            return null;
        }

        $response = $this->httpClient->request('GET', 'https://api.pexels.com/v1/search', [
            'query' => [
                'query' => $query,
                'per_page' => 1,
                'orientation' => 'landscape',
                'size' => 'large'
            ],
            'headers' => [
                'Authorization' => $this->pexelsApiKey,
            ],
            'timeout' => 10,
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = $response->toArray();
        
        if (isset($data['photos'][0]['src']['large'])) {
            return $data['photos'][0]['src']['large'];
        } elseif (isset($data['photos'][0]['src']['medium'])) {
            return $data['photos'][0]['src']['medium'];
        }

        return null;
    }

    /**
     * Génère une URL de recherche d'images Unsplash basée sur les mots-clés
     */
    public function generateImageSearchUrl(array $keywords): string
    {
        $query = implode(' ', array_slice($keywords, 0, 4));
        return "https://unsplash.com/s/photos/" . urlencode($query);
    }

    /**
     * Fallback: génère des mots-clés basiques en anglais à partir du titre et de la description
     */
    private function getFallbackKeywords(string $titre, string $description): array
    {
        $text = strtolower($titre . ' ' . $description);
        
        // Mots à exclure (français et anglais)
        $stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'à', 'pour', 'avec', 'sur', 'dans', 
                      'the', 'a', 'an', 'and', 'or', 'to', 'for', 'with', 'on', 'in', 'of'];
        
        // Dictionnaire de traduction français -> anglais
        $translationMap = [
            'tournoi' => 'tournament',
            'football' => 'soccer',
            'amical' => 'friendly',
            'match' => 'match',
            'compétition' => 'competition',
            'sport' => 'sport',
            'activité' => 'activity',
            'activités' => 'activities',
            'enfants' => 'children',
            'enfant' => 'child',
            'jeune' => 'young',
            'jeunes' => 'youth',
            'conférence' => 'conference',
            'atelier' => 'workshop',
            'formation' => 'training',
            'cours' => 'class',
            'équipe' => 'team',
            'équipes' => 'teams',
            'joueur' => 'player',
            'joueurs' => 'players',
            'terrain' => 'field',
            'terrains' => 'fields',
            'prix' => 'prize',
            'remise' => 'award',
            'inscription' => 'registration',
            'équipement' => 'equipment',
            'sportif' => 'sports',
            'éducation' => 'education',
            'numérique' => 'digital',
            'outils' => 'tools',
            'pédagogiques' => 'educational',
            'défis' => 'challenges',
            'applications' => 'applications',
            'parents' => 'parents',
            'fête' => 'celebration',
            'fin' => 'end',
            'année' => 'year',
            'scolaire' => 'school',
            'célébrez' => 'celebrate',
            'spectacles' => 'show',
            'spectacle' => 'performance',
            'jeux' => 'games',
            'animations' => 'entertainment',
            'stands' => 'stalls',
            'restauration' => 'food',
            'tombola' => 'raffle',
            'festif' => 'festive',
            'famille' => 'family',
            'convivialité' => 'togetherness',
            'entrée' => 'entry',
            'gratuite' => 'free',
        ];
        
        // Extraire les mots significatifs
        $words = preg_split('/\s+/', $text);
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim(preg_replace('/[^a-zéèêëàâäôöùûüîïç]/', '', $word));
            if (strlen($word) > 3 && !in_array($word, $stopWords)) {
                // Traduire si possible, sinon garder le mot original
                $translated = $translationMap[$word] ?? $word;
                if (!in_array($translated, $keywords)) {
                    $keywords[] = $translated;
                }
            }
        }
        
        return array_unique(array_slice($keywords, 0, 8));
    }
}
