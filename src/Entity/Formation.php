<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\FormationRepository;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Vich\UploaderBundle\Mapping\Annotation\UploadableField;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[Vich\Uploadable]
class Formation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $Nom = null;

    #[ORM\Column(length: 700, nullable: true)]
    private ?string $Prérequis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $Objectif = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $Suivi = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $Programme = null;

    #[ORM\Column]
    private ?int $Cout = null;

    #[UploadableField(mapping: 'formation', fileNameProperty: 'imageName')]
    private ?File $image = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $Description = null;

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

    public function getPrérequis(): ?string
    {
        return $this->Prérequis;
    }

    public function setPrérequis(?string $Prérequis): static
    {
        $this->Prérequis = $Prérequis;

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

    public function getDescription(): ?string
    {
        return $this->Description;
    }

    public function setDescription(string $Description): static
    {
        $this->Description = $Description;

        return $this;
    }
}
