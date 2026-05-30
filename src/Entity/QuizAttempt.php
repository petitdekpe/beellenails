<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\QuizAttemptRepository;

#[ORM\Entity(repositoryClass: QuizAttemptRepository::class)]
class QuizAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FormationEnrollment $enrollment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quiz $quiz = null;

    #[ORM\Column]
    private int $attemptNumber = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $score = null; // pourcentage 0-100

    #[ORM\Column]
    private bool $passed = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $completedAt = null;

    #[ORM\OneToMany(mappedBy: 'attempt', targetEntity: QuizAttemptAnswer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $attemptAnswers;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
        $this->attemptAnswers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEnrollment(): ?FormationEnrollment { return $this->enrollment; }

    public function setEnrollment(?FormationEnrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
        return $this;
    }

    public function getQuiz(): ?Quiz { return $this->quiz; }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getAttemptNumber(): int { return $this->attemptNumber; }

    public function setAttemptNumber(int $attemptNumber): static
    {
        $this->attemptNumber = $attemptNumber;
        return $this;
    }

    public function getScore(): ?float
    {
        return $this->score !== null ? (float) $this->score : null;
    }

    public function setScore(?float $score): static
    {
        $this->score = $score !== null ? number_format($score, 2) : null;
        return $this;
    }

    public function isPassed(): bool { return $this->passed; }

    public function setPassed(bool $passed): static
    {
        $this->passed = $passed;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable { return $this->startedAt; }

    public function getCompletedAt(): ?\DateTime { return $this->completedAt; }

    public function setCompletedAt(?\DateTime $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function isCompleted(): bool { return $this->completedAt !== null; }

    public function getAttemptAnswers(): Collection { return $this->attemptAnswers; }

    public function addAttemptAnswer(QuizAttemptAnswer $answer): static
    {
        if (!$this->attemptAnswers->contains($answer)) {
            $this->attemptAnswers->add($answer);
            $answer->setAttempt($this);
        }
        return $this;
    }

    public function calculateScore(): float
    {
        $total = $this->attemptAnswers->count();
        if ($total === 0) {
            return 0.0;
        }
        $correct = $this->attemptAnswers->filter(fn(QuizAttemptAnswer $a) => $a->isCorrectAnswer())->count();
        return round(($correct / $total) * 100, 2);
    }
}
