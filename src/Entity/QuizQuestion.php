<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\QuizQuestionRepository;

#[ORM\Entity(repositoryClass: QuizQuestionRepository::class)]
class QuizQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quiz $quiz = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $questionText = null;

    #[ORM\Column(length: 10)]
    private string $type = 'single'; // 'single' | 'multiple'

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $explanation = null;

    #[ORM\Column]
    private int $position = 1;

    #[ORM\OneToMany(mappedBy: 'question', targetEntity: QuizAnswer::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $answers;

    public function __construct()
    {
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getQuiz(): ?Quiz { return $this->quiz; }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getQuestionText(): ?string { return $this->questionText; }

    public function setQuestionText(string $questionText): static
    {
        $this->questionText = $questionText;
        return $this;
    }

    public function getType(): string { return $this->type; }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isSingleChoice(): bool { return $this->type === 'single'; }

    public function getExplanation(): ?string { return $this->explanation; }

    public function setExplanation(?string $explanation): static
    {
        $this->explanation = $explanation;
        return $this;
    }

    public function getPosition(): int { return $this->position; }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getAnswers(): Collection { return $this->answers; }

    public function addAnswer(QuizAnswer $answer): static
    {
        if (!$this->answers->contains($answer)) {
            $this->answers->add($answer);
            $answer->setQuestion($this);
        }
        return $this;
    }

    public function removeAnswer(QuizAnswer $answer): static
    {
        if ($this->answers->removeElement($answer)) {
            if ($answer->getQuestion() === $this) {
                $answer->setQuestion(null);
            }
        }
        return $this;
    }

    public function getCorrectAnswers(): Collection
    {
        return $this->answers->filter(fn(QuizAnswer $a) => $a->isCorrect());
    }
}
