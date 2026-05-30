<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\FormationEnrollment;
use App\Entity\FormationModule;
use App\Entity\ModuleProgress;
use App\Entity\QuizAttempt;
use App\Entity\QuizAttemptAnswer;
use App\Repository\ModuleProgressRepository;
use App\Repository\QuizAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mon-espace/quiz')]
#[IsGranted('ROLE_USER')]
class QuizAttemptController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuizAttemptRepository $attemptRepository,
        private readonly ModuleProgressRepository $moduleProgressRepository,
    ) {}

    #[Route('/module/{enrollmentId}/{moduleId}', name: 'app_quiz_attempt_show', methods: ['GET'])]
    public function show(int $enrollmentId, int $moduleId): Response
    {
        [$enrollment, $module, $moduleProgress] = $this->resolveContext($enrollmentId, $moduleId);

        $quiz = $module->getQuiz();
        if (!$quiz || !$quiz->isActive()) {
            throw $this->createNotFoundException('Aucun quiz pour ce module.');
        }

        if (!$moduleProgress->isVideoWatched()) {
            $this->addFlash('warning', 'Vous devez terminer la vidéo avant de passer le quiz.');
            return $this->redirectToRoute('app_user_learning_module', [
                'enrollmentId' => $enrollmentId,
                'moduleId' => $moduleId,
            ]);
        }

        $attempts = $this->attemptRepository->findByEnrollmentAndQuiz($enrollment, $quiz);
        $attemptCount = count($attempts);
        $bestAttempt = $this->attemptRepository->findBestAttempt($enrollment, $quiz);
        $canAttempt = $quiz->isUnlimitedAttempts() || $attemptCount < $quiz->getMaxAttempts();

        return $this->render('user_learning/quiz.html.twig', [
            'enrollment' => $enrollment,
            'module' => $module,
            'quiz' => $quiz,
            'moduleProgress' => $moduleProgress,
            'attempts' => $attempts,
            'attemptCount' => $attemptCount,
            'bestAttempt' => $bestAttempt,
            'canAttempt' => $canAttempt,
        ]);
    }

    #[Route('/module/{enrollmentId}/{moduleId}/submit', name: 'app_quiz_attempt_submit', methods: ['POST'])]
    public function submit(int $enrollmentId, int $moduleId, Request $request): JsonResponse
    {
        [$enrollment, $module, $moduleProgress] = $this->resolveContext($enrollmentId, $moduleId);

        $quiz = $module->getQuiz();
        if (!$quiz || !$quiz->isActive()) {
            return new JsonResponse(['error' => 'Quiz introuvable'], 404);
        }

        if (!$moduleProgress->isVideoWatched()) {
            return new JsonResponse(['error' => 'Vidéo non terminée'], 403);
        }

        // Vérifier le nombre de tentatives
        $attemptCount = $this->attemptRepository->countByEnrollmentAndQuiz($enrollment, $quiz);
        if (!$quiz->isUnlimitedAttempts() && $attemptCount >= $quiz->getMaxAttempts()) {
            return new JsonResponse(['error' => 'Nombre de tentatives maximum atteint'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $answers = $data['answers'] ?? [];

        // Créer la tentative
        $attempt = new QuizAttempt();
        $attempt->setEnrollment($enrollment);
        $attempt->setQuiz($quiz);
        $attempt->setAttemptNumber($attemptCount + 1);

        // Enregistrer les réponses
        foreach ($quiz->getQuestions() as $question) {
            $selectedIds = array_map('intval', $answers[(string) $question->getId()] ?? []);

            $attemptAnswer = new QuizAttemptAnswer();
            $attemptAnswer->setQuestion($question);
            $attemptAnswer->setSelectedAnswerIds($selectedIds);
            $attemptAnswer->evaluateCorrectness();

            $attempt->addAttemptAnswer($attemptAnswer);
        }

        // Calculer le score
        $score = $attempt->calculateScore();
        $passed = $score >= $quiz->getPassingScore();

        $attempt->setScore($score);
        $attempt->setPassed($passed);
        $attempt->setCompletedAt(new \DateTime());

        $this->entityManager->persist($attempt);

        // Mettre à jour la progression du module
        $moduleProgress->recordQuizResult($score, $passed);
        $enrollment->updateProgress();

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'score' => $score,
            'passed' => $passed,
            'passingScore' => $quiz->getPassingScore(),
            'attemptId' => $attempt->getId(),
            'redirectUrl' => $this->generateUrl('app_quiz_attempt_result', ['id' => $attempt->getId()]),
        ]);
    }

    #[Route('/result/{id}', name: 'app_quiz_attempt_result', methods: ['GET'])]
    public function result(QuizAttempt $attempt): Response
    {
        $this->denyAccessUnlessGranted('view', $attempt->getEnrollment());

        return $this->render('user_learning/quiz_result.html.twig', [
            'attempt' => $attempt,
            'quiz' => $attempt->getQuiz(),
            'enrollment' => $attempt->getEnrollment(),
            'module' => $attempt->getQuiz()->getModule(),
        ]);
    }

    private function resolveContext(int $enrollmentId, int $moduleId): array
    {
        $enrollment = $this->entityManager->getRepository(FormationEnrollment::class)->find($enrollmentId);
        $module = $this->entityManager->getRepository(FormationModule::class)->find($moduleId);

        if (!$enrollment || !$module || $module->getFormation() !== $enrollment->getFormation()) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted('view', $enrollment);

        $moduleProgress = $this->moduleProgressRepository->findByEnrollmentAndModule($enrollment, $module);
        if (!$moduleProgress) {
            throw $this->createNotFoundException('Progression introuvable.');
        }

        return [$enrollment, $module, $moduleProgress];
    }
}
