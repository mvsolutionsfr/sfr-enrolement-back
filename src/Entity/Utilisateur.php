<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $idAui = null;

    #[ORM\Column(length: 50)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $prenom = null;

    #[ORM\Column]
    private ?bool $admin = null;

     #[ORM\OneToMany(mappedBy: 'utilModifiePar', targetEntity: UtilisateurModifiePar::class, orphanRemoval: true)]
    private Collection $utilisateurModifiePars;

     #[ORM\ManyToOne(inversedBy: 'utilisateurs',cascade: ['persist'])]
     #[ORM\JoinColumn(nullable: false)]
     private ?Enseigne $enseigne = null;

     #[ORM\Column(length: 255, nullable: true)]
     private ?string $ref = null;

     #[ORM\Column]
     private ?bool $actif = null;

    public function __construct()
    {
        $this->utilisateurModifiePars = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdAui(): ?string
    {
        return $this->idAui;
    }

    public function setIdAui(?string $idAui): self
    {
        $this->idAui = $idAui;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function isAdmin(): ?bool
    {
        return $this->admin;
    }

    public function setAdmin(bool $admin): self
    {
        $this->admin = $admin;

        return $this;
    }

    /**
     * @return Collection<int, UtilisateurModifiePar>
     */
    public function getUtilisateurModifiePars(): Collection
    {
        return $this->utilisateurModifiePars;
    }

    public function addUtilisateurModifiePar(UtilisateurModifiePar $utilisateurModifiePar): self
    {
        if (!$this->utilisateurModifiePars->contains($utilisateurModifiePar)) {
            $this->utilisateurModifiePars->add($utilisateurModifiePar);
            $utilisateurModifiePar->setUtilModifiePar($this);
        }

        return $this;
    }

    public function removeUtilisateurModifiePar(UtilisateurModifiePar $utilisateurModifiePar): self
    {
        if ($this->utilisateurModifiePars->removeElement($utilisateurModifiePar)) {
            // set the owning side to null (unless already changed)
            if ($utilisateurModifiePar->getUtilModifiePar() === $this) {
                $utilisateurModifiePar->setUtilModifiePar(null);
            }
        }

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

    public function getRef(): ?string
    {
        return $this->ref;
    }

    public function setRef(?string $ref): self
    {
        $this->ref = $ref;

        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

}
