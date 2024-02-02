<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\RendezvousRepository;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: RendezvousRepository::class)]
#[Vich\Uploadable]
class Rendezvous
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rendezvouses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestation $prestation = null;

    #[Vich\UploadableField(mapping: 'rendezvous', fileNameProperty: 'imageName')]
    private ?File $image = null;

    #[ORM\Column(length: 255)]
    private ?string $ImageName = null;

    
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $day = null;

    #[ORM\ManyToOne(inversedBy: 'rendezvouses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Creneau $creneau = null;

    #[ORM\ManyToOne(inversedBy: 'rendezvouses')]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $User = null;

    #[ORM\OneToOne(mappedBy: 'rendezvou')]
    private ?Payment $payment = null;

    #[ORM\Column(nullable: true)]
    private ?bool $Paid = null;

    #[ORM\ManyToMany(targetEntity: Supplement::class, inversedBy: 'rendezvouses')]
    private Collection $supplement;

    public function __construct()
    {
        $this->supplement = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrestation(): ?Prestation
    {
        return $this->prestation;
    }

    public function setPrestation(?Prestation $prestation): static
    {
        $this->prestation = $prestation;

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
        return $this->ImageName;
    }

    public function setImageName(string $ImageName): static
    {
        $this->ImageName = $ImageName;

        return $this;
    }

    public function getDay(): ?\DateTimeInterface
    {
        return $this->day;
    }

    public function setDay($day): static
    {
        $this->day = $day;

        return $this;
    }

    public function getCreneau(): ?Creneau
    {
        return $this->creneau;
    }

    public function setCreneau(?Creneau $creneau): static
    {
        $this->creneau = $creneau;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->User;
    }

    public function setUser(?User $User): static
    {
        $this->User = $User;

        return $this;
    }

    public function isPaid(): ?bool
    {
        return $this->Paid;
    }

    public function setPaid(?bool $Paid): static
    {
        $this->Paid = $Paid;

        return $this;
    }

    public function getPayment(): ?Payment
	{
		return $this->payment;
	}

	public function setPayment(?Payment $payment): self
                                                	{
                                                		// unset the owning side of the relation if necessary
                                                		if ($payment === null && $this->payment !== null) {
                                                			$this->payment->setRendezvous(null);
                                                		}
                                                
                                                		// set the owning side of the relation if necessary
                                                		if ($payment !== null && $payment->getRendezvous() !== $this) {
                                                			$payment->setRendezvous($this);
                                                		}
                                                
                                                		$this->payment = $payment;
                                                
                                                		return $this;
                                                	}

    /**
     * @return Collection<int, supplement>
     */
    public function getSupplement(): Collection
    {
        return $this->supplement;
    }

    public function addSupplement(supplement $supplement): static
    {
        if (!$this->supplement->contains($supplement)) {
            $this->supplement->add($supplement);
        }

        return $this;
    }

    public function removeSupplement(supplement $supplement): static
    {
        $this->supplement->removeElement($supplement);

        return $this;
    }
}
