<?php
/**
 * models/RendezVousModel.php
 * Entité "rendez-vous médical" (table rendez_vous) : médecin, laboratoire, vaccin...
 * Statut : prévu, fait ou annulé. Le propriétaire est un compte OU un profil à charge.
 */
class RendezVousModel
{
    public const STATUTS = ['prevu', 'fait', 'annule'];

    private int $id = 0;
    private ?int $userId = null;
    private ?int $membreId = null;
    private string $titre = '';
    private string $dateRdv = '';
    private string $lieu = '';
    private string $medecin = '';
    private string $note = '';
    private string $statut = 'prevu';

    public function getId(): int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function getMembreId(): ?int { return $this->membreId; }
    public function getTitre(): string { return $this->titre; }
    public function getDateRdv(): string { return $this->dateRdv; }
    public function getLieu(): string { return $this->lieu; }
    public function getMedecin(): string { return $this->medecin; }
    public function getNote(): string { return $this->note; }
    public function getStatut(): string { return $this->statut; }

    public function setId(int $id): void { $this->id = $id; }
    public function setUserId(?int $userId): void { $this->userId = $userId; }
    public function setMembreId(?int $membreId): void { $this->membreId = $membreId; }
    public function setTitre(string $titre): void { $this->titre = $titre; }
    public function setDateRdv(string $date): void { $this->dateRdv = $date; }
    public function setLieu(string $lieu): void { $this->lieu = $lieu; }
    public function setMedecin(string $medecin): void { $this->medecin = $medecin; }
    public function setNote(string $note): void { $this->note = $note; }
    public function setStatut(string $statut): void { $this->statut = $statut; }

    public function getOwnerKey(): string
    {
        return $this->membreId !== null ? 'm' . $this->membreId : 'u' . $this->userId;
    }

    public function getDate(): string { return substr($this->dateRdv, 0, 10); }
    public function getTime(): string { return substr($this->dateRdv, 11, 5); }

    /** Rendez-vous prévu et pas encore passé */
    public function isUpcoming(): bool
    {
        return $this->statut === 'prevu' && $this->dateRdv >= date('Y-m-d H:i:s');
    }

    /** Jours restants avant le rendez-vous (0 = aujourd'hui) */
    public function daysLeft(): int
    {
        return (int) (new DateTime('today'))->diff(new DateTime($this->getDate()))->format('%r%a');
    }

    public static function fromArray(array $row): self
    {
        $r = new self();
        $r->setId((int) $row['id']);
        $r->setUserId($row['user_id'] !== null ? (int) $row['user_id'] : null);
        $r->setMembreId($row['membre_id'] !== null ? (int) $row['membre_id'] : null);
        $r->setTitre((string) $row['titre']);
        $r->setDateRdv((string) $row['date_rdv']);
        $r->setLieu((string) ($row['lieu'] ?? ''));
        $r->setMedecin((string) ($row['medecin'] ?? ''));
        $r->setNote((string) ($row['note'] ?? ''));
        $r->setStatut((string) ($row['statut'] ?? 'prevu'));
        return $r;
    }
}
