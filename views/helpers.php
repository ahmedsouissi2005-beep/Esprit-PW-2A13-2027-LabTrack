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

/**
 * Rond de profil : la photo du compte si elle existe, sinon ses initiales.
 * L'image passe par index.php?page=avatar (contrôle d'accès), jamais par un lien direct.
 */
function avatarHtml(UserModel $user, string $extraClass = ''): string
{
    $class = trim('lt-avatar ' . $extraClass);
    $avatar = $user->getAvatar();
    if ($avatar !== null) {
        $src = 'index.php?page=avatar&id=' . $user->getId() . '&v=' . substr($avatar, 0, 8);
        return '<img src="' . e($src) . '" alt="' . e($user->getNom()) . '" class="' . e($class) . ' lt-avatar-img">';
    }
    return '<div class="' . e($class) . '">' . e(initials($user->getNom())) . '</div>';
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

/**
 * Attributs à placer sur un <form> pour activer la validation JavaScript
 * (assets/js/form-validation.js) avec les messages dans la langue courante.
 */
function validationAttrs(array $t): string
{
    $messages = [
        'required' => 'err_field_required',
        'email'    => 'err_email',
        'nom'      => 'err_nom',
        'length'   => 'err_password_length',
        'match'    => 'err_password_match',
        'photo-type' => 'err_photo_type',
        'photo-size' => 'err_photo_size',
    ];
    $html = 'novalidate data-validate';
    foreach ($messages as $name => $key) {
        $html .= ' data-msg-' . $name . '="' . e($t[$key] ?? '') . '"';
    }
    return $html;
}
