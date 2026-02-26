<?php

namespace App\Controller\Quiz;

use App\Entity\Quiz\Question;
use App\Entity\Quiz\Quiz;
use App\Entity\Quiz\QuizAttempt;
use App\Repository\Quiz\QuestionRepository;
use App\Repository\Quiz\QuizRepository;
use App\Repository\Quiz\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/quiz')]
class QuizFrontController extends AbstractController
{
    public function __construct(
        private QuizRepository $quizRepository,
        private QuestionRepository $questionRepository,
        private QuizAttemptRepository $attemptRepository,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'quiz_front_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $q = $request->query->get('q');
        $sort = $request->query->get('sort', 'titre');
        $order = $request->query->get('order', 'ASC');
        $quizzes = $this->quizRepository->findPublished($q, $sort, $order);

        return $this->render('quiz/list.html.twig', [
            'quizzes' => $quizzes,
            'searchQuery' => $q,
            'sort' => $sort,
            'order' => $order,
        ]);
    }

    #[Route('/{id}', name: 'quiz_front_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Quiz $quiz): Response
    {
        if (!$quiz->isPublished()) {
            throw $this->createNotFoundException('Quiz non disponible.');
        }
        $questions = $this->questionRepository->findByQuizOrdered($quiz);
        return $this->render('quiz/show.html.twig', [
            'quiz' => $quiz,
            'questions' => $questions,
        ]);
    }

    #[Route('/{id}/play', name: 'quiz_front_play', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function play(Quiz $quiz): Response
    {
        if (!$quiz->isPublished()) {
            throw $this->createNotFoundException('Quiz non disponible.');
        }
        $questions = $this->questionRepository->findByQuizOrdered($quiz);
        if (empty($questions)) {
            $this->addFlash('warning', 'Ce quiz n\'a pas encore de questions.');
            return $this->redirectToRoute('quiz_front_show', ['id' => $quiz->getId()]);
        }
        return $this->render('quiz/play.html.twig', [
            'quiz' => $quiz,
            'questions' => $questions,
        ]);
    }

    /**
     * Chatbot for quiz help: only available when quiz has chatbot enabled.
     */
    #[Route('/{id}/chat', name: 'quiz_front_chat', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function chat(Quiz $quiz, Request $request): JsonResponse
    {
        if (!$quiz->isPublished() || !$quiz->isChatbotEnabled()) {
            return new JsonResponse(['reply' => 'L\'assistant n\'est pas disponible pour ce quiz.'], 403);
        }
        if (!$this->isCsrfTokenValid('quiz_chat_' . $quiz->getId(), $request->request->get('_token'))) {
            return new JsonResponse(['reply' => 'Session expirée. Rechargez la page.'], 403);
        }
        $message = trim((string) ($request->request->get('message') ?? ''));
        $reply = $this->getQuizChatbotReply($quiz, $message);
        return new JsonResponse(['reply' => $reply]);
    }

    private function getQuizChatbotReply(Quiz $quiz, string $message): string
    {
        $m = mb_strtolower($message);
        $titre = $quiz->getTitre() ?? '';
        if ($message === '') {
            return 'Posez-moi une question sur le quiz « ' . $titre . ' ». Je peux vous donner des indices ou des précisions sur le thème (sans donner les réponses directes).';
        }
        if (str_contains($m, 'aide') || str_contains($m, 'help') || str_contains($m, 'indice') || str_contains($m, 'hint')) {
            return 'Je peux vous aider à réfléchir au thème du quiz : « ' . $titre . ' ». Relisez bien chaque question et les options. Pour les QCM, éliminez les réponses manifestement fausses. Bonne chance !';
        }
        if (str_contains($m, 'bonjour') || str_contains($m, 'salut') || str_contains($m, 'hello')) {
            return 'Bonjour ! Je suis l\'assistant du quiz « ' . $titre . ' ». Posez-moi une question si vous avez besoin d\'un indice ou d\'une précision.';
        }
        if (str_contains($m, 'merci')) {
            return 'Avec plaisir ! Continuez le quiz, vous y êtes presque.';
        }
        if (str_contains($m, 'réponse') && (str_contains($m, 'quelle') || str_contains($m, 'donne') || str_contains($m, 'correct'))) {
            return 'Je ne peux pas donner les réponses directement. Je peux seulement vous aider à réfléchir au thème du quiz. Relisez la question et les options.';
        }
        // Generic helpful reply using quiz context
        $desc = $quiz->getDescription() ? ' ' . mb_substr(strip_tags($quiz->getDescription()), 0, 200) . '...' : '';
        return 'Pour le quiz « ' . $titre . ' », gardez en tête le thème général.' . $desc . ' Relisez bien l\'énoncé de la question et prenez votre temps pour choisir.';
    }

