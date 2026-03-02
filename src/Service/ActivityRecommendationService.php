<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ActivityRecommendationService
{
    private HttpClientInterface $httpClient;
    private ?string $groqApiKey;
    private LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ?string $groqApiKey = null
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->groqApiKey = !empty($groqApiKey) ? $groqApiKey : null;
    }

    /**
     * Recommande des activités pour un événement basé sur les informations fournies
     * 
     * @param string $titre Titre de l'événement
     * @param string $description Description de l'événement
     * @param string $heureDebut Heure de début de l'événement (format H:i)
     * @param string $heureFin Heure de fin de l'événement (format H:i)
     * @param string|null $pauseDebut Heure de début de pause (format H:i, optionnel)
     * @param string|null $pauseFin Heure de fin de pause (format H:i, optionnel)
     * @return array ['success' => bool, 'activites' => string|null, 'message' => string]
     */
    public function recommendActivities(
        string $titre,
        string $description,
        string $heureDebut,
        string $heureFin,
        ?string $pauseDebut = null,
        ?string $pauseFin = null
    ): array {
        if (!$this->groqApiKey) {
            $this->logger->warning('ActivityRecommendationService: Groq API Key non configurée');
            return [
                'success' => false,
                'activites' => null,
                'message' => 'Clé API Groq non configurée. Veuillez configurer GROQ_API_KEY dans votre fichier .env.local'
            ];
        }

        try {
            // Calculer la durée disponible (en excluant la pause)
            $dureeDisponible = $this->calculerDureeDisponible($heureDebut, $heureFin, $pauseDebut, $pauseFin);
            
            // Construire le prompt pour Groq
            $prompt = $this->construirePrompt($titre, $description, $heureDebut, $heureFin, $pauseDebut, $pauseFin, $dureeDisponible);
            
            $this->logger->info('ActivityRecommendationService: Appel à Groq API pour générer les activités');
            
            // Appeler Groq API
            $activites = $this->appelerGroqAPI($prompt);
            
            if ($activites) {
                $this->logger->info('ActivityRecommendationService: Activités générées avec succès');
                return [
                    'success' => true,
                    'activites' => $activites,
                    'message' => 'Activités recommandées avec succès.'
                ];
            } else {
                $this->logger->warning('ActivityRecommendationService: Gemini API non disponible, utilisation du fallback');
                
                // Générer des activités basiques en fallback
                $activitesFallback = $this->genererActivitesFallback($titre, $description, $heureDebut, $heureFin, $pauseDebut, $pauseFin);
                
                return [
                    'success' => true,
                    'activites' => $activitesFallback,
                    'message' => 'Activités générées (mode fallback - l\'API Groq n\'est pas disponible pour le moment).'
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('ActivityRecommendationService: Exception lors de la recommandation d\'activités: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'activites' => null,
                'message' => 'Une erreur est survenue lors de la génération des activités: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calcule la durée disponible en excluant la pause
     */
    private function calculerDureeDisponible(
        string $heureDebut,
        string $heureFin,
        ?string $pauseDebut,
        ?string $pauseFin
    ): array {
        $debutMinutes = $this->timeToMinutes($heureDebut);
        $finMinutes = $this->timeToMinutes($heureFin);
        $dureeTotale = $finMinutes - $debutMinutes;
        
        $dureePause = 0;
        if ($pauseDebut && $pauseFin) {
            $pauseDebutMinutes = $this->timeToMinutes($pauseDebut);
            $pauseFinMinutes = $this->timeToMinutes($pauseFin);
            $dureePause = $pauseFinMinutes - $pauseDebutMinutes;
        }
        
        $dureeDisponible = $dureeTotale - $dureePause;
        
        return [
            'totale' => $dureeTotale,
            'pause' => $dureePause,
            'disponible' => max(0, $dureeDisponible),
            'heures' => intdiv($dureeDisponible, 60),
            'minutes' => $dureeDisponible % 60
        ];
    }

    /**
     * Convertit une heure (H:i) en minutes depuis minuit
     */
    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = explode(':', $time);
        return (int)$hours * 60 + (int)$minutes;
    }

    /**
     * Construit le prompt pour Groq
     */
    private function construirePrompt(
        string $titre,
        string $description,
        string $heureDebut,
        string $heureFin,
        ?string $pauseDebut,
        ?string $pauseFin,
        array $dureeDisponible
    ): string {
        $dureeText = '';
        if ($dureeDisponible['heures'] > 0 && $dureeDisponible['minutes'] > 0) {
            $dureeText = "{$dureeDisponible['heures']}h{$dureeDisponible['minutes']}";
        } elseif ($dureeDisponible['heures'] > 0) {
            $dureeText = "{$dureeDisponible['heures']}h";
        } else {
            $dureeText = "{$dureeDisponible['minutes']}min";
        }

        $pauseInfo = '';
        if ($pauseDebut && $pauseFin) {
            $pauseInfo = "\n- Pause prévue : de {$pauseDebut} à {$pauseFin} (durée : " . 
                        intdiv($dureeDisponible['pause'], 60) . "h" . ($dureeDisponible['pause'] % 60) . 
                        "). La pause ne doit PAS être incluse dans les activités.";
        }

        return "Tu es un expert en organisation d'événements éducatifs et ludiques pour enfants et familles.

TÂCHE : Crée un programme d'activités détaillé et structuré pour l'événement suivant.

⚠️ IMPORTANT : Les activités générées DOIVENT être directement liées au titre et à la description de l'événement. 
Si l'événement parle de développement web/programmation (workshop, développement, full stack, web, code, etc.), génère des activités de programmation/coding/développement.
Si l'événement parle d'art/peinture, génère des activités artistiques.
Si l'événement parle de sport, génère des activités sportives.
Ne génère JAMAIS d'activités qui ne correspondent pas au thème de l'événement.

INFORMATIONS DE L'ÉVÉNEMENT :
- Titre : {$titre}
- Description : {$description}
- Horaires : de {$heureDebut} à {$heureFin}
- Durée totale disponible pour les activités : {$dureeText}{$pauseInfo}

RÈGLES IMPORTANTES :
1. Format de réponse OBLIGATOIRE : Liste les activités au format suivant (UNE activité par ligne) :
   - De HH:MM à HH:MM : Nom de l'activité (description brève si nécessaire)
   
   EXEMPLE :
   - De 09:00 à 09:15 : Accueil des participants et présentation de l'événement
   - De 09:15 à 10:00 : Atelier créatif - Peinture sur toile
   - De 10:00 à 10:45 : Jeux éducatifs interactifs
   - De 10:45 à 11:30 : Activité sportive - Parcours d'obstacles
   - De 11:30 à 11:45 : Remise des certificats de participation
   
2. Les activités doivent :
   - Être STRICTEMENT adaptées au titre \"{$titre}\" et à la description \"{$description}\" de l'événement
   - Si l'événement concerne le développement web/programmation (workshop, développement, full stack, web, code, etc.), les activités DOIVENT être liées à la programmation, au coding, au développement web, aux technologies, aux frameworks
   - Si l'événement concerne l'art/peinture, les activités DOIVENT être liées à l'art et à la créativité artistique
   - Si l'événement concerne le sport, les activités DOIVENT être liées au sport et à l'activité physique
   - Respecter l'intervalle horaire ({$heureDebut} - {$heureFin})
   - Ne PAS inclure la pause dans les activités (si pause prévue : {$pauseInfo})
   - Être variées et intéressantes
   - Avoir des durées réalistes (minimum 15 minutes, maximum 1h30)
   - Être progressives (commencer par des activités d'accueil, finir par des activités de clôture)
   - Se succéder sans interruption (l'heure de fin d'une activité = l'heure de début de la suivante)

3. Structure recommandée :
   - Début : Accueil et présentation (10-15 min)
   - Milieu : Activités principales (réparties sur la durée disponible)
   - Fin : Activité de clôture ou remise de prix/certificats (10-15 min)

4. IMPORTANT - RÈGLES STRICTES :
   - Utilise EXACTEMENT le format \"- De HH:MM à HH:MM : Description\"
   - La première activité doit commencer à {$heureDebut} ou très proche
   - La dernière activité doit se terminer à {$heureFin} ou très proche
   - Ne dépasse JAMAIS l'heure de fin ({$heureFin})
   - Ne commence JAMAIS avant l'heure de début ({$heureDebut})
   - Si une pause est prévue ({$pauseDebut} - {$pauseFin}), ne programme AUCUNE activité pendant cette pause
   - Les activités AVANT la pause doivent se terminer AVANT {$pauseDebut}
   - Les activités APRÈS la pause doivent commencer APRÈS {$pauseFin}
   - Les activités doivent se suivre chronologiquement sans chevauchement ni gap (sauf pour la pause)
   - Réponds UNIQUEMENT avec la liste des activités au format demandé, sans introduction ni conclusion
   - Chaque ligne doit commencer par \"- De\" suivi de l'heure de début, puis \"à\" suivi de l'heure de fin, puis \":\" suivi de la description

Génère maintenant le programme d'activités :";
    }

    /**
     * Appelle l'API Groq pour générer les activités
     */
    private function appelerGroqAPI(string $prompt): ?string
    {
        // Modèles Groq disponibles (par ordre de préférence)
        $models = [
            'llama-3.1-70b-versatile',  // Modèle recommandé, très performant
            'llama-3.1-8b-instant',     // Plus rapide
            'mixtral-8x7b-32768',       // Alternative
            'gemma-7b-it'               // Dernière option
        ];
        
        $lastException = null;
        
        foreach ($models as $model) {
            try {
                $url = "https://api.groq.com/openai/v1/chat/completions";
                $this->logger->info("ActivityRecommendationService: Tentative avec Groq model: {$model}");
                
                $response = $this->httpClient->request('POST', $url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => "Bearer {$this->groqApiKey}",
                    ],
                    'json' => [
                        'model' => $model,
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => 'Tu es un expert en organisation d\'événements éducatifs et ludiques. Tu génères des programmes d\'activités structurés et détaillés.'
                            ],
                            [
                                'role' => 'user',
                                'content' => $prompt
                            ]
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 2000,
                    ],
                    'timeout' => 30,
                ]);

                $statusCode = $response->getStatusCode();
                if ($statusCode !== 200) {
                    // Récupérer le contenu de l'erreur pour plus d'informations
                    try {
                        $errorContent = $response->getContent(false);
                        $errorData = json_decode($errorContent, true);
                        $errorMessage = $errorData['error']['message'] ?? $errorContent;
                        $this->logger->error("Groq API ({$model}) returned status code: {$statusCode}");
                        $this->logger->error("Groq API error message: {$errorMessage}");
                    } catch (\Exception $e) {
                        $this->logger->warning("Groq API ({$model}) returned status code: {$statusCode}");
                    }
                    continue;
                }

                $data = $response->toArray();
                
                if (!isset($data['choices'][0]['message']['content'])) {
                    $this->logger->warning("Groq API ({$model}): No content in response");
                    $this->logger->debug("Groq API response structure: " . json_encode($data));
                    continue;
                }
                
                $activites = $data['choices'][0]['message']['content'];
                
                // Nettoyer et formater la réponse
                $activites = trim($activites);
                
                // Supprimer les préfixes comme "Voici", "Programme d'activités", etc.
                $activites = preg_replace('/^(Voici|Programme|Activités|Liste|Voilà|Ci-dessous).*?:\s*/i', '', $activites);
                $activites = trim($activites);
                
                // S'assurer que chaque ligne commence par "- De" ou "-" suivi de "De"
                // Normaliser le format pour garantir la cohérence
                $lines = explode("\n", $activites);
                $formattedLines = [];
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }
                    // Si la ligne ne commence pas par "-", l'ajouter
                    if (!preg_match('/^-\s*De\s+\d{2}:\d{2}\s+à\s+\d{2}:\d{2}\s*:/i', $line)) {
                        // Essayer de détecter si c'est une ligne d'activité
                        if (preg_match('/De\s+\d{2}:\d{2}\s+à\s+\d{2}:\d{2}\s*:/i', $line)) {
                            $line = '- ' . ltrim($line);
                        } elseif (preg_match('/^\d{2}:\d{2}\s*[-–]\s*\d{2}:\d{2}\s*:/', $line)) {
                            // Format alternatif : "09:00 - 09:15 :"
                            $line = preg_replace('/^(\d{2}:\d{2})\s*[-–]\s*(\d{2}:\d{2})\s*:/', '- De $1 à $2 :', $line);
                        }
                    }
                    $formattedLines[] = $line;
                }
                $activites = implode("\n", $formattedLines);
                $activites = trim($activites);
                
                if (!empty($activites)) {
                    $this->logger->info("ActivityRecommendationService: Succès avec Groq model: {$model}");
                    return $activites;
                }
            } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
                $lastException = $e;
                $response = $e->getResponse();
                $errorContent = $response ? $response->getContent(false) : 'No response';
                try {
                    $errorData = json_decode($errorContent, true);
                    $errorMessage = $errorData['error']['message'] ?? $errorContent;
                    $this->logger->error("Groq API ({$model}) client error: {$errorMessage}");
                } catch (\Exception $parseEx) {
                    $this->logger->error("Groq API ({$model}) client error: " . $e->getMessage());
                    $this->logger->error("Groq API error response: {$errorContent}");
                }
                continue;
            } catch (\Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface $e) {
                $lastException = $e;
                $this->logger->error("Groq API ({$model}) server error: " . $e->getMessage());
                continue;
            } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
                $lastException = $e;
                $this->logger->error("Groq API ({$model}) exception: " . $e->getMessage());
                continue;
            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->error("Groq API ({$model}) unexpected error: " . $e->getMessage());
                continue;
            }
        }

        if ($lastException) {
            $this->logger->error('Tous les modèles Groq ont échoué. Dernière erreur: ' . $lastException->getMessage());
        }
        
        return null;
    }

    /**
     * Génère des activités basiques en fallback si l'API Groq n'est pas disponible
     */
    private function genererActivitesFallback(
        string $titre,
        string $description,
        string $heureDebut,
        string $heureFin,
        ?string $pauseDebut,
        ?string $pauseFin
    ): string {
        $activites = [];
        
        // Convertir les heures en minutes
        [$debutH, $debutM] = explode(':', $heureDebut);
        [$finH, $finM] = explode(':', $heureFin);
        $debutMinutes = (int)$debutH * 60 + (int)$debutM;
        $finMinutes = (int)$finH * 60 + (int)$finM;
        
        // Calculer la pause si elle existe
        $pauseDebutMinutes = null;
        $pauseFinMinutes = null;
        if ($pauseDebut && $pauseFin) {
            [$pauseDebutH, $pauseDebutM] = explode(':', $pauseDebut);
            [$pauseFinH, $pauseFinM] = explode(':', $pauseFin);
            $pauseDebutMinutes = (int)$pauseDebutH * 60 + (int)$pauseDebutM;
            $pauseFinMinutes = (int)$pauseFinH * 60 + (int)$pauseFinM;
        }
        
        // Activité d'accueil (15 minutes)
        $activites[] = sprintf(
            "- De %s à %s : Accueil des participants et présentation de l'événement",
            $heureDebut,
            $this->ajouterMinutes($heureDebut, 15)
        );
        
        $currentMinutes = $debutMinutes + 15;
        $activiteNum = 1;
        
        // Activités principales (par tranches de 30-45 minutes)
        while ($currentMinutes < $finMinutes - 15) {
            // Si on est dans la pause, sauter directement après
            if ($pauseDebutMinutes && $pauseFinMinutes && 
                $currentMinutes >= $pauseDebutMinutes && $currentMinutes < $pauseFinMinutes) {
                $currentMinutes = $pauseFinMinutes;
                continue;
            }
            
            // Calculer la durée de l'activité (45 minutes par défaut)
            $dureeActivite = 45;
            
            // Si une pause existe et que l'activité chevaucherait la pause
            if ($pauseDebutMinutes && $pauseFinMinutes && 
                $currentMinutes < $pauseDebutMinutes && 
                $currentMinutes + $dureeActivite > $pauseDebutMinutes) {
                // Terminer l'activité juste avant la pause
                $dureeActivite = $pauseDebutMinutes - $currentMinutes;
            }
            
            // Vérifier qu'on ne dépasse pas la fin de l'événement
            if ($currentMinutes + $dureeActivite > $finMinutes - 15) {
                $dureeActivite = $finMinutes - $currentMinutes - 15;
            }
            
            // Si la durée est trop courte, vérifier si on peut continuer après la pause
            if ($dureeActivite < 15) {
                // Si on est avant la pause et qu'on peut sauter la pause pour continuer
                if ($pauseDebutMinutes && $pauseFinMinutes && 
                    $currentMinutes < $pauseDebutMinutes) {
                    $currentMinutes = $pauseFinMinutes;
                    continue;
                }
                break;
            }
            
            $heureDebutActivite = $this->minutesToTime($currentMinutes);
            $heureFinActivite = $this->minutesToTime($currentMinutes + $dureeActivite);
            
            $activites[] = sprintf(
                "- De %s à %s : Activité %d - %s",
                $heureDebutActivite,
                $heureFinActivite,
                $activiteNum,
                $this->genererNomActivite($titre, $description, $activiteNum)
            );
            
            $currentMinutes += $dureeActivite;
            $activiteNum++;
            
            // Si on vient de terminer juste avant la pause, sauter la pause
            if ($pauseDebutMinutes && $pauseFinMinutes && 
                $currentMinutes >= $pauseDebutMinutes && $currentMinutes < $pauseFinMinutes) {
                $currentMinutes = $pauseFinMinutes;
            }
        }
        
        // Activité de clôture (15 minutes)
        if ($currentMinutes < $finMinutes - 10) {
            $heureCloture = $this->minutesToTime(max($currentMinutes, $finMinutes - 15));
            $activites[] = sprintf(
                "- De %s à %s : Remise des certificats et clôture de l'événement",
                $heureCloture,
                $heureFin
            );
        }
        
        return implode("\n", $activites);
    }
    
    /**
     * Ajoute des minutes à une heure (format H:i)
     */
    private function ajouterMinutes(string $heure, int $minutes): string
    {
        [$h, $m] = explode(':', $heure);
        $totalMinutes = (int)$h * 60 + (int)$m + $minutes;
        return $this->minutesToTime($totalMinutes);
    }
    
    /**
     * Convertit des minutes en format H:i
     */
    private function minutesToTime(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }
    
    /**
     * Génère un nom d'activité basé sur le titre et la description de l'événement
     */
    private function genererNomActivite(string $titre, string $description, int $numero): string
    {
        // Combiner titre et description pour une meilleure analyse
        // Normaliser les accents pour une meilleure détection
        $texteComplet = $this->normaliserTexte($titre . ' ' . $description);
        
        // Liste d'activités par défaut
        $activitesParDefaut = [
            'Atelier créatif et artistique',
            'Activité interactive et ludique',
            'Jeu éducatif et découverte',
            'Atelier pratique',
            'Activité manuelle et créative',
            'Jeu de groupe et coopération',
            'Atelier découverte et exploration',
            'Activité participative'
        ];
        
        // Détecter le thème principal basé sur le titre et la description
        $themes = [
            'sport' => ['sport', 'sportif', 'sportive', 'football', 'basket', 'course', 'athlétisme', 'gymnastique', 'danse sportive'],
            'art' => ['art', 'artistique', 'créatif', 'créative', 'peinture', 'dessin', 'sculpture', 'artisanat', 'bricolage'],
            'science' => ['science', 'scientifique', 'expérience', 'expérimentation', 'laboratoire', 'découverte', 'nature', 'environnement'],
            'musique' => ['musique', 'musical', 'musicale', 'chant', 'chanson', 'instrument', 'orchestre', 'rythme'],
            'théâtre' => ['théâtre', 'théâtral', 'spectacle', 'improvisation', 'drame', 'comédie', 'mime'],
            'cuisine' => ['cuisine', 'culinaire', 'gastronomie', 'recette', 'pâtisserie', 'cuisiner'],
            'lecture' => ['lecture', 'livre', 'histoire', 'conte', 'bibliothèque', 'littérature'],
            'technologie' => [
                'technologie', 'informatique', 'programmation', 'robotique', 'électronique', 'digital', 'numérique',
                'workshop', 'développement', 'développer', 'web', 'full stack', 'fullstack', 'frontend', 'backend',
                'javascript', 'python', 'java', 'php', 'html', 'css', 'react', 'angular', 'vue', 'node', 'api',
                'code', 'coding', 'coder', 'application', 'app', 'site', 'site web', 'logiciel', 'software',
                'développeur', 'développeuse', 'programmeur', 'programmeuse', 'ingénieur', 'ingénierie',
                'framework', 'base de données', 'database', 'sql', 'git', 'github', 'agile', 'scrum'
            ]
        ];
        
        $themeDetecte = null;
        $maxOccurrences = 0;
        
        foreach ($themes as $theme => $motsCles) {
            $occurrences = 0;
            foreach ($motsCles as $motCle) {
                // Normaliser aussi le mot-clé pour la comparaison
                $motCleNormalise = $this->normaliserTexte($motCle);
                if (strpos($texteComplet, $motCleNormalise) !== false) {
                    $occurrences++;
                }
            }
            if ($occurrences > $maxOccurrences) {
                $maxOccurrences = $occurrences;
                $themeDetecte = $theme;
            }
        }
        
        // Générer l'activité selon le thème détecté
        if ($themeDetecte) {
            $activitesParTheme = [
                'sport' => ['Activité sportive et motricité', 'Jeux sportifs et compétition', 'Parcours sportif et défis', 'Atelier sportif et bien-être'],
                'art' => ['Atelier créatif et artistique', 'Activité peinture et dessin', 'Atelier bricolage et création', 'Activité artistique et expression'],
                'science' => ['Atelier scientifique et découverte', 'Expérience scientifique et observation', 'Atelier nature et environnement', 'Découverte scientifique et expérimentation'],
                'musique' => ['Atelier musical et rythme', 'Activité chant et musique', 'Atelier instruments et sons', 'Expression musicale et créative'],
                'théâtre' => ['Atelier théâtre et expression', 'Activité spectacle et jeu', 'Atelier improvisation et créativité', 'Expression théâtrale et communication'],
                'cuisine' => ['Atelier culinaire et recettes', 'Activité cuisine et pâtisserie', 'Atelier gastronomie et découverte', 'Cuisine créative et partage'],
                'lecture' => ['Atelier lecture et contes', 'Activité livres et histoires', 'Atelier littérature et imagination', 'Lecture interactive et partage'],
                'technologie' => [
                    'Atelier développement web et pratique',
                    'Workshop programmation et coding',
                    'Session développement frontend/backend',
                    'Atelier pratique - Création d\'application',
                    'Workshop technologies web et frameworks',
                    'Atelier développement full stack',
                    'Session pratique - Projet web',
                    'Atelier code et développement logiciel',
                    'Workshop bases de données et API',
                    'Atelier outils de développement et Git'
                ]
            ];
            
            $activites = $activitesParTheme[$themeDetecte];
            return $activites[($numero - 1) % count($activites)];
        }
        
        // Si aucun thème spécifique n'est détecté, utiliser les activités par défaut
        return $activitesParDefaut[($numero - 1) % count($activitesParDefaut)];
    }
    
    /**
     * Normalise le texte pour améliorer la détection de thèmes (supprime les accents, convertit en minuscules)
     */
    private function normaliserTexte(string $texte): string
    {
        $texte = strtolower($texte);
        // Remplacer les caractères accentués
        $accents = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n'
        ];
        return strtr($texte, $accents);
    }
}
