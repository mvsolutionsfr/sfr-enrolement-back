<?php

namespace App\Entity;

use App\Repository\RequeteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RequeteRepository::class)]
class Requete
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $messageErreur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateModif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $codeErreur = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $statutHttp = null;

    #[ORM\ManyToOne(inversedBy: 'requetes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?OrderTransaction $transaction = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getMessageErreur(): ?string
    {
        return $this->messageErreur;
    }

    public function setMessageErreur(?string $messageErreur): self
    {
        $this->messageErreur = $messageErreur;

        return $this;
    }

    public function getDateModif(): ?\DateTimeInterface
    {
        return $this->dateModif;
    }

    public function setDateModif(\DateTimeInterface $dateModif): self
    {
        $this->dateModif = $dateModif;

        return $this;
    }

    public function getCodeErreur(): ?string
    {
        return $this->codeErreur;
    }

    public function setCodeErreur(?string $codeErreur): self
    {
        $this->codeErreur = $codeErreur;

        return $this;
    }

    public function getStatutHttp(): ?string
    {
        return $this->statutHttp;
    }

    public function setStatutHttp(?string $statutHttp): self
    {
        $this->statutHttp = $statutHttp;

        return $this;
    }

    public function getTransaction(): ?OrderTransaction
    {
        return $this->transaction;
    }

    public function setTransaction(?OrderTransaction $transaction): self
    {
        $this->transaction = $transaction;

        return $this;
    }
}
