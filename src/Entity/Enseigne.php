<?php

namespace App\Entity;

use App\Repository\EnseigneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnseigneRepository::class)]
class Enseigne
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $raisonSociale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $referent = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(mappedBy: 'enseigne', targetEntity: Client::class, orphanRemoval: true)]
    private Collection $clients;

    #[ORM\OneToMany(mappedBy: 'enseignes', targetEntity: PgmEnseigne::class, orphanRemoval: true,cascade: ['persist'])]
    private Collection $pgmEnrolements;

    #[ORM\OneToMany(mappedBy: 'enseigne', targetEntity: EnseigneEstModifiePar::class, orphanRemoval: true,cascade: ['persist'])]
    private Collection $enseigneEstModifiePars;

    #[ORM\OneToMany(mappedBy: 'enseigne', targetEntity: Utilisateur::class,cascade: ['persist'])]
    private Collection $utilisateurs;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ref = null;

    #[ORM\OneToMany(mappedBy: 'enseigne', targetEntity: Orders::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
        $this->pgmEnrolements = new ArrayCollection();
        $this->enseigneEstModifiePars = new ArrayCollection();
        $this->utilisateurs = new ArrayCollection();
        $this->orders = new ArrayCollection();
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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): self
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    /**
     * @return Collection<int, Client>
     */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function addClient(Client $client): self
    {
        if (!$this->clients->contains($client)) {
            $this->clients->add($client);
            $client->setEnseigne($this);
        }

        return $this;
    }

    public function removeClient(Client $client): self
    {
        if ($this->clients->removeElement($client)) {
            // set the owning side to null (unless already changed)
            if ($client->getEnseigne() === $this) {
                $client->setEnseigne(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PgmEnseigne>
     */
    public function getPgmEnrolements(): Collection
    {
        return $this->pgmEnrolements;
    }

    public function addPgmEnrolement(PgmEnseigne $pgmEnrolement): self
    {
        if (!$this->pgmEnrolements->contains($pgmEnrolement)) {
            $this->pgmEnrolements->add($pgmEnrolement);
            $pgmEnrolement->setEnseignes($this);
        }

        return $this;
    }

    public function removePgmEnrolement(PgmEnseigne $pgmEnrolement): self
    {
        if ($this->pgmEnrolements->removeElement($pgmEnrolement)) {
            // set the owning side to null (unless already changed)
            if ($pgmEnrolement->getEnseignes() === $this) {
                $pgmEnrolement->setEnseignes(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, EnseigneEstModifiePar>
     */
    public function getEnseigneEstModifiePars(): Collection
    {
        return $this->enseigneEstModifiePars;
    }

    public function addEnseigneEstModifiePar(EnseigneEstModifiePar $enseigneEstModifiePar): self
    {
        if (!$this->enseigneEstModifiePars->contains($enseigneEstModifiePar)) {
            $this->enseigneEstModifiePars->add($enseigneEstModifiePar);
            $enseigneEstModifiePar->setEnseigne($this);
        }

        return $this;
    }

    public function removeEnseigneEstModifiePar(EnseigneEstModifiePar $enseigneEstModifiePar): self
    {
        if ($this->enseigneEstModifiePars->removeElement($enseigneEstModifiePar)) {
            // set the owning side to null (unless already changed)
            if ($enseigneEstModifiePar->getEnseigne() === $this) {
                $enseigneEstModifiePar->setEnseigne(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Utilisateur>
     */
    public function getUtilisateurs(): Collection
    {
        return $this->utilisateurs;
    }

    public function addUtilisateur(Utilisateur $utilisateur): self
    {
        if (!$this->utilisateurs->contains($utilisateur)) {
            $this->utilisateurs->add($utilisateur);
            $utilisateur->setEnseigne($this);
        }

        return $this;
    }

    public function removeUtilisateur(Utilisateur $utilisateur): self
    {
        if ($this->utilisateurs->removeElement($utilisateur)) {
            // set the owning side to null (unless already changed)
            if ($utilisateur->getEnseigne() === $this) {
                $utilisateur->setEnseigne(null);
            }
        }

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
            $order->setEnseigne($this);
        }
        return $this;
    }

    public function removeOrder(Orders $order): self
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getEnseigne() === $this) {
                $order->setEnseigne(null);
            }
        }

        return $this;
    }

}
