<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Controller;

use App\Entity\FormationModule;
use App\Entity\Quiz;
use App\Entity\QuizAnswer;
use App\Entity\QuizQuestion;
use App\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/quiz')]
#[IsGranted('ROLE_ADMIN')]
class QuizController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly QuizRepository $quizRepository,
    ) {}

    #[Route('/module/{id}', name: 'app_quiz_show', methods: ['GET'])]
    public function show(FormationModule $module): Response
    {
        $quiz = $module->getQuiz();

        return $this->render('dashboard/quiz/show.html.twig', [
            'module' => $module,
            'quiz' => $quiz,
        ]);
    }

    #[Route('/module/{id}/create', name: 'app_quiz_create', methods: ['GET', 'POST'])]
    public function create(FormationModule $module, Request $request): Response
    {
        if ($module->getQuiz()) {
            return $this->redirectToRoute('app_quiz_show', ['id' => $module->getId()]);
        }

        if ($request->isMethod('POST')) {
            return $this->handleSave($module, null, $request);
        }

        return $this->render('dashboard/quiz/edit.html.twig', [
            'module' => $module,
            'quiz' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_quiz_edit', methods: ['GET', 'POST'])]
    public function edit(Quiz $quiz, Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($quiz->getModule(), $quiz, $request);
        }

        return $this->render('dashboard/quiz/edit.html.twig', [
            'module' => $quiz->getModule(),
            'quiz' => $quiz,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_quiz_delete', methods: ['POST'])]
    public function delete(Quiz $quiz, Request $request): Response
    {
        if ($this->isCsrfTokenValid('delete_quiz_' . $quiz->getId(), $request->request->get('_token'))) {
            $moduleId = $quiz->getModule()->getId();
            $this->entityManager->remove($quiz);
            $this->entityManager->flush();
            $this->addFlash('success', 'Quiz supprimé.');
            return $this->redirectToRoute('app_quiz_show', ['id' => $moduleId]);
        }

        return $this->redirectToRoute('app_quiz_show', ['id' => $quiz->getModule()->getId()]);
    }

    #[Route('/{id}/toggle', name: 'app_quiz_toggle', methods: ['POST'])]
    public function toggle(Quiz $quiz): JsonResponse
    {
        $quiz->setIsActive(!$quiz->isActive());
        $this->entityManager->flush();

        return new JsonResponse(['active' => $quiz->isActive()]);
    }

    private function handleSave(FormationModule $module, ?Quiz $quiz, Request $request): Response
    {
        $data = $request->request->all();

        if (!$quiz) {
            $quiz = new Quiz();
            $quiz->setModule($module);
        }

        $quiz->setPassingScore((int) ($data['passing_score'] ?? 70));
        $quiz->setMaxAttempts(
            isset($data['max_attempts']) && $data['max_attempts'] !== '' && $data['max_attempts'] !== '0'
                ? (int) $data['max_attempts']
                : null
        );
        $quiz->setIsActive(isset($data['is_active']));
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

            $answerPosition = 1;
            foreach ($qData['answers'] ?? [] as $aData) {
                $answerText = trim($aData['text'] ?? '');
                if ($answerText === '') {
                    continue;
                }
                $answer = new QuizAnswer();
                $answer->setAnswerText($answerText);
                $answer->setIsCorrect(isset($aData['correct']));
                $answer->setPosition($answerPosition++);
                $question->addAnswer($answer);
            }

            $quiz->addQuestion($question);
        }

        $this->entityManager->persist($quiz);
        $this->entityManager->flush();

        $this->addFlash('success', 'Quiz enregistré avec succès.');
        return $this->redirectToRoute('app_quiz_show', ['id' => $module->getId()]);
    }
}
