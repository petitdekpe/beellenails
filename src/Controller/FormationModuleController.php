<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\Formation;
use App\Entity\FormationModule;
use App\Entity\Quiz;
use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use App\Repository\FormationModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/formation/{formationId}/module')]
#[IsGranted('ROLE_ADMIN')]
class FormationModuleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FormationModuleRepository $moduleRepository,
    ) {}

    #[Route('/new', name: 'app_formation_module_new', methods: ['GET', 'POST'])]
    public function new(int $formationId, Request $request): Response
    {
        $formation = $this->entityManager->getRepository(Formation::class)->find($formationId);
        if (!$formation) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $module = new FormationModule();
            $module->setFormation($formation);
            $this->saveModule($module, $request);

            $this->addFlash('success', 'Module ajouté avec succès.');
            return $this->redirectToRoute('app_dashboard_formation_edit', ['id' => $formationId]);
        }

        $nextPosition = $this->moduleRepository->getNextPosition($formation);

        return $this->render('dashboard/module/edit.html.twig', [
            'formation' => $formation,
            'module' => null,
            'nextPosition' => $nextPosition,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_formation_module_edit', methods: ['GET', 'POST'])]
    public function edit(int $formationId, FormationModule $module, Request $request): Response
    {
        if ($module->getFormation()->getId() !== $formationId) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $this->saveModule($module, $request);

            $this->addFlash('success', 'Module mis à jour.');
            return $this->redirectToRoute('app_dashboard_formation_edit', ['id' => $formationId]);
        }

        return $this->render('dashboard/module/edit.html.twig', [
            'formation' => $module->getFormation(),
            'module' => $module,
            'nextPosition' => $module->getPosition(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_formation_module_delete', methods: ['POST'])]
    public function delete(int $formationId, FormationModule $module, Request $request): Response
    {
        if ($module->getFormation()->getId() !== $formationId) {
            throw $this->createNotFoundException();
        }

        if ($this->isCsrfTokenValid('delete_module_' . $module->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($module);
            $this->entityManager->flush();
            $this->addFlash('success', 'Module supprimé.');
        }

        return $this->redirectToRoute('app_dashboard_formation_edit', ['id' => $formationId]);
    }

    private function saveModule(FormationModule $module, Request $request): void
    {
        $data = $request->request->all();

        $module->setTitle($data['title'] ?? '');
        $module->setDescription($data['description'] ?: null);
        $module->setDuration((int) ($data['duration'] ?? 0));
        $module->setPosition((int) ($data['position'] ?? 1));
        $module->setYoutubeUrl($data['youtube_url'] ?: null);
        $module->setIsActive(isset($data['is_active']));

        $this->entityManager->persist($module);
        $this->entityManager->flush(); // flush pour avoir l'ID si nouveau module

        // --- Quiz ---
        $hasQuiz = isset($data['quiz_enabled']);
        $existingQuiz = $module->getQuiz();

        if (!$hasQuiz) {
            if ($existingQuiz) {
                $this->entityManager->remove($existingQuiz);
            }
            $this->entityManager->flush();
            return;
        }

        $quiz = $existingQuiz ?? new Quiz();
        $quiz->setModule($module);
        $quiz->setPassingScore((int) ($data['passing_score'] ?? 70));
        $quiz->setMaxAttempts(
            isset($data['max_attempts']) && $data['max_attempts'] !== '' && $data['max_attempts'] !== '0'
                ? (int) $data['max_attempts']
                : null
        );
        $quiz->setIsActive(isset($data['quiz_active']));
        $quiz->setUpdatedAt(new \DateTime());

        // Supprimer les questions existantes et recréer
        foreach ($quiz->getQuestions() as $q) {
            $quiz->removeQuestion($q);
        }

        $position = 1;
        foreach ($data['questions'] ?? [] as $qData) {
            $questionText = trim($qData['text'] ?? '');
            if ($questionText === '') {
                continue;
            }

            $question = new QuizQuestion();
            $question->setQuestionText($questionText);
            $question->setType($qData['type'] ?? 'single');
            $question->setExplanation(trim($qData['explanation'] ?? '') ?: null);
            $question->setPosition($position++);

            $answerPos = 1;
            foreach ($qData['answers'] ?? [] as $aData) {
                $answerText = trim($aData['text'] ?? '');
                if ($answerText === '') {
                    continue;
                }
                $answer = new QuizAnswer();
                $answer->setAnswerText($answerText);
                $answer->setIsCorrect(isset($aData['correct']));
                $answer->setPosition($answerPos++);
                $question->addAnswer($answer);
            }

            $quiz->addQuestion($question);
        }

        $this->entityManager->persist($quiz);
        $this->entityManager->flush();
    }
}
