<?php
/**
 * Configuration de l'application « Messagerie IA — Conciergerie ».
 *
 * COPIEZ ce fichier en `config/config.php` puis remplissez vos valeurs.
 * Le fichier config.php est ignoré par Git (voir .gitignore) : vos secrets
 * ne partent donc JAMAIS sur GitHub.
 *
 * Sur cPanel, placez le dossier du projet en dehors de public_html si
 * possible, et faites pointer le Document Root du domaine/sous-domaine vers
 * le sous-dossier `public/`.
 */

return [

    // --- Base de données MySQL (créée depuis cPanel > MySQL Databases) ---
    'db' => [
        'host'    => 'localhost',
        'name'    => 'cpaneluser_messagerie',   // nom de la base
        'user'    => 'cpaneluser_msg',          // utilisateur MySQL
        'pass'    => 'MOT_DE_PASSE_MYSQL',       // mot de passe MySQL
        'charset' => 'utf8mb4',
    ],

    // --- Clé de chiffrement des clés API stockées en base ---
    // Générez une valeur aléatoire de 64 caractères hex, par ex. :
    //   php -r "echo bin2hex(random_bytes(32));"
    'app_key' => 'REMPLACEZ_PAR_64_CARACTERES_HEXADECIMAUX_ALEATOIRES',

    // --- Compte administrateur (accès au tableau de bord) ---
    // Générez le hash du mot de passe ainsi :
    //   php -r "echo password_hash('VotreMotDePasse', PASSWORD_DEFAULT);"
    'admin' => [
        'user'          => 'admin',
        'password_hash' => '$2y$10$REMPLACEZ_PAR_VOTRE_HASH_BCRYPT',
    ],

    // --- Clé API Lodgify (commune au compte) ---
    'lodgify' => [
        'api_key'  => 'VOTRE_CLE_API_LODGIFY',
        'base_url' => 'https://api.lodgify.com',
    ],

    // --- Paramètres IA par défaut (surchargés par logement) ---
    'ai' => [
        // 'anthropic' (Claude) ou 'openai'
        'default_provider' => 'anthropic',
        'default_model'    => 'claude-haiku-4-5-20251001',
        'max_tokens'       => 700,
        'temperature'      => 0.3,
    ],

    // --- Règles d'escalade : ces mots forcent la VALIDATION humaine ---
    // (insensibles à la casse, recherche par inclusion)
    'escalation_keywords' => [
        'rembours', 'remboursement', 'refund', 'avocat', 'litige', 'plainte',
        'remise', 'geste commercial', 'réduction', 'reduction', 'dédommage',
        'dedommage', 'annul', 'cancel', 'police', 'urgence', 'urgent',
        'dégât', 'degat', 'dégât des eaux', 'cassé', 'casse', 'fuite',
        'sang', 'blessé', 'blesse', 'caution', 'dispute', 'insatisfait',
    ],

    // --- Divertissement / sécurité ---
    'app' => [
        'name'      => 'Messagerie IA — Conciergerie',
        'timezone'  => 'Europe/Paris',
        // Fréquence du cron (information affichée seulement)
        'poll_note' => 'Cron recommandé : toutes les 1 à 2 minutes',
        // Nombre de réservations récentes scannées à chaque passage du cron
        'poll_bookings_size' => 50,
    ],
];
