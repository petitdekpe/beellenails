<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use App\Repository\PromoCodeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PromoCodeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PromoCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex('/^[A-Z0-9_-]+$/', message: 'Le code ne peut contenir que des lettres majuscules, chiffres, tirets et underscores')]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotNull(message: 'Le type de réduction est obligatoire')]
    #[Assert\Choice(choices: ['percentage', 'fixed_amount'], message: 'Type de réduction invalide')]
    private string $discountType = 'percentage';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $discountValue = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $minimumAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $maximumDiscount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $validFrom = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank]
    #[Assert\GreaterThan(propertyPath: 'validFrom', message: 'La date de fin doit être postérieure à la date de début')]
    private ?\DateTimeInterface $validUntil = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $maxUsageGlobal = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $maxUsagePerUser = null;

    #[ORM\Column]
    private ?int $currentUsage = 0;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Prestation::class)]
    #[ORM\JoinTable(name: 'promo_code_prestations')]
    private Collection $eligiblePrestations;

    #[ORM\OneToMany(mappedBy: 'promoCode', targetEntity: PromoCodeUsage::class, cascade: ['remove'])]
    private Collection $usages;

    #[ORM\OneToMany(mappedBy: 'promoCode', targetEntity: Rendezvous::class)]
    private Collection $rendezvous;

    public function __construct()
    {
        $this->eligiblePrestations = new ArrayCollection();
        $this->usages = new ArrayCollection();
        $this->rendezvous = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->currentUsage = 0;
        $this->isActive = true;
        $this->discountType = 'percentage'; // Valeur par défaut
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = strtoupper($code);
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDiscountType(): string
    {
        return $this->discountType ?? 'percentage';
    }

    public function setDiscountType(?string $discountType): static
    {
        $this->discountType = $discountType ?? 'percentage';
        return $this;
    }

    public function getDiscountValue(): ?string
    {
        return $this->discountValue;
    }

    public function setDiscountValue(string $discountValue): static
    {
        $this->discountValue = $discountValue;
        return $this;
    }

    public function getMinimumAmount(): ?string
    {
        return $this->minimumAmount;
    }

    public function setMinimumAmount(?string $minimumAmount): static
    {
        $this->minimumAmount = $minimumAmount;
        return $this;
    }

    public function getMaximumDiscount(): ?string
    {
        return $this->maximumDiscount;
    }

    public function setMaximumDiscount(?string $maximumDiscount): static
    {
        $this->maximumDiscount = $maximumDiscount;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(\DateTimeInterface $validFrom): static
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(\DateTimeInterface $validUntil): static
    {
        $this->validUntil = $validUntil;
        return $this;
    }

    public function getMaxUsageGlobal(): ?int
    {
        return $this->maxUsageGlobal;
    }

    public function setMaxUsageGlobal(?int $maxUsageGlobal): static
    {
        $this->maxUsageGlobal = $maxUsageGlobal;
        return $this;
    }

    public function getMaxUsagePerUser(): ?int
    {
        return $this->maxUsagePerUser;
    }

    public function setMaxUsagePerUser(?int $maxUsagePerUser): static
    {
        $this->maxUsagePerUser = $maxUsagePerUser;
        return $this;
    }

    public function getCurrentUsage(): ?int
    {
        return $this->currentUsage;
    }

    public function setCurrentUsage(int $currentUsage): static
    {
        $this->currentUsage = $currentUsage;
        return $this;
    }

    public function incrementUsage(): static
    {
        $this->currentUsage++;
        return $this;
    }

    public function decrementUsage(): static
    {
        $this->currentUsage = max(0, $this->currentUsage - 1);
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Prestation>
     */
    public function getEligiblePrestations(): Collection
    {
        return $this->eligiblePrestations;
    }

    public function addEligiblePrestation(Prestation $prestation): static
    {
        if (!$this->eligiblePrestations->contains($prestation)) {
            $this->eligiblePrestations->add($prestation);
        }
        return $this;
    }

    public function removeEligiblePrestation(Prestation $prestation): static
    {
        $this->eligiblePrestations->removeElement($prestation);
        return $this;
    }

    /**
     * @return Collection<int, PromoCodeUsage>
     */
    public function getUsages(): Collection
    {
        return $this->usages;
    }

    public function addUsage(PromoCodeUsage $usage): static
    {
        if (!$this->usages->contains($usage)) {
            $this->usages->add($usage);
            $usage->setPromoCode($this);
        }
        return $this;
    }

    public function removeUsage(PromoCodeUsage $usage): static
    {
        if ($this->usages->removeElement($usage)) {
            if ($usage->getPromoCode() === $this) {
                $usage->setPromoCode(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Rendezvous>
     */
    public function getRendezvous(): Collection
    {
        return $this->rendezvous;
    }

    public function addRendezvous(Rendezvous $rendezvous): static
    {
        if (!$this->rendezvous->contains($rendezvous)) {
            $this->rendezvous->add($rendezvous);
            $rendezvous->setPromoCode($this);
        }
        return $this;
    }

    public function removeRendezvous(Rendezvous $rendezvous): static
    {
        if ($this->rendezvous->removeElement($rendezvous)) {
            if ($rendezvous->getPromoCode() === $this) {
                $rendezvous->setPromoCode(null);
            }
        }
        return $this;
    }

    // Méthodes utilitaires
    public function isValid(\DateTimeInterface $date = null): bool
    {
        $date = $date ?? new \DateTime();
        return $this->isActive 
            && $date >= $this->validFrom 
            && $date <= $this->validUntil
            && ($this->maxUsageGlobal === null || $this->currentUsage < $this->maxUsageGlobal);
    }

    public function isEligibleForPrestation(Prestation $prestation): bool
    {
        return $this->eligiblePrestations->isEmpty() || $this->eligiblePrestations->contains($prestation);
    }

    public function getDiscountDisplayValue(): string
    {
        if ($this->discountType === 'percentage') {
            return $this->discountValue . '%';
        }
        return number_format((float)$this->discountValue, 2) . ' F CFA';
    }

    public function __toString(): string
    {
        return $this->code ?? '';
    }
}