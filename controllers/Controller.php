<?php
/**
 * controllers/Controller.php
 * Contrôleur de base (atelier Intégration Template, partie 2).
 * Il fait le lien entre les contrôleurs et les vues :
 *  - render()      : BACKOFFICE. Capture la page dans $content puis inclut
 *                    views/back/layout.php, qui assemble les partials.
 *  - renderFront() : FRONTOFFICE. Inclut la page, qui assemble elle-même
 *                    ses includes (head, spinner, navbar, footer, scripts).
 */
abstract class Controller
{
    /** Clé de la page courante (sert à activer le bon lien du menu) */
    protected string $page = 'dashboard';

    /** Titre affiché dans l'onglet du navigateur */
    protected string $title = 'LabTrack';

    /** Données communes à toutes les vues : $t, $lang, $dir, $csrfToken */
    private static array $shared = [];

    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /**
     * Exécute un fichier de vue et renvoie le HTML produit au lieu de l'afficher.
     */
    protected function capture(string $file, array $data = []): string
    {
        extract(self::$shared);
        extract($data);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }

    /**
     * BACKOFFICE : affiche une page de views/back/pages/ dans le layout Registrar.
     *
     * @param string      $view        nom du fichier de la page (sans .php)
     * @param array       $data        variables transmises à la page
     * @param string|null $scriptsView fichier de scripts propre à la page (sans .php)
     */
    protected function render(string $view, array $data = [], ?string $scriptsView = null): void
    {
        $content = $this->capture(__DIR__ . '/../views/back/pages/' . $view . '.php', $data);
        $extraScripts = $scriptsView
            ? $this->capture(__DIR__ . '/../views/back/pages/' . $scriptsView . '.php', $data)
            : '';
        $title = $data['title'] ?? $this->title;
        $currentPage = $data['currentPage'] ?? $this->page;

        extract(self::$shared);
        extract($data);
        require __DIR__ . '/../views/back/layout.php';
    }

    /**
     * FRONTOFFICE : affiche une page de views/front/ (template Novena).
     */
    protected function renderFront(string $view, array $data = []): void
    {
        extract(self::$shared);
        extract($data);
        $pageTitle = $data['pageTitle'] ?? $this->title;
        $currentPage = $data['currentPage'] ?? $this->page;
        require __DIR__ . '/../views/front/' . $view . '.php';
    }

    /** Redirection vers une page de l'application, puis arrêt du script */
    protected function redirect(string $page, string $query = ''): void
    {
        header('Location: index.php?page=' . $page . ($query !== '' ? '&' . $query : ''));
        exit;
    }
}
