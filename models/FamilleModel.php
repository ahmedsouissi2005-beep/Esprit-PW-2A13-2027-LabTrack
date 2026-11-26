<?php
/**
 * models/FamilleModel.php
 * Entité "famille" (Espace famille) : un groupe créé par un compte, son gestionnaire.
 * Comme UserModel : attributs privés + getters/setters, la logique reste dans le contrôleur.
 */
class FamilleModel
{
    private int $id = 0;
    private string $nom = '';
    private ?int $createdBy = null;
    private string $createdAt = '';

    public function getId(): int { return $this->id; }
    public function getNom(): string { return $this->nom; }
    public function getCreatedBy(): ?int { return $this->createdBy; }
    public function getCreatedAt(): string { return $this->createdAt; }

    public function setId(int $id): void { $this->id = $id; }
    public function setNom(string $nom): void { $this->nom = $nom; }
    public function setCreatedBy(?int $createdBy): void { $this->createdBy = $createdBy; }
    public function setCreatedAt(string $createdAt): void { $this->createdAt = $createdAt; }

    /** Construit l'objet à partir d'une ligne de la table famille */
    public static function fromArray(array $row): self
    {
        $f = new self();
        $f->setId((int) $row['id']);
        $f->setNom((string) $row['nom']);
        $f->setCreatedBy(isset($row['created_by']) ? (int) $row['created_by'] : null);
        $f->setCreatedAt((string) ($row['created_at'] ?? ''));
        return $f;
    }
}
