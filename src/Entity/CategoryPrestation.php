<?php

namespace App\Entity;

use App\Repository\CategoryPrestationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: CategoryPrestationRepository::class)]
#[Vich\Uploadable]
class CategoryPrestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $NomCategory = null;

    #[Vich\UploadableField(mapping: 'category_prestation', fileNameProperty: 'imageName')]
    private ?File $image = null;

    #[ORM\Column(length: 255)]
    private ?string $imageName = null;

    #[ORM\OneToMany(mappedBy: 'categoryPrestation', targetEntity: Prestation::class, cascade: ['persist', 'remove'])]
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

    public function getImage(): ?File
    {
        return $this->image;
    }

    public function setImage(File $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(string $imageName): static
    {
        $this->imageName = $imageName;

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
