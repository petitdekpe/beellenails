<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\QuizAnswerRepository;

#[ORM\Entity(repositoryClass: QuizAnswerRepository::class)]
class QuizAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?QuizQuestion $question = null;

    #[ORM\Column(length: 500)]
    private ?string $answerText = null;

    #[ORM\Column]
    private bool $isCorrect = false;

    #[ORM\Column]
    private int $position = 1;

    public function getId(): ?int { return $this->id; }

    public function getQuestion(): ?QuizQuestion { return $this->question; }

    public function setQuestion(?QuizQuestion $question): static
    {
        $this->question = $question;
        return $this;
    }

    public function getAnswerText(): ?string { return $this->answerText; }

    public function setAnswerText(string $answerText): static
    {
        $this->answerText = $answerText;
        return $this;
    }

    public function isCorrect(): bool { return $this->isCorrect; }

    public function setIsCorrect(bool $isCorrect): static
    {
        $this->isCorrect = $isCorrect;
        return $this;
    }

    public function getPosition(): int { return $this->position; }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }
}
