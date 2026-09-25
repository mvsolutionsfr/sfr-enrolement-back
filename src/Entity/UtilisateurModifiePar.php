<?php

namespace App\Entity;

use App\Repository\UtilisateurModifieParRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurModifieParRepository::class)]
class UtilisateurModifiePar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(inversedBy: 'utilisateurModifiePars')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilModifiePar = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilModifie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;

        return $this;
    }

    public function getUtilModifiePar(): ?Utilisateur
    {
        return $this->utilModifiePar;
    }

    public function setUtilModifiePar(?Utilisateur $utilModifiePar): self
    {
        $this->utilModifiePar = $utilModifiePar;

        return $this;
    }

    public function getUtilModifie(): ?Utilisateur
    {
        return $this->utilModifie;
    }

    public function setUtilModifie(?Utilisateur $utilModifie): self
    {
        $this->utilModifie = $utilModifie;

        return $this;
    }
}
