<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\QuizRepository;

#[ORM\Entity(repositoryClass: QuizRepository::class)]
class Quiz
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'quiz')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FormationModule $module = null;

    #[ORM\Column]
    private int $passingScore = 70;

    #[ORM\Column(nullable: true)]
    private ?int $maxAttempts = null; // null = illimité

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'quiz', targetEntity: QuizQuestion::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $questions;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->questions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getModule(): ?FormationModule { return $this->module; }

    public function setModule(?FormationModule $module): static
    {
        $this->module = $module;
        return $this;
    }

    public function getPassingScore(): int { return $this->passingScore; }

    public function setPassingScore(int $passingScore): static
    {
        $this->passingScore = $passingScore;
        return $this;
    }

    public function getMaxAttempts(): ?int { return $this->maxAttempts; }

    public function setMaxAttempts(?int $maxAttempts): static
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    public function isUnlimitedAttempts(): bool { return $this->maxAttempts === null; }

    public function isActive(): bool { return $this->isActive; }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getQuestions(): Collection { return $this->questions; }

    public function addQuestion(QuizQuestion $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setQuiz($this);
        }
        return $this;
    }

    public function removeQuestion(QuizQuestion $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getQuiz() === $this) {
                $question->setQuiz(null);
            }
        }
        return $this;
    }

    public function getQuestionsCount(): int { return $this->questions->count(); }
}
