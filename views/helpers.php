<?php
/**
 * views/helpers.php
 * Petites fonctions d'affichage utilisées par les includes et les partials
 * (atelier Intégration Template : navActive, isActive, isOpen...).
 */

/** Échappe une valeur pour l'afficher sans risque de XSS */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Frontoffice (Novena) : classe "active" sur le lien du menu de la page courante */
function navActive(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'active' : '';
}

/** Backoffice (Registrar) : classe "is-active" sur le lien de la sidebar */
function isActive(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'is-active' : '';
}

/** Backoffice : attribut aria-current pour l'accessibilité du lien actif */
function ariaCurrent(string $page, string $currentPage): string
{
    return $page === $currentPage ? 'aria-current="page"' : '';
}

/** Initiales d'un nom (avatar du backoffice) */
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters !== '' ? $letters : '?';
}
