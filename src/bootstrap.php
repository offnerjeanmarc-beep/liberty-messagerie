<?php
/**
 * Amorçage commun : charge la config, la connexion DB et les librairies.
 * Inclure ce fichier en tête de chaque script (web ou cron).
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration manquante : copiez config/config.example.php en config/config.php');
}

/** @var array $CONFIG */
$CONFIG = require $configFile;

date_default_timezone_set($CONFIG['app']['timezone'] ?? 'Europe/Paris');

require __DIR__ . '/db.php';
require __DIR__ . '/crypto.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/lodgify.php';
require __DIR__ . '/ai.php';

// Connexion DB partagée
$GLOBALS['CONFIG'] = $CONFIG;
$GLOBALS['DB'] = db_connect($CONFIG['db']);
