<?php
/**
 * config/database.php
 * Connexion PDO sécurisée - patron de conception Singleton
 * (une seule connexion partagée par toute l'application)
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function getConnexion(): PDO
    {
        if (!isset(self::$pdo)) {
            try {
                self::$pdo = new PDO(
                    // --- À adapter à votre configuration MySQL / phpMyAdmin ---
                    'mysql:host=localhost;dbname=labtrack;charset=utf8mb4',
                    'root',
                    '', // mot de passe MySQL si nécessaire
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false, // vraies requêtes préparées (anti injection SQL)
                    ]
                );
            } catch (Exception $e) {
                die('Erreur: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
}
