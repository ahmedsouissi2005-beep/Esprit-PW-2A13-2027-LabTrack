<?php
/**
 * models/MembreModel.php
 * Entité "membre d'une famille" (table famille_membre). Deux cas :
 *  - un compte LabTrack (userId renseigné) qui a accepté une invitation ;
 *  - un profil à charge (userId NULL) : enfant, grand-parent sans compte,
 *    créé et géré par le gestionnaire de la famille.
 * Les "partages" sont le consentement du membre : ce que la famille peut voir.
 */
class MembreModel
{
    private int $id = 0;
    private int $familleId = 0;
    private ?int $userId = null;
    private string $nom = '';
    private ?string $avatar = null;
    private string $relation = 'autre';
    private ?string $dateNaissance = null;
    private string $role = 'member';
    private array $partages = ['analyses' => false, 'dossier' => false, 'rappels' => false, 'rdv' => false];
    private string $joinedAt = '';

    public function getId(): int { return $this->id; }
    public function getFamilleId(): int { return $this->familleId; }
    public function getUserId(): ?int { return $this->userId; }
    public function getNom(): string { return $this->nom; }
    public function getAvatar(): ?string { return $this->avatar; }
    public function getRelation(): string { return $this->relation; }
    public function getDateNaissance(): ?string { return $this->dateNaissance; }
    public function getRole(): string { return $this->role; }
    public function getPartages(): array { return $this->partages; }
    public function getJoinedAt(): string { return $this->joinedAt; }

    public function setId(int $id): void { $this->id = $id; }
    public function setFamilleId(int $familleId): void { $this->familleId = $familleId; }
    public function setUserId(?int $userId): void { $this->userId = $userId; }
    public function setNom(string $nom): void { $this->nom = $nom; }
    public function setAvatar(?string $avatar): void { $this->avatar = $avatar; }
    public function setRelation(string $relation): void { $this->relation = $relation; }
    public function setDateNaissance(?string $date): void { $this->dateNaissance = $date; }
    public function setRole(string $role): void { $this->role = $role; }
    public function setPartages(array $partages): void { $this->partages = $partages; }
    public function setJoinedAt(string $joinedAt): void { $this->joinedAt = $joinedAt; }

    /** Profil à charge (sans compte LabTrack) */
    public function isDependent(): bool { return $this->userId === null; }

    /** Le membre a-t-il accepté de partager ce type de données ? (un profil à charge : toujours) */
    public function shares(string $type): bool
    {
        return $this->isDependent() || !empty($this->partages[$type]);
    }

    /** Âge en années (profils à charge), ou null si la date de naissance est inconnue */
    public function getAge(): ?int
    {
        if ($this->dateNaissance === null || $this->dateNaissance === '') {
            return null;
        }
        return (new DateTime($this->dateNaissance))->diff(new DateTime('today'))->y;
    }

    /** Ligne de famille_membre jointe à utilisateur (nom et photo du compte) */
    public static function fromArray(array $row): self
    {
        $m = new self();
        $m->setId((int) $row['id']);
        $m->setFamilleId((int) $row['famille_id']);
        $m->setUserId($row['user_id'] !== null ? (int) $row['user_id'] : null);
        $m->setNom((string) ($row['user_nom'] ?? $row['nom'] ?? ''));
        $m->setAvatar($row['user_avatar'] ?? null);
        $m->setRelation((string) $row['relation']);
        $m->setDateNaissance($row['date_naissance'] ?? null);
        $m->setRole((string) $row['role']);
        $m->setPartages([
            'analyses' => (bool) $row['share_analyses'],
            'dossier'  => (bool) $row['share_dossier'],
            'rappels'  => (bool) $row['share_rappels'],
            'rdv'      => (bool) $row['share_rdv'],
        ]);
        $m->setJoinedAt((string) ($row['joined_at'] ?? ''));
        return $m;
    }
}
