<?php

namespace App\Entity;

use App\Repository\CategoryPrestationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryPrestationRepository::class)]
class CategoryPrestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $NomCategory = null;

    #[ORM\OneToMany(mappedBy: 'categoryPrestation', targetEntity: Prestation::class)]
    private Collection $prestation;

    public function __construct()
    {
        $this->prestation = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomCategory(): ?string
    {
        return $this->NomCategory;
    }

    public function setNomCategory(string $NomCategory): static
    {
        $this->NomCategory = $NomCategory;

        return $this;
    }

    /**
     * @return Collection<int, Prestation>
     */
    public function getPrestation(): Collection
    {
        return $this->prestation;
    }

    public function addPrestation(Prestation $prestation): static
    {
        if (!$this->prestation->contains($prestation)) {
            $this->prestation->add($prestation);
            $prestation->setCategoryPrestation($this);
        }

        return $this;
    }

    public function removePrestation(Prestation $prestation): static
    {
        if ($this->prestation->removeElement($prestation)) {
            // set the owning side to null (unless already changed)
            if ($prestation->getCategoryPrestation() === $this) {
                $prestation->setCategoryPrestation(null);
            }
        }

        return $this;
    }
}
