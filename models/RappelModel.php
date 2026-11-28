<?php
/**
 * models/RappelModel.php
 * Entité "rappel de médicament" (table rappel_medicament) : un médicament à prendre
 * chaque jour, N fois par jour (fréquence 1 à 6, une heure par prise), entre une date de
 * début et une date de fin (facultative). La colonne `heure` garde la 1re prise (compatibilité
 * avec l'Espace famille), `heures` la liste complète "08:00,14:00,20:00".
 * Le propriétaire est un compte (userId) OU un profil à charge de l'Espace famille (membreId).
 */
class RappelModel
{
    public const MAX_FREQUENCE = 6;

    private int $id = 0;
    private ?int $userId = null;
    private ?int $membreId = null;
    private string $medicament = '';
    private string $dose = '';
    /** @var string[] heures de prise triées (HH:MM), une par prise quotidienne */
    private array $heures = ['08:00'];
    private string $dateDebut = '';
    private ?string $dateFin = null;
    private string $note = '';
    /** Stock de la boîte : nombre d'unités au moment de stockMaj (null = stock non suivi) */
    private ?int $stock = null;
    private int $unitesParPrise = 1;
    private ?string $stockMaj = null;

    public function getId(): int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function getMembreId(): ?int { return $this->membreId; }
    public function getMedicament(): string { return $this->medicament; }
    public function getDose(): string { return $this->dose; }
    /** 1re prise de la journée */
    public function getHeure(): string { return $this->heures[0]; }
    /** @return string[] toutes les heures de prise, triées */
    public function getHeures(): array { return $this->heures; }
    /** Nombre de prises par jour */
    public function getFrequence(): int { return count($this->heures); }
    public function getDateDebut(): string { return $this->dateDebut; }
    public function getDateFin(): ?string { return $this->dateFin; }
    public function getNote(): string { return $this->note; }
    public function getStock(): ?int { return $this->stock; }
    public function getUnitesParPrise(): int { return $this->unitesParPrise; }
    public function getStockMaj(): ?string { return $this->stockMaj; }

    public function setId(int $id): void { $this->id = $id; }
    public function setUserId(?int $userId): void { $this->userId = $userId; }
    public function setMembreId(?int $membreId): void { $this->membreId = $membreId; }
    public function setMedicament(string $medicament): void { $this->medicament = $medicament; }
    public function setDose(string $dose): void { $this->dose = $dose; }
    /** @param string[] $heures */
    public function setHeures(array $heures): void
    {
        $heures = array_values(array_unique(array_filter(array_map(fn($h) => substr(trim((string) $h), 0, 5), $heures), fn($h) => $h !== '')));
        sort($heures);
        $this->heures = $heures ?: ['08:00'];
    }
    public function setDateDebut(string $date): void { $this->dateDebut = $date; }
    public function setDateFin(?string $date): void { $this->dateFin = $date; }
    public function setNote(string $note): void { $this->note = $note; }
    public function setStock(?int $stock): void { $this->stock = $stock; }
    public function setUnitesParPrise(int $n): void { $this->unitesParPrise = max(1, $n); }
    public function setStockMaj(?string $date): void { $this->stockMaj = $date; }

    /**
     * État du stock, à partir du nombre de prises cochées "pris" depuis la mise à jour du stock.
     * Chaque prise consomme getUnitesParPrise() unités. Retourne null si le stock n'est pas suivi.
     * @return array{left:int, days:int, low:bool, out:bool}|null
     */
    public function stockStatus(int $takenSinceUpdate, int $alertDays): ?array
    {
        if ($this->stock === null) {
            return null;
        }
        $left = max(0, $this->stock - $takenSinceUpdate * $this->unitesParPrise);
        $perDay = $this->getFrequence() * $this->unitesParPrise;
        $days = intdiv($left, max(1, $perDay));
        return [
            'left' => $left,
            'days' => $days,
            'out'  => $left < $this->unitesParPrise,   // plus assez pour une prise
            'low'  => $days <= $alertDays,
        ];
    }

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
        // `heures` = liste complète ; anciennes lignes (sans la colonne ou vide) : l'unique `heure`
        $list = trim((string) ($row['heures'] ?? ''));
        $r->setHeures($list !== '' ? explode(',', $list) : [(string) $row['heure']]);
        $r->setDateDebut((string) $row['date_debut']);
        $r->setDateFin($row['date_fin'] ?? null);
        $r->setNote((string) ($row['note'] ?? ''));
        $r->setStock(isset($row['stock']) && $row['stock'] !== null ? (int) $row['stock'] : null);
        $r->setUnitesParPrise((int) ($row['unites_par_prise'] ?? 1));
        $r->setStockMaj($row['stock_maj'] ?? null);
        return $r;
    }
}
