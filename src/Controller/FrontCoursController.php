<?php

namespace App\Controller;

use App\Entity\Cours;
use App\Entity\Lecon;
use App\Entity\User;
use App\Entity\UserCoursProgress;
use App\Repository\CoursRepository;
use App\Repository\UserCoursProgressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class FrontCoursController extends AbstractController
{
    private const SESSION_STUDENT_COURSE_IDS = 'student_course_ids';

    #[Route('/', name: 'home')]
    #[Route('/home', name: 'home_alias')]
    public function home(): Response
    {
        return $this->render('home_page/index.html.twig');
    }

    #[Route('/courses', name: 'front_courses')]
    public function courses(
        CoursRepository $repo,
        Request $request,
        PaginatorInterface $paginator,
        UserCoursProgressRepository $userCoursProgressRepository
    ): Response {
        $studentCourseIds = [];
        $user = $this->getUser();

        if ($user instanceof User) {
            $studentCourseIds = $userCoursProgressRepository->findCourseIdsByUser($user);
        } else {
            $studentCourseIds = $this->normalizeCourseIds(
                $request->getSession()->get(self::SESSION_STUDENT_COURSE_IDS, [])
            );
        }

        $cours = $paginator->paginate(
            $repo->findAllWithLeconsQueryBuilder(),
            $request->query->getInt('page', 1),
            6
        );

        return $this->render('front/courses.html.twig', [
            'cours' => $cours,
            'studentCourseIds' => $studentCourseIds,
        ]);
    }

    #[Route('/courses/{id}', name: 'front_cours_show', requirements: ['id' => '\d+'])]
    public function courseShow(Cours $cours): Response
    {
        return $this->render('front/course_show.html.twig', [
            'cours' => $cours,
            'lecons' => $cours->getLecons(),
        ]);
    }

    #[Route('/student/dashboard', name: 'front_student_dashboard')]
    public function studentDashboard(
        ChartBuilderInterface $chartBuilder,
        TranslatorInterface $translator,
        UserCoursProgressRepository $userCoursProgressRepository
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $progressEntries = $userCoursProgressRepository->findByUserOrdered($user);
        $studentCourses = [];

        foreach ($progressEntries as $progressEntry) {
            $course = $progressEntry->getCours();
            if (!$course instanceof Cours) {
                continue;
            }

            $progress = max(0, min(100, $progressEntry->getProgress()));
            $totalLecons = $course->getLecons()->count();
            $completedLessons = $totalLecons > 0
                ? (int) floor(($progress / 100) * $totalLecons)
                : 0;

            $studentCourses[] = [
                'course' => $course,
                'progress' => $progress,
                'totalLecons' => $totalLecons,
                'completedLessons' => $completedLessons,
            ];
        }

        $totalCourses = count($studentCourses);
        $completedCourses = count(array_filter(
            $studentCourses,
            static fn (array $item): bool => $item['progress'] >= 100
        ));
        $completedLessonsTotal = array_sum(array_map(
            static fn (array $item): int => $item['completedLessons'],
            $studentCourses
        ));
        $inProgressCourses = max(0, $totalCourses - $completedCourses);

        $completionChart = null;
        $progressByCourseChart = null;

        if ($totalCourses > 0) {
            $completionChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);
            $completionChart->setData([
                'labels' => [
                    $translator->trans('student.chart.completed'),
                    $translator->trans('student.chart.in_progress'),
                ],
                'datasets' => [[
                    'label' => $translator->trans('student.chart.completion_dataset'),
                    'data' => [$completedCourses, $inProgressCourses],
                    'backgroundColor' => ['#28a745', '#4c64ff'],
                    'borderWidth' => 0,
                ]],
            ]);
            $completionChart->setOptions([
                'plugins' => [
                    'legend' => [
                        'position' => 'bottom',
                    ],
                ],
                'cutout' => '65%',
            ]);

            $courseTitles = array_map(
                static fn (array $item): string => (string) $item['course']->getTitre(),
                $studentCourses
            );
            $courseProgresses = array_map(
                static fn (array $item): int => (int) $item['progress'],
                $studentCourses
            );

            $progressByCourseChart = $chartBuilder->createChart(Chart::TYPE_BAR);
            $progressByCourseChart->setData([
                'labels' => $courseTitles,
                'datasets' => [[
                    'label' => $translator->trans('student.chart.progress_dataset'),
                    'data' => $courseProgresses,
                    'backgroundColor' => 'rgba(76, 100, 255, 0.65)',
                    'borderColor' => '#4c64ff',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ]],
            ]);
            $progressByCourseChart->setOptions([
                'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                ],
                'scales' => [
                    'y' => [
                        'beginAtZero' => true,
                        'max' => 100,
                        'ticks' => [
                            'stepSize' => 20,
                        ],
                    ],
                ],
            ]);
        }

        return $this->render('front/student_dashboard.html.twig', [
            'studentCourses' => $studentCourses,
            'totalCourses' => $totalCourses,
            'completedCourses' => $completedCourses,
            'completedLessonsTotal' => $completedLessonsTotal,
            'completionChart' => $completionChart,
            'progressByCourseChart' => $progressByCourseChart,
        ]);
    }

    #[Route('/parent/dashboard', name: 'front_parent_dashboard')]
    public function parentDashboard(
        Request $request,
        CoursRepository $repo,
        UserCoursProgressRepository $userCoursProgressRepository
    ): Response {
        $user = $this->getUser();
        if ($user instanceof User) {
            $childrenCourses = array_values(array_filter(array_map(
                static fn (UserCoursProgress $progress): ?Cours => $progress->getCours(),
                $userCoursProgressRepository->findByUserOrdered($user)
            )));
        } else {
            $childrenCourseIds = $this->normalizeCourseIds(
                $request->getSession()->get(self::SESSION_STUDENT_COURSE_IDS, [])
            );

            $childrenCourses = $childrenCourseIds === [] ? [] : $repo->findBy(['id' => $childrenCourseIds]);
            $courseOrder = array_flip($childrenCourseIds);

            usort(
                $childrenCourses,
                static fn (Cours $a, Cours $b): int => ($courseOrder[$a->getId()] ?? PHP_INT_MAX) <=> ($courseOrder[$b->getId()] ?? PHP_INT_MAX)
            );
        }

        $childrenLessonsCount = array_sum(array_map(
            static fn (Cours $course): int => $course->getLecons()->count(),
            $childrenCourses
        ));

        return $this->render('front/parent_dashboard.html.twig', [
            'childrenCourses' => $childrenCourses,
            'childrenCoursesCount' => count($childrenCourses),
            'childrenLessonsCount' => $childrenLessonsCount,
            'allCoursesCount' => $repo->count([]),
        ]);
    }

    #[Route('/instructor-dashboard.html', name: 'front_parent_dashboard_legacy', methods: ['GET'])]
    public function parentDashboardLegacy(): Response
    {
        return $this->redirectToRoute('front_parent_dashboard');
    }

    #[Route('/student/courses/{id}/toggle', name: 'front_student_course_toggle', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleStudentCourse(
        Request $request,
        Cours $cours,
        EntityManagerInterface $em,
        UserCoursProgressRepository $userCoursProgressRepository
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('student_course_toggle_' . $cours->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $existingProgress = $userCoursProgressRepository->findOneByUserAndCours($user, $cours);
        $isAddingCourse = $existingProgress === null;

        if ($isAddingCourse) {
            $newProgress = (new UserCoursProgress())
                ->setUser($user)
                ->setCours($cours)
                ->setProgress(0);

            $em->persist($newProgress);
        } else {
            $em->remove($existingProgress);
        }

        $em->flush();

        if ($isAddingCourse) {
            return $this->redirectToRoute('front_student_dashboard');
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('front_student_dashboard'));
    }

    #[Route('/student/courses/{id}/continue', name: 'front_student_course_continue', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function continueStudentCourse(
        Request $request,
        Cours $cours,
        EntityManagerInterface $em,
        UserCoursProgressRepository $userCoursProgressRepository
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('student_course_continue_' . $cours->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $progressEntry = $userCoursProgressRepository->findOneByUserAndCours($user, $cours);
        if (!$progressEntry instanceof UserCoursProgress) {
            $progressEntry = (new UserCoursProgress())
                ->setUser($user)
                ->setCours($cours)
                ->setProgress(0);
            $em->persist($progressEntry);
        }

        $progressEntry->setProgress($progressEntry->getProgress() + 10);
        $em->flush();

        return $this->redirectToRoute('front_student_dashboard');
    }

    #[Route('/courses/{id}/create-own', name: 'front_student_course_create_own', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createOwnCourse(Request $request, Cours $cours, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('student_course_create_' . $cours->getId(), $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $customTitle = trim((string) $request->request->get('custom_title', ''));
        if ($customTitle !== '') {
            $customTitle = mb_substr($customTitle, 0, 255);
        }

        $personalCourse = new Cours();
        $personalCourse->setTitre(
            $customTitle !== '' ? $customTitle : ((string) $cours->getTitre() . ' (Mon cours)')
        );
        $personalCourse->setDescription((string) $cours->getDescription());
        $personalCourse->setNiveau((int) $cours->getNiveau());
        $personalCourse->setMatiere((string) $cours->getMatiere());
        $personalCourse->setImage((string) $cours->getImage());

        $em->persist($personalCourse);

        foreach ($cours->getLecons() as $sourceLecon) {
            $copiedLecon = (new Lecon())
                ->setCours($personalCourse)
                ->setTitre((string) $sourceLecon->getTitre())
                ->setOrdre((int) $sourceLecon->getOrdre())
                ->setMediaType((string) $sourceLecon->getMediaType())
                ->setMediaUrl((string) $sourceLecon->getMediaUrl())
                ->setVideoUrl($sourceLecon->getVideoUrl())
                ->setYoutubeUrl($sourceLecon->getYoutubeUrl())
                ->setImage($sourceLecon->getImage());

            $em->persist($copiedLecon);
        }

        $em->flush();

        $this->addFlash('success', 'Votre cours personnel a ete cree. Vous pouvez maintenant le modifier.');

        return $this->redirectToRoute('app_cours_edit', [
            'id' => $personalCourse->getId(),
        ]);
    }

    private function normalizeCourseIds(mixed $ids): array
    {
        if (!is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $normalized[] = $intId;
            }
        }

        return array_values(array_unique($normalized));
    }
}
