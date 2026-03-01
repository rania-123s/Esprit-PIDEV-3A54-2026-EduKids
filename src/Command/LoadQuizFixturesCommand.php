<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Quiz\Question;
use App\Entity\Quiz\QuestionOption;
use App\Entity\Quiz\Quiz;
use App\Repository\Quiz\QuizRepository;
use App\Repository\Quiz\QuestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:quiz:load-fixtures',
    description: 'Charge des données de test pour les quiz et questions.',
)]
final class LoadQuizFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuizRepository $quizRepository,
        private readonly QuestionRepository $questionRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('purge', null, InputOption::VALUE_NONE, 'Supprimer tous les quiz existants avant de charger les fixtures.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('purge')) {
            $existing = $this->quizRepository->findAll();
            foreach ($existing as $quiz) {
                $this->em->remove($quiz);
            }
            $this->em->flush();
            $io->note('Anciens quiz supprimés.');
        }

        $data = $this->getFixturesData();
        foreach ($data as $quizData) {
            $quiz = $this->createQuiz($quizData);
            $this->quizRepository->save($quiz, false);
            foreach ($quizData['questions'] as $qData) {
                $question = $this->createQuestion($quiz, $qData);
                $this->questionRepository->save($question, false);
            }
        }
        $this->em->flush();

        $io->success(sprintf('%d quiz et leurs questions ont été créés.', count($data)));
        return Command::SUCCESS;
    }

    private function createQuiz(array $data): Quiz
    {
        $quiz = new Quiz();
        $quiz->setTitre($data['titre']);
        $quiz->setDescription($data['description'] ?? null);
        $quiz->setImageUrl($data['imageUrl'] ?? null);
        $quiz->setPublished($data['published'] ?? false);
        $quiz->setChatbotEnabled($data['chatbotEnabled'] ?? false);
        $quiz->setUpdatedAt(new \DateTimeImmutable());
        return $quiz;
    }

    private function createQuestion(Quiz $quiz, array $data): Question
    {
        $question = new Question();
        $question->setQuiz($quiz);
        $question->setTexte($data['texte']);
        $question->setType($data['type'] ?? Question::TYPE_QCM);
        $question->setOrdre($data['ordre'] ?? 0);
        if (isset($data['bonneReponse'])) {
            $question->setBonneReponse($data['bonneReponse']);
        }
        if (!empty($data['options'])) {
            foreach ($data['options'] as $i => $opt) {
                $option = new QuestionOption();
                $option->setTexte(is_array($opt) ? $opt['texte'] : $opt);
                $option->setOrdre($i);
                $option->setCorrect(is_array($opt) && ($opt['correct'] ?? false));
                $question->addQuestionOption($option);
            }
        }
        return $question;
    }

    /**
     * @return list<array{titre: string, description?: string, imageUrl?: string, published: bool, chatbotEnabled?: bool, questions: list<array{texte: string, type?: string, ordre?: int, bonneReponse?: string, options?: array}>}>
     */
    private function getFixturesData(): array
    {
        return [
            [
                'titre' => 'Mathématiques - Niveau 1',
                'description' => 'Quiz de découverte des nombres et des opérations de base pour les enfants.',
                'published' => true,
                'chatbotEnabled' => true,
                'questions' => [
                    [
                        'texte' => 'Combien font 5 + 3 ?',
                        'type' => Question::TYPE_QCM,
                        'ordre' => 0,
                        'options' => [
                            ['texte' => '6', 'correct' => false],
                            ['texte' => '7', 'correct' => false],
                            ['texte' => '8', 'correct' => true],
                            ['texte' => '9', 'correct' => false],
                        ],
                    ],
                    [
                        'texte' => 'Quel est le double de 4 ?',
                        'type' => Question::TYPE_QCM,
                        'ordre' => 1,
                        'options' => [
                            ['texte' => '6', 'correct' => false],
                            ['texte' => '8', 'correct' => true],
                            ['texte' => '10', 'correct' => false],
                        ],
                    ],
                    [
                        'texte' => 'Écris le résultat de 10 - 4 en chiffres.',
                        'type' => Question::TYPE_TEXTE,
                        'ordre' => 2,
                        'bonneReponse' => '6',
                    ],
                ],
            ],
            [
                'titre' => 'Français - Conjugaison CE1',
                'description' => 'Révision du présent de l\'indicatif pour les verbes du 1er groupe.',
                'published' => true,
                'chatbotEnabled' => false,
                'questions' => [
                    [
                        'texte' => 'Conjugue le verbe "chanter" à la 1re personne du pluriel au présent.',
                        'type' => Question::TYPE_QCM,
                        'ordre' => 0,
                        'options' => [
                            ['texte' => 'nous chantons', 'correct' => true],
                            ['texte' => 'nous chantez', 'correct' => false],
                            ['texte' => 'nous chantent', 'correct' => false],
                        ],
                    ],
                    [
                        'texte' => 'Quelle est la bonne terminaison pour "ils mang..." au présent ?',
                        'type' => Question::TYPE_QCM,
                        'ordre' => 1,
                        'options' => [
                            ['texte' => 'mangent', 'correct' => true],
                            ['texte' => 'mangons', 'correct' => false],
                            ['texte' => 'mangez', 'correct' => false],
                        ],
                    ],
                ],
            ],
            [
                'titre' => 'Sciences - Les animaux',
                'description' => 'Découverte des caractéristiques des animaux (brouillon, non publié).',
                'published' => false,
                'questions' => [
                    [
                        'texte' => 'Quel animal pond des œufs ?',
                        'type' => Question::TYPE_QCM,
                        'ordre' => 0,
                        'options' => [
                            ['texte' => 'Le chat', 'correct' => false],
                            ['texte' => 'La poule', 'correct' => true],
                            ['texte' => 'Le chien', 'correct' => false],
                        ],
                    ],
                    [
                        'texte' => 'Cite un mammifère qui vit dans la mer.',
                        'type' => Question::TYPE_TEXTE,
                        'ordre' => 1,
                        'bonneReponse' => 'dauphin',
                    ],
                ],
            ],
            [
                'titre' => 'Culture générale - ÉduKids',
                'description' => 'Petit quiz pour tester la plateforme (publié, avec assistant).',
                'published' => true,
                'chatbotEnabled' => true,
                'questions' => [
                    [
                        'texte' => 'Quel type de contenu peut-on trouver sur ÉduKids ?',
                        'type' => Question::TYPE_QCM,
                        'ordre' => 0,
                        'options' => [
                            ['texte' => 'Cours et leçons', 'correct' => true],
                            ['texte' => 'Vidéos de cuisine uniquement', 'correct' => false],
                            ['texte' => 'Jeux sans éducation', 'correct' => false],
                        ],
                    ],
                ],
            ],
        ];
    }
}
