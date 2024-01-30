<?php

namespace App\Entity;

use App\Repository\CreneauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CreneauRepository::class)]
class Creneau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\OneToMany(mappedBy: 'creneau', targetEntity: Rendezvous::class)]
    private Collection $rendezvouses;

    #[ORM\Column(length: 255)]
    private ?string $libelle = null;

    public function __construct()
    {
        $this->rendezvouses = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->libelle;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;

        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;

        return $this;
    }

    /**
     * @return Collection<int, Rendezvous>
     */
    public function getRendezvouses(): Collection
    {
        return $this->rendezvouses;
    }

    public function addRendezvouse(Rendezvous $rendezvouse): static
    {
        if (!$this->rendezvouses->contains($rendezvouse)) {
            $this->rendezvouses->add($rendezvouse);
            $rendezvouse->setCreneau($this);
        }

        return $this;
    }

    public function removeRendezvouse(Rendezvous $rendezvouse): static
    {
        if ($this->rendezvouses->removeElement($rendezvouse)) {
            // set the owning side to null (unless already changed)
            if ($rendezvouse->getCreneau() === $this) {
                $rendezvouse->setCreneau(null);
            }
        }

        return $this;
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }
}
