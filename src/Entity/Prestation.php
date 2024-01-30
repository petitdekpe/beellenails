<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PrestationRepository;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: PrestationRepository::class)]
#[Vich\Uploadable]
class Prestation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Title = null;

    #[ORM\Column]
    private ?int $price = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[Vich\UploadableField(mapping: 'prestation', fileNameProperty: 'imageName')]
    private ?File $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[ORM\OneToMany(mappedBy: 'prestation', targetEntity: Rendezvous::class)]
    private Collection $rendezvouses;

    #[ORM\ManyToOne(inversedBy: 'prestation', cascade: ['persist', 'remove'])]
    private ?CategoryPrestation $categoryPrestation = null;

    #[ORM\Column(length: 800, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 800, nullable: true)]
    private ?string $inclus = null;

    public function __construct()
    {
        $this->rendezvouses = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->Title;
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->Title;
    }

    public function setTitle(string $Title): static
    {
        $this->Title = $Title;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price;
    }

    public function setPrice(int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

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
            $rendezvouse->setPrestation($this);
        }

        return $this;
    }

    public function removeRendezvouse(Rendezvous $rendezvouse): static
    {
        if ($this->rendezvouses->removeElement($rendezvouse)) {
            // set the owning side to null (unless already changed)
            if ($rendezvouse->getPrestation() === $this) {
                $rendezvouse->setPrestation(null);
            }
        }

        return $this;
    }

    public function getCategoryPrestation(): ?CategoryPrestation
    {
        return $this->categoryPrestation;
    }

    public function setCategoryPrestation(?CategoryPrestation $categoryPrestation): static
    {
        $this->categoryPrestation = $categoryPrestation;

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

    public function getInclus(): ?string
    {
        return $this->inclus;
    }

    public function setInclus(?string $inclus): static
    {
        $this->inclus = $inclus;

        return $this;
    }

}
