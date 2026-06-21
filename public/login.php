<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
auth_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim((string)($_POST['user'] ?? ''));
    $p = (string)($_POST['pass'] ?? '');
    if (auth_check_login($u, $p)) {
        auth_login($u);
        header('Location: index.php');
        exit;
    }
    $error = 'Identifiants incorrects.';
}
if (auth_is_logged()) { header('Location: index.php'); exit; }
$appName = $GLOBALS['CONFIG']['app']['name'] ?? 'Messagerie IA';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Connexion — <?= e($appName) ?></title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
<form class="login-card" method="post" autocomplete="off">
  <div class="login-brand"><span class="dot"></span> <?= e($appName) ?></div>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
  <label>Identifiant</label>
  <input type="text" name="user" required autofocus>
  <label>Mot de passe</label>
  <input type="password" name="pass" required>
  <button type="submit" class="btn primary">Se connecter</button>
</form>
</body>
</html>
