<?php
/**
 * Entête + navigation communs. Définir $PAGE_TITLE et $ACTIVE avant d'inclure.
 */
$ACTIVE = $ACTIVE ?? '';
$appName = $GLOBALS['CONFIG']['app']['name'] ?? 'Messagerie IA';
function nav_class(string $a, string $active): string { return $a === $active ? 'nav-link active' : 'nav-link'; }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($PAGE_TITLE ?? $appName) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand"><span class="dot"></span> <?= e($appName) ?></div>
  <nav class="nav">
    <a class="<?= nav_class('inbox', $ACTIVE) ?>" href="index.php">Boîte de réception</a>
    <a class="<?= nav_class('instructions', $ACTIVE) ?>" href="instructions.php">Instructions des chatbots</a>
    <a class="<?= nav_class('history', $ACTIVE) ?>" href="historique.php">Historique</a>
    <a class="<?= nav_class('diagnostic', $ACTIVE) ?>" href="diagnostic.php">Diagnostic</a>
    <a class="<?= nav_class('journal', $ACTIVE) ?>" href="journal.php">Journal</a>
  </nav>
  <a class="logout" href="logout.php">Déconnexion</a>
</header>
<main class="container">
