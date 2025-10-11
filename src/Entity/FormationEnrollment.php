<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\FormationEnrollmentRepository;

#[ORM\Entity(repositoryClass: FormationEnrollmentRepository::class)]
class FormationEnrollment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Formation $formation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $enrolledAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $expiresAt = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'active'; // active, completed, expired, cancelled

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $progressPercentage = '0.00';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $completedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastAccessedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $certificateGenerated = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $certificateGeneratedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $expirationNotifiedAt = null;

    #[ORM\OneToMany(mappedBy: 'enrollment', targetEntity: ModuleProgress::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $moduleProgresses;

    public function __construct()
    {
        $this->moduleProgresses = new ArrayCollection();
        $this->enrolledAt = new \DateTime();
        $this->lastAccessedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;
        
        // Calculate expiration date based on formation access type
        if ($formation) {
            $this->calculateExpirationDate();
        }
        
        return $this;
    }

    public function getEnrolledAt(): ?\DateTime
    {
        return $this->enrolledAt;
    }

    public function setEnrolledAt(\DateTime $enrolledAt): static
    {
        $this->enrolledAt = $enrolledAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        
        if ($status === 'completed' && !$this->completedAt) {
            $this->completedAt = new \DateTime();
        }
        
        return $this;
    }

    public function getProgressPercentage(): ?string
    {
        return $this->progressPercentage;
    }

    public function setProgressPercentage(?string $progressPercentage): static
    {
        $this->progressPercentage = $progressPercentage;
        
        // Auto-complete if 100%
        if ($progressPercentage >= 100 && $this->status !== 'completed') {
            $this->setStatus('completed');
        }
        
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

    public function isCertificateGenerated(): ?bool
    {
        return $this->certificateGenerated;
    }

    public function setCertificateGenerated(bool $certificateGenerated): static
    {
        $this->certificateGenerated = $certificateGenerated;
        
        if ($certificateGenerated && !$this->certificateGeneratedAt) {
            $this->certificateGeneratedAt = new \DateTime();
        }
        
        return $this;
    }

    public function getCertificateGeneratedAt(): ?\DateTime
    {
        return $this->certificateGeneratedAt;
    }

    public function setCertificateGeneratedAt(?\DateTime $certificateGeneratedAt): static
    {
        $this->certificateGeneratedAt = $certificateGeneratedAt;
        return $this;
    }

    public function getExpirationNotifiedAt(): ?\DateTime
    {
        return $this->expirationNotifiedAt;
    }

    public function setExpirationNotifiedAt(?\DateTime $expirationNotifiedAt): static
    {
        $this->expirationNotifiedAt = $expirationNotifiedAt;
        return $this;
    }

    public function getModuleProgresses(): Collection
    {
        return $this->moduleProgresses;
    }

    public function addModuleProgress(ModuleProgress $moduleProgress): static
    {
        if (!$this->moduleProgresses->contains($moduleProgress)) {
            $this->moduleProgresses->add($moduleProgress);
            $moduleProgress->setEnrollment($this);
        }
        return $this;
    }

    public function removeModuleProgress(ModuleProgress $moduleProgress): static
    {
        if ($this->moduleProgresses->removeElement($moduleProgress)) {
            if ($moduleProgress->getEnrollment() === $this) {
                $moduleProgress->setEnrollment(null);
            }
        }
        return $this;
    }

    private function calculateExpirationDate(): void
    {
        if (!$this->formation) {
            return;
        }

        if ($this->formation->getAccessType() === 'relative' && $this->formation->getAccessDuration()) {
            $this->expiresAt = (clone $this->enrolledAt)->add(new \DateInterval('P' . $this->formation->getAccessDuration() . 'D'));
        } elseif ($this->formation->getAccessType() === 'fixed' && $this->formation->getEndDate()) {
            $this->expiresAt = $this->formation->getEndDate();
        }
    }

    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }
        
        return new \DateTime() > $this->expiresAt;
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expiresAt || $this->isExpired()) {
            return null;
        }
        
        $now = new \DateTime();
        $diff = $now->diff($this->expiresAt);
        
        return $diff->days;
    }

    public function getTimeUntilExpiration(): ?string
    {
        if (!$this->expiresAt || $this->isExpired()) {
            return null;
        }
        
        $now = new \DateTime();
        $diff = $now->diff($this->expiresAt);
        
        if ($diff->days > 0) {
            return $diff->days . ' jour' . ($diff->days > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        } else {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        }
    }

    public function isAccessible(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    public function updateProgress(): void
    {
        $totalModules = $this->formation?->getModules()->count() ?? 0;
        if ($totalModules === 0) {
            return;
        }

        $completedModules = $this->moduleProgresses->filter(fn($mp) => $mp->isCompleted())->count();
        $percentage = ($completedModules / $totalModules) * 100;

        $this->setProgressPercentage(number_format($percentage, 2));
    }

    public function getTotalTimeSpent(): int
    {
        $totalTime = 0;
        foreach ($this->moduleProgresses as $moduleProgress) {
            $totalTime += $moduleProgress->getTimeSpent() ?? 0;
        }
        return $totalTime;
    }

    public function getCompletedModulesCount(): int
    {
        return $this->moduleProgresses->filter(fn($mp) => $mp->isCompleted())->count();
    }
}