<?php
/**
 * models/UserModel.php
 * Représente UN utilisateur LabTrack.
 * Règle MVC du workshop : uniquement des attributs privés + getters/setters.
 * Aucune requête SQL, aucune logique métier.
 */
class UserModel
{
    // ------------------------------------------------------------
    // ATTRIBUTS
    // ------------------------------------------------------------
    private int $id = 0;
    private string $nom = '';
    private string $email = '';
    private string $code = '';          // code personnel à 4 chiffres, unique
    private string $password = '';
    private string $role = 'patient';   // patient | admin
    private string $langue = 'fr';      // fr | ar | en
    private string $createdAt = '';
    private ?string $avatar = null;     // fichier de la photo de profil (null = initiales)

    // ------------------------------------------------------------
    // CONSTRUCTEUR
    // ------------------------------------------------------------
    public function __construct()
    {
    }

    // ------------------------------------------------------------
    // GETTERS
    // ------------------------------------------------------------
    public function getId(): int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getLangue(): string
    {
        return $this->langue;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    // ------------------------------------------------------------
    // SETTERS
    // ------------------------------------------------------------
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function setLangue(string $langue): void
    {
        $this->langue = $langue;
    }

    public function setAvatar(?string $avatar): void
    {
        $this->avatar = ($avatar === null || $avatar === '') ? null : $avatar;
    }

    public function setCreatedAt(string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    // ------------------------------------------------------------
    // CONVERSION D'UNE LIGNE SQL EN OBJET
    // ------------------------------------------------------------
    public static function fromArray(array $row): self
    {
        $user = new self();
        $user->setId((int) ($row['id'] ?? 0));
        $user->setNom((string) ($row['nom'] ?? ''));
        $user->setEmail((string) ($row['email'] ?? ''));
        $user->setCode((string) ($row['code'] ?? ''));
        $user->setPassword((string) ($row['password'] ?? ''));
        $user->setRole((string) ($row['role'] ?? 'patient'));
        $user->setLangue((string) ($row['langue'] ?? 'fr'));
        $user->setCreatedAt((string) ($row['created_at'] ?? ''));
        $user->setAvatar(isset($row['avatar']) ? (string) $row['avatar'] : null);
        return $user;
    }
}
