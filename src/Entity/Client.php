<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $raisonSociale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referent = null;

    #[ORM\Column]
    private ?bool $actif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne(inversedBy: 'clients')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Enseigne $enseigne = null;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Orders::class)]
    private Collection $orders;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: PgmClient::class, orphanRemoval: true)]
    private Collection $pgmEnrolements;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Enrolement::class, orphanRemoval: true)]
    private Collection $enrolements;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ClientEstModifiePar::class)]
    private Collection $clientEstModifiePar;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $siren = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ref = null;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
        $this->pgmEnrolements = new ArrayCollection();
        $this->enrolements = new ArrayCollection();
        $this->clientEstModifiePar = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRaisonSociale(): ?string
    {
        return $this->raisonSociale;
    }

    public function setRaisonSociale(string $raisonSociale): self
    {
        $this->raisonSociale = $raisonSociale;

        return $this;
    }

    public function getReferent(): ?string
    {
        return $this->referent;
    }

    public function setReferent(?string $referent): self
    {
        $this->referent = $referent;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getEnseigne(): ?Enseigne
    {
        return $this->enseigne;
    }

    public function setEnseigne(?Enseigne $enseigne): self
    {
        $this->enseigne = $enseigne;

        return $this;
    }

    /**
     * @return Collection<int, Orders>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrder(Orders $order): self
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setClient($this);
        }

        return $this;
    }

    public function removeOrder(Orders $order): self
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getClient() === $this) {
                $order->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PgmClient>
     */
    public function getPgmEnrolements(): Collection
    {
        return $this->pgmEnrolements;
    }

    public function addPgmEnrolement(PgmClient $pgmEnrolement): self
    {
        if (!$this->pgmEnrolements->contains($pgmEnrolement)) {
            $this->pgmEnrolements->add($pgmEnrolement);
            $pgmEnrolement->setClient($this);
        }

        return $this;
    }

    public function removePgmEnrolement(PgmClient $pgmEnrolement): self
    {
        if ($this->pgmEnrolements->removeElement($pgmEnrolement)) {
            // set the owning side to null (unless already changed)
            if ($pgmEnrolement->getClient() === $this) {
                $pgmEnrolement->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Enrolement>
     */
    public function getEnrolements(): Collection
    {
        return $this->enrolements;
    }

    public function addEnrolement(Enrolement $enrolement): self
    {
        if (!$this->enrolements->contains($enrolement)) {
            $this->enrolements->add($enrolement);
            $enrolement->setClient($this);
        }

        return $this;
    }

    public function removeEnrolement(Enrolement $enrolement): self
    {
        if ($this->enrolements->removeElement($enrolement)) {
            // set the owning side to null (unless already changed)
            if ($enrolement->getClient() === $this) {
                $enrolement->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ClientEstModifiePar>
     */
    public function getClientEstModifiePar(): Collection
    {
        return $this->clientEstModifiePar;
    }

    public function addClientEstModifiePar(ClientEstModifiePar $clientEstModifiePar): self
    {
        if (!$this->clientEstModifiePar->contains($clientEstModifiePar)) {
            $this->clientEstModifiePar->add($clientEstModifiePar);
            $clientEstModifiePar->setClient($this);
        }

        return $this;
    }

    public function removeClientEstModifiePar(ClientEstModifiePar $clientEstModifiePar): self
    {
        if ($this->clientEstModifiePar->removeElement($clientEstModifiePar)) {
            // set the owning side to null (unless already changed)
            if ($clientEstModifiePar->getClient() === $this) {
                $clientEstModifiePar->setClient(null);
            }
        }

        return $this;
    }

    public function getSiren(): ?string
    {
        return $this->siren;
    }

    public function setSiren(?string $siren): self
    {
        $this->siren = $siren;

        return $this;
    }

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

}
