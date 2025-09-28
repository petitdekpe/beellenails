<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Entity;

use App\Interface\PayableEntityInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use App\Entity\FormationReview;
use App\Entity\FormationModule;
use App\Entity\FormationResource;


#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[Vich\Uploadable]
class Formation implements PayableEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Nom = null;

    #[ORM\Column(length: 700, nullable: true)]
    private ?string $Prerequis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $Objectif = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $Suivi = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $Programme = null;

    #[ORM\Column]
    private ?int $Cout = null;

    #[Vich\UploadableField(mapping: 'formation', fileNameProperty: 'imageName')]
    private ?File $image = null;

    #[ORM\Column(length: 255)]
    private ?string $imageName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $Description = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $theme = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $level = null;

    #[ORM\Column(nullable: true)]
    private ?int $duration = null;

    #[ORM\Column]
    private ?bool $isFree = false;

    #[ORM\Column(length: 20)]
    private ?string $accessType = 'relative';

    #[ORM\Column(nullable: true)]
    private ?int $accessDuration = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $endDate = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instructorName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $instructorBio = null;

    #[Vich\UploadableField(mapping: 'formation_instructor', fileNameProperty: 'instructorImageName')]
    private ?File $instructorImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instructorImageName = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $youtubeUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $targetAudience = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: FormationReview::class, orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: FormationModule::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $modules;

    #[ORM\OneToMany(mappedBy: 'formation', targetEntity: FormationResource::class, orphanRemoval: true, cascade: ['persist', 'remove'])]
    private Collection $resources;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
        $this->modules = new ArrayCollection();
        $this->resources = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->Nom;
    }

    public function setNom(string $Nom): static
    {
        $this->Nom = $Nom;

        return $this;
    }

    public function getPrerequis(): ?string
    {
        return $this->Prerequis;
    }

    public function setPrerequis(?string $Prerequis): static
    {
        $this->Prerequis = $Prerequis;

        return $this;
    }

    public function getObjectif(): ?string
    {
        return $this->Objectif;
    }

    public function setObjectif(?string $Objectif): static
    {
        $this->Objectif = $Objectif;

        return $this;
    }

    public function getSuivi(): ?string
    {
        return $this->Suivi;
    }

    public function setSuivi(string $Suivi): static
    {
        $this->Suivi = $Suivi;

        return $this;
    }

    public function getProgramme(): ?string
    {
        return $this->Programme;
    }

    public function setProgramme(string $Programme): static
    {
        $this->Programme = $Programme;

        return $this;
    }

    public function getCout(): ?int
    {
        return $this->Cout;
    }

    public function setCout(int $Cout): static
    {
        $this->Cout = $Cout;

        return $this;
    }

    public function getImage(): ?File
    {
        return $this->image;
    }

    public function setImage(?File $image = null): void
    {
        $this->image = $image;
        if ($image) {
            $this->updatedAt = new \DateTimeImmutable('now');
        }
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->Description;
    }

    public function setDescription(string $Description): static
    {
        $this->Description = $Description;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getTheme(): ?string
    {
        return $this->theme;
    }

    public function setTheme(?string $theme): static
    {
        $this->theme = $theme;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(?string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function isFree(): ?bool
    {
        return $this->isFree;
    }

    public function setIsFree(bool $isFree): static
    {
        $this->isFree = $isFree;
        return $this;
    }

    public function getAccessType(): ?string
    {
        return $this->accessType;
    }

    public function setAccessType(string $accessType): static
    {
        $this->accessType = $accessType;
        return $this;
    }

    public function getAccessDuration(): ?int
    {
        return $this->accessDuration;
    }

    public function setAccessDuration(?int $accessDuration): static
    {
        $this->accessDuration = $accessDuration;
        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getInstructorName(): ?string
    {
        return $this->instructorName;
    }

    public function setInstructorName(?string $instructorName): static
    {
        $this->instructorName = $instructorName;
        return $this;
    }

    public function getInstructorBio(): ?string
    {
        return $this->instructorBio;
    }

    public function setInstructorBio(?string $instructorBio): static
    {
        $this->instructorBio = $instructorBio;
        return $this;
    }

    public function getInstructorImage(): ?File
    {
        return $this->instructorImage;
    }

    public function setInstructorImage(?File $instructorImage = null): void
    {
        $this->instructorImage = $instructorImage;
        if ($instructorImage) {
            $this->updatedAt = new \DateTimeImmutable('now');
        }
    }

    public function getInstructorImageName(): ?string
    {
        return $this->instructorImageName;
    }

    public function setInstructorImageName(?string $instructorImageName): static
    {
        $this->instructorImageName = $instructorImageName;
        return $this;
    }

    public function getYoutubeUrl(): ?string
    {
        return $this->youtubeUrl;
    }

    public function setYoutubeUrl(?string $youtubeUrl): static
    {
        $this->youtubeUrl = $youtubeUrl;
        return $this;
    }

    public function getTargetAudience(): ?string
    {
        return $this->targetAudience;
    }

    public function setTargetAudience(?string $targetAudience): static
    {
        $this->targetAudience = $targetAudience;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(FormationReview $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setFormation($this);
        }
        return $this;
    }

    public function removeReview(FormationReview $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getFormation() === $this) {
                $review->setFormation(null);
            }
        }
        return $this;
    }

    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(FormationModule $module): static
    {
        if (!$this->modules->contains($module)) {
            $this->modules->add($module);
            $module->setFormation($this);
        }
        return $this;
    }

    public function removeModule(FormationModule $module): static
    {
        if ($this->modules->removeElement($module)) {
            if ($module->getFormation() === $this) {
                $module->setFormation(null);
            }
        }
        return $this;
    }

    public function getResources(): Collection
    {
        return $this->resources;
    }

    public function addResource(FormationResource $resource): static
    {
        if (!$this->resources->contains($resource)) {
            $this->resources->add($resource);
            $resource->setFormation($this);
        }
        return $this;
    }

    public function removeResource(FormationResource $resource): static
    {
        if ($this->resources->removeElement($resource)) {
            if ($resource->getFormation() === $this) {
                $resource->setFormation(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getAverageRating(): ?float
    {
        $approvedReviews = $this->getApprovedReviews();
        if ($approvedReviews->isEmpty()) {
            return null;
        }
        
        $total = 0;
        foreach ($approvedReviews as $review) {
            $total += $review->getRating();
        }
        
        return round($total / $approvedReviews->count(), 1);
    }

    public function getApprovedReviews(): Collection
    {
        return $this->reviews->filter(function($review) {
            return $review->isApproved() && $review->isVisible();
        });
    }

    public function getTotalDuration(): int
    {
        $total = 0;
        foreach ($this->modules as $module) {
            $total += $module->getDuration();
        }
        return $total;
    }

    public function getYoutubeVideoId(): ?string
    {
        if (!$this->youtubeUrl) {
            return null;
        }
        
        // Extract YouTube video ID from various URL formats
        preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $this->youtubeUrl, $matches);
        return $matches[1] ?? null;
    }

    public function isAvailable(): bool
    {
        if (!$this->isActive) {
            return false;
        }
        
        if ($this->accessType === 'fixed' && $this->startDate && $this->endDate) {
            $now = new \DateTime();
            return $now >= $this->startDate && $now <= $this->endDate;
        }
        
        return true;
    }

    // Implementation of PayableEntityInterface

    public function getUser(): ?User
    {
        // Formation n'a pas d'utilisateur associé directement
        // Cette méthode sera utilisée par le controller générique
        return null;
    }

    public function getPaymentDescription(): string
    {
        return "Formation : {$this->Nom}";
    }

    public function getPaymentAmount(string $paymentType): int
    {
        return match($paymentType) {
            'formation_full' => (int) $this->Cout, // Prix complet de la formation
            'formation_advance' => (int) ($this->Cout * 0.3), // 30% d'acompte par défaut
            default => 0
        };
    }

    public function onPaymentSuccess(): void
    {
        // Logic spécifique aux formations après paiement réussi
        // Par exemple, activer l'accès à la formation
    }

    public function onPaymentFailure(): void
    {
        // Logic spécifique aux formations après échec de paiement
    }

    public function onPaymentCancellation(): void
    {
        // Logic spécifique aux formations après annulation de paiement
    }

    public function getSuccessRedirectRoute(): string
    {
        return 'generic_payment_success';
    }

    public function getFailureRedirectRoute(): string
    {
        return 'generic_payment_error';
    }

    public function getEntityType(): string
    {
        return 'formation';
    }

    public function getPaymentContext(): array
    {
        return [
            'formation_id' => $this->id,
            'formation_name' => $this->Nom,
            'formation_price' => $this->Cout,
            'formation_type' => $this->accessType
        ];
    }
}
