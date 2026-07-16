<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>

namespace App\Entity;

use App\Repository\BookingSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Réglages globaux de réservation (ligne unique).
 */
#[ORM\Entity(repositoryClass: BookingSettingsRepository::class)]
class BookingSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 1440, notInRangeMessage: 'La durée doit être comprise entre 1 et 1440 minutes.')]
    #[ORM\Column]
    private int $holdDurationMinutes = 15;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHoldDurationMinutes(): int
    {
        return $this->holdDurationMinutes;
    }

    public function setHoldDurationMinutes(int $holdDurationMinutes): self
    {
        $this->holdDurationMinutes = $holdDurationMinutes;
        $this->updatedAt = new \DateTime();

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
