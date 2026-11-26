<?php
/**
 * models/RappelModel.php
 * Entité "rappel de médicament" (table rappel_medicament) : un médicament à prendre
 * chaque jour à une heure donnée, entre une date de début et une date de fin (facultative).
 * Le propriétaire est un compte (userId) OU un profil à charge de l'Espace famille (membreId).
 */
class RappelModel
{
    private int $id = 0;
    private ?int $userId = null;
    private ?int $membreId = null;
    private string $medicament = '';
    private string $dose = '';
    private string $heure = '08:00';
    private string $dateDebut = '';
    private ?string $dateFin = null;
    private string $note = '';

    public function getId(): int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function getMembreId(): ?int { return $this->membreId; }
    public function getMedicament(): string { return $this->medicament; }
    public function getDose(): string { return $this->dose; }
    public function getHeure(): string { return $this->heure; }
    public function getDateDebut(): string { return $this->dateDebut; }
    public function getDateFin(): ?string { return $this->dateFin; }
    public function getNote(): string { return $this->note; }

    public function setId(int $id): void { $this->id = $id; }
    public function setUserId(?int $userId): void { $this->userId = $userId; }
    public function setMembreId(?int $membreId): void { $this->membreId = $membreId; }
    public function setMedicament(string $medicament): void { $this->medicament = $medicament; }
    public function setDose(string $dose): void { $this->dose = $dose; }
    public function setHeure(string $heure): void { $this->heure = $heure; }
    public function setDateDebut(string $date): void { $this->dateDebut = $date; }
    public function setDateFin(?string $date): void { $this->dateFin = $date; }
    public function setNote(string $note): void { $this->note = $note; }

    /** Clé du propriétaire : "u" (le compte connecté) ou "m12" (profil à charge n°12) */
    public function getOwnerKey(): string
    {
        return $this->membreId !== null ? 'm' . $this->membreId : 'u' . $this->userId;
    }

    /** Le traitement est-il en cours à cette date (AAAA-MM-JJ) ? */
    public function isActiveOn(string $date): bool
    {
        return $date >= $this->dateDebut && ($this->dateFin === null || $date <= $this->dateFin);
    }

    public static function fromArray(array $row): self
    {
        $r = new self();
        $r->setId((int) $row['id']);
        $r->setUserId($row['user_id'] !== null ? (int) $row['user_id'] : null);
        $r->setMembreId($row['membre_id'] !== null ? (int) $row['membre_id'] : null);
        $r->setMedicament((string) $row['medicament']);
        $r->setDose((string) ($row['dose'] ?? ''));
        $r->setHeure(substr((string) $row['heure'], 0, 5));
        $r->setDateDebut((string) $row['date_debut']);
        $r->setDateFin($row['date_fin'] ?? null);
        $r->setNote((string) ($row['note'] ?? ''));
        return $r;
    }
}
