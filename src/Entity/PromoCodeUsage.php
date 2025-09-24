<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use App\Repository\PromoCodeUsageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromoCodeUsageRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PromoCodeUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'usages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PromoCode $promoCode = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Rendezvous $rendezvous = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null; // 'attempted', 'validated', 'revoked'

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $finalAmount = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $attemptedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $validatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $revokedAt = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public const STATUS_ATTEMPTED = 'attempted';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REVOKED = 'revoked';

    public function __construct()
    {
        $this->attemptedAt = new \DateTime();
        $this->status = self::STATUS_ATTEMPTED;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function setPromoCode(?PromoCode $promoCode): static
    {
        $this->promoCode = $promoCode;
        return $this;
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

    public function getRendezvous(): ?Rendezvous
    {
        return $this->rendezvous;
    }

    public function setRendezvous(?Rendezvous $rendezvous): static
    {
        $this->rendezvous = $rendezvous;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getOriginalAmount(): ?string
    {
        return $this->originalAmount;
    }

    public function setOriginalAmount(?string $originalAmount): static
    {
        $this->originalAmount = $originalAmount;
        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getFinalAmount(): ?string
    {
        return $this->finalAmount;
    }

    public function setFinalAmount(?string $finalAmount): static
    {
        $this->finalAmount = $finalAmount;
        return $this;
    }

    public function getAttemptedAt(): ?\DateTimeInterface
    {
        return $this->attemptedAt;
    }

    public function setAttemptedAt(\DateTimeInterface $attemptedAt): static
    {
        $this->attemptedAt = $attemptedAt;
        return $this;
    }

    public function getValidatedAt(): ?\DateTimeInterface
    {
        return $this->validatedAt;
    }

    public function setValidatedAt(?\DateTimeInterface $validatedAt): static
    {
        $this->validatedAt = $validatedAt;
        return $this;
    }

    public function getRevokedAt(): ?\DateTimeInterface
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTimeInterface $revokedAt): static
    {
        $this->revokedAt = $revokedAt;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
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

    // Méthodes utilitaires
    public function validate(): static
    {
        $this->status = self::STATUS_VALIDATED;
        $this->validatedAt = new \DateTime();
        return $this;
    }

    public function revoke(string $reason = null): static
    {
        $this->status = self::STATUS_REVOKED;
        $this->revokedAt = new \DateTime();
        if ($reason) {
            $this->notes = ($this->notes ? $this->notes . "\n" : '') . "Révoqué: " . $reason;
        }
        return $this;
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_ATTEMPTED => 'Tentative',
            self::STATUS_VALIDATED => 'Validé',
            self::STATUS_REVOKED => 'Révoqué',
            default => 'Inconnu'
        };
    }
}