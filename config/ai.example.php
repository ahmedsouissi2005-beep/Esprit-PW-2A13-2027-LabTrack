<?php
/**
 * config/ai.example.php
 * Modèle de configuration pour la lecture des rapports d'analyses (module Analyses sanguines).
 *
 * 1. Copier ce fichier en config/ai.local.php (ignoré par Git : jamais publié).
 * 2. Créer une clé API sur https://console.anthropic.com et la coller dans api_key.
 *
 * Sans config/ai.local.php (ni variable d'environnement ANTHROPIC_API_KEY), l'envoi d'un
 * rapport affiche un message d'erreur.
 */
return [
    'api_key' => 'sk-ant-...',
    'model'   => 'claude-sonnet-5-5',
];
