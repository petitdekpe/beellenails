<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ModuleProgressRepository;

#[ORM\Entity(repositoryClass: ModuleProgressRepository::class)]
class ModuleProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'moduleProgresses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?FormationEnrollment $enrollment = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?FormationModule $module = null;

    #[ORM\Column]
    private ?bool $started = false;

    #[ORM\Column]
    private ?bool $completed = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastAccessedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $videoPosition = 0; // Position en secondes dans la vidéo

    #[ORM\Column(nullable: true)]
    private ?int $timeSpent = 0; // Temps passé en secondes

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $completionPercentage = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null; // Notes personnelles de l'utilisateur

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnrollment(): ?FormationEnrollment
    {
        return $this->enrollment;
    }

    public function setEnrollment(?FormationEnrollment $enrollment): static
    {
        $this->enrollment = $enrollment;
        return $this;
    }

    public function getModule(): ?FormationModule
    {
        return $this->module;
    }

    public function setModule(?FormationModule $module): static
    {
        $this->module = $module;
        return $this;
    }

    public function isStarted(): ?bool
    {
        return $this->started;
    }

    public function setStarted(bool $started): static
    {
        $this->started = $started;
        
        if ($started && !$this->startedAt) {
            $this->startedAt = new \DateTime();
            $this->lastAccessedAt = new \DateTime();
        }
        
        return $this;
    }

    public function isCompleted(): ?bool
    {
        return $this->completed;
    }

    public function setCompleted(bool $completed): static
    {
        $this->completed = $completed;
        
        if ($completed && !$this->completedAt) {
            $this->completedAt = new \DateTime();
            $this->completionPercentage = '100.00';
        }
        
        return $this;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTime $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getLastAccessedAt(): ?\DateTime
    {
        return $this->lastAccessedAt;
    }

    public function setLastAccessedAt(?\DateTime $lastAccessedAt): static
    {
        $this->lastAccessedAt = $lastAccessedAt;
        return $this;
    }

    public function getVideoPosition(): ?int
    {
        return $this->videoPosition;
    }

    public function setVideoPosition(?int $videoPosition): static
    {
        $this->videoPosition = $videoPosition;
        $this->lastAccessedAt = new \DateTime();
        
        // Update completion percentage based on video position
        if ($this->module && $this->module->getDuration() > 0) {
            $percentage = min(100, ($videoPosition / ($this->module->getDuration() * 60)) * 100);
            $this->completionPercentage = number_format($percentage, 2);
            
            // Auto-complete if reached near end (95%)
            if ($percentage >= 95 && !$this->completed) {
                $this->setCompleted(true);
            }
        }
        
        return $this;
    }

    public function getTimeSpent(): ?int
    {
        return $this->timeSpent;
    }

    public function setTimeSpent(?int $timeSpent): static
    {
        $this->timeSpent = $timeSpent;
        return $this;
    }

    public function addTimeSpent(int $seconds): static
    {
        $this->timeSpent = ($this->timeSpent ?? 0) + $seconds;
        return $this;
    }

    public function getCompletionPercentage(): ?string
    {
        return $this->completionPercentage;
    }

    public function setCompletionPercentage(?string $completionPercentage): static
    {
        $this->completionPercentage = $completionPercentage;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getFormattedTimeSpent(): string
    {
        if (!$this->timeSpent) {
            return '0 min';
        }
        
        $hours = floor($this->timeSpent / 3600);
        $minutes = floor(($this->timeSpent % 3600) / 60);
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'min';
        }
        
        return $minutes . ' min';
    }

    public function getFormattedVideoPosition(): string
    {
        if (!$this->videoPosition) {
            return '0:00';
        }
        
        $minutes = floor($this->videoPosition / 60);
        $seconds = $this->videoPosition % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getStatus(): string
    {
        if ($this->completed) {
            return 'completed';
        } elseif ($this->started) {
            return 'in_progress';
        } else {
            return 'not_started';
        }
    }

    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            'completed' => 'Terminé',
            'in_progress' => 'En cours',
            'not_started' => 'Non commencé',
            default => 'Inconnu'
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->getStatus()) {
            'completed' => 'green',
            'in_progress' => 'blue',
            'not_started' => 'gray',
            default => 'gray'
        };
    }
}