    #[Route('/{id}/submit', name: 'quiz_front_submit', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submit(Quiz $quiz, Request $request): Response
    {
        if (!$quiz->isPublished()) {
            throw $this->createNotFoundException('Quiz non disponible.');
        }
        if (!$this->isCsrfTokenValid('quiz_submit_' . $quiz->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expirée. Réessayez.');
            return $this->redirectToRoute('quiz_front_play', ['id' => $quiz->getId()]);
        }

        $questions = $this->questionRepository->findByQuizOrdered($quiz);
        $answers = [];
        $score = 0;
        $total = \count($questions);

        foreach ($questions as $question) {
            $key = 'q_' . $question->getId();
            $userAnswer = $request->request->get($key);
            if ($userAnswer !== null && $userAnswer !== '') {
                $userAnswer = trim((string) $userAnswer);
            } else {
                $userAnswer = null;
            }
            $answers[$question->getId()] = $userAnswer;

            if ($question->getType() === Question::TYPE_QCM) {
                $correctIndex = $question->getCorrectOptionIndex();
                if ($userAnswer !== null && $correctIndex !== null && $userAnswer === (string) $correctIndex) {
                    $score++;
                }
            } else {
                $expected = $question->getBonneReponse();
                if ($expected !== null && $userAnswer !== null && mb_strtolower(trim($expected)) === mb_strtolower($userAnswer)) {
                    $score++;
                }
            }
        }

        $user = $this->getUser();
        $attempt = new QuizAttempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);
        $attempt->setScore($score);
        $attempt->setTotalQuestions($total);
        $attempt->setAnswers($answers);
        $attempt->setCompletedAt(new \DateTimeImmutable());
        $this->attemptRepository->save($attempt, true);

        return $this->redirectToRoute('quiz_front_result', ['id' => $quiz->getId(), 'attemptId' => $attempt->getId()]);
    }

    #[Route('/{id}/result/{attemptId}', name: 'quiz_front_result', requirements: ['id' => '\d+', 'attemptId' => '\d+'], methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function result(Quiz $quiz, int $attemptId): Response
    {
        $attempt = $this->attemptRepository->find($attemptId);
        if (!$attempt || $attempt->getQuiz() !== $quiz || $attempt->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException('Tentative non trouvée.');
        }
        $questions = $this->questionRepository->findByQuizOrdered($quiz);
        $corrections = [];
        foreach ($questions as $q) {
            $userAnswer = $attempt->getAnswers()[$q->getId()] ?? null;
            $correct = false;
            if ($q->getType() === Question::TYPE_QCM) {
                $correctIndex = $q->getCorrectOptionIndex();
                $correct = $userAnswer !== null && $correctIndex !== null && $userAnswer === (string) $correctIndex;
            } else {
                $expected = $q->getBonneReponse();
                $correct = $expected !== null && $userAnswer !== null && mb_strtolower(trim($expected)) === mb_strtolower($userAnswer);
            }
            $corrections[] = [
                'question' => $q,
                'userAnswer' => $userAnswer,
                'correct' => $correct,
            ];
        }

        return $this->render('quiz/result.html.twig', [
            'quiz' => $quiz,
            'attempt' => $attempt,
            'corrections' => $corrections,
        ]);
    }
}
