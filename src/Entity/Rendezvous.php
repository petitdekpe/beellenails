<?php
// SPDX-License-Identifier: Proprietary
// Copyright (c) 2025 Jean-Yves A.
// Auteur: Jean-Yves A. <murielahodode@gmail.com>


namespace App\Entity;

use App\Interface\PayableEntityInterface;
use App\Repository\PaymentConfigurationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\RendezvousRepository;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RendezvousRepository::class)]
#[Vich\Uploadable]
class Rendezvous implements PayableEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank(groups: ['with_prestation'], message: 'Veuillez choisir une prestation')]
    #[Assert\NotBlank(groups: ['without_prestation'], allowNull: true)]
    #[ORM\ManyToOne(inversedBy: 'rendezvouses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestation $prestation = null;

    #[Vich\UploadableField(mapping: 'rendezvous', fileNameProperty: 'imageName')]
    private ?File $image = null;

    #[ORM\Column(length: 255)]
    private ?string $imageName = null;

    #[Assert\NotBlank(message: 'Veuillez choisir une date de rendez-vous')]
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $day = null;

    #[Assert\NotBlank(message: 'Plus de créneau libre pour cette date. Choisissez une date avec des créneaux libres affichés.')]
    #[ORM\ManyToOne(inversedBy: 'rendezvouses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Creneau $creneau = null;

    #[ORM\ManyToOne(inversedBy: 'rendezvouses')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'rendezvous', targetEntity: Payment::class, cascade: ['persist', 'remove'])]
    private Collection $payments;

    #[ORM\Column(nullable: true)]
    private ?bool $paid = null;

    #[ORM\ManyToMany(targetEntity: Supplement::class, inversedBy: 'rendezvouses')]
    private Collection $supplement;

    #[ORM\Column(nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $created_at;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $updated_at;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $totalCost = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $originalAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;

    #[ORM\ManyToOne(inversedBy: 'rendezvous')]
    private ?PromoCode $promoCode = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $pendingPromoCode = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $previousDay = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Creneau $previousCreneau = null;

    public function __construct()
    {
        $this->payments = new ArrayCollection();
        $this->supplement = new ArrayCollection();
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(?Prestation $prestation): self
    {
        $this->prestation = $prestation;

        return $this;
    }

    public function getImage(): ?File
    {
        return $this->image;
    }

    public function setImage(?File $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(string $imageName): self
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getDay(): ?\DateTimeInterface
    {
        return $this->day;
    }

    public function setDay(?\DateTimeInterface $day): self
    {
        $this->day = $day;
        $this->updateTimestamps();

        return $this;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(?Creneau $creneau): self
    {
        $this->creneau = $creneau;
        $this->updateTimestamps();

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setRendezvous($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getRendezvous() === $this) {
                $payment->setRendezvous(null);
            }
        }

        return $this;
    }

    public function isPaid(): ?bool
    {
        return $this->paid;
    }

    public function setPaid(?bool $paid): self
    {
        $this->paid = $paid;

        return $this;
    }

    /**
     * @return Collection<int, Supplement>
     */
    public function getSupplement(): Collection
    {
        return $this->supplement;
    }

    public function addSupplement(Supplement $supplement): self
    {
        if (!$this->supplement->contains($supplement)) {
            $this->supplement->add($supplement);
        }

        return $this;
    }

    public function removeSupplement(Supplement $supplement): self
    {
        $this->supplement->removeElement($supplement);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function updateTimestamps(): void
    {
        $this->updated_at = new \DateTime();
    }

    public function getTotalCost(): ?string
    {
        return $this->totalCost;
    }

    public function setTotalCost(?string $totalCost): self
    {
        $this->totalCost = $totalCost;
        $this->updateTimestamps();

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

    public function getPromoCode(): ?PromoCode
    {
        return $this->promoCode;
    }

    public function setPromoCode(?PromoCode $promoCode): static
    {
        $this->promoCode = $promoCode;
        return $this;
    }

    public function getPendingPromoCode(): ?string
    {
        return $this->pendingPromoCode;
    }

    public function setPendingPromoCode(?string $pendingPromoCode): static
    {
        $this->pendingPromoCode = $pendingPromoCode;
        return $this;
    }

    /**
     * Calcule automatiquement le coût total basé sur la prestation et les suppléments
     */
    public function calculateTotalCost(): string
    {
        $total = 0;

        // Ajouter le prix de la prestation principale
        if ($this->prestation) {
            $total += (float) $this->prestation->getPrice();
        }

        // Ajouter le prix de tous les suppléments
        foreach ($this->supplement as $supplement) {
            $total += (float) $supplement->getPrice();
        }

        return (string) $total;
    }

    /**
     * Met à jour le coût total automatiquement
     */
    public function updateTotalCost(): self
    {
        $this->totalCost = $this->calculateTotalCost();
        $this->updateTimestamps();

        return $this;
    }

    public function getPreviousDay(): ?\DateTimeInterface
    {
        return $this->previousDay;
    }

    public function setPreviousDay(?\DateTimeInterface $previousDay): self
    {
        $this->previousDay = $previousDay;
        $this->updateTimestamps();

        return $this;
    }

    public function getPreviousCreneau(): ?Creneau
    {
        return $this->previousCreneau;
    }

    public function setPreviousCreneau(?Creneau $previousCreneau): self
    {
        $this->previousCreneau = $previousCreneau;
        $this->updateTimestamps();

        return $this;
    }

    /**
     * Sauvegarde les anciennes informations avant modification
     */
    public function saveCurrentAsHistory(): self
    {
        $this->previousDay = $this->day;
        $this->previousCreneau = $this->creneau;
        return $this;
    }

    /**
     * Vérifie si le rendez-vous a été reporté (a des informations historiques)
     */
    public function isRescheduled(): bool
    {
        return $this->previousDay !== null || $this->previousCreneau !== null;
    }

    // Implementation of PayableEntityInterface

    public function getPaymentDescription(): string
    {
        $prestationName = $this->prestation?->getTitle() ?? 'Prestation';
        return "Acompte pour {$prestationName} - " . $this->user?->getFullName();
    }

    public function getPaymentAmount(string $paymentType): int
    {
        // Cette méthode sera utilisée par le service PaymentTypeResolver
        // Pour l'instant, retourner une valeur par défaut
        return match($paymentType) {
            'rendezvous_advance' => 5000, // Sera remplacé par la configuration
            default => 0
        };
    }

    public function onPaymentSuccess(): void
    {
        $this->setPaid(true);
        $this->setStatus('Rendez-vous pris');
    }

    public function onPaymentFailure(): void
    {
        $this->setStatus('Échec du paiement');
    }

    public function onPaymentCancellation(): void
    {
        $this->setStatus('Paiement annulé');
    }

    public function getSuccessRedirectRoute(): string
    {
        return 'rendezvous_payment_done';
    }

    public function getFailureRedirectRoute(): string
    {
        return 'rendezvous_payment_error';
    }

    public function getEntityType(): string
    {
        return 'rendezvous';
    }

    public function getPaymentContext(): array
    {
        return [
            'rendezvous_id' => $this->id,
            'user_name' => $this->user?->getFullName(),
            'prestation' => $this->prestation?->getTitle(),
            'day' => $this->day?->format('Y-m-d'),
            'creneau' => $this->creneau?->getDebut()
        ];
    }
}
