<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\QuizAttemptAnswerRepository;

#[ORM\Entity(repositoryClass: QuizAttemptAnswerRepository::class)]
class QuizAttemptAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attemptAnswers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?QuizAttempt $attempt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?QuizQuestion $question = null;

    // IDs des réponses sélectionnées par l'utilisateur (JSON)
    #[ORM\Column(type: Types::JSON)]
    private array $selectedAnswerIds = [];

    // Indique si la réponse globale à cette question est correcte
    #[ORM\Column]
    private bool $isCorrect = false;

    public function getId(): ?int { return $this->id; }

    public function getAttempt(): ?QuizAttempt { return $this->attempt; }

    public function setAttempt(?QuizAttempt $attempt): static
    {
        $this->attempt = $attempt;
        return $this;
    }

    public function getQuestion(): ?QuizQuestion { return $this->question; }

    public function setQuestion(?QuizQuestion $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getSelectedAnswerIds(): array { return $this->selectedAnswerIds; }

    public function setSelectedAnswerIds(array $selectedAnswerIds): static
    {
        $this->selectedAnswerIds = $selectedAnswerIds;
        return $this;
    }

    public function isCorrectAnswer(): bool { return $this->isCorrect; }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;
        return $this;
    }

    public function wasAnswerSelected(int $answerId): bool
    {
        return in_array($answerId, $this->selectedAnswerIds, true);
    }

    // Vérifie si les réponses sélectionnées correspondent exactement aux bonnes réponses
    public function evaluateCorrectness(): static
    {
        if (!$this->question) {
            return $this;
        }

        $correctIds = $this->question->getCorrectAnswers()
            ->map(fn(QuizAnswer $a) => $a->getId())
            ->toArray();

        sort($correctIds);
        $selected = $this->selectedAnswerIds;
        sort($selected);

        $this->isCorrect = $correctIds === $selected;
        return $this;
    }
}
