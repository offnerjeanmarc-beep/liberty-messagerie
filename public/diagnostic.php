<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repo.php';
auth_require();

$PAGE_TITLE = 'Diagnostic';
$ACTIVE = 'diagnostic';

function diag_row(string $label, bool $ok, string $detail = '', bool $warn = false): array
{
    return [
        'label' => $label,
        'state' => $ok ? 'ok' : ($warn ? 'warn' : 'err'),
        'detail' => $detail,
    ];
}

$checks = [];
$checks[] = diag_row('Version PHP', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION, true);

foreach (['curl', 'pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json'] as $ext) {
    $loaded = extension_loaded($ext);
    $checks[] = diag_row('Extension ' . $ext, $loaded, $loaded ? 'chargée' : 'manquante');
}

$configPath = APP_ROOT . '/config/config.php';
$checks[] = diag_row('Fichier config/config.php', is_file($configPath), is_file($configPath) ? 'présent' : 'absent');

$appKey = (string)($GLOBALS['CONFIG']['app_key'] ?? '');
$appKeyOk = (bool)preg_match('/^[a-f0-9]{64}$/i', $appKey);
$checks[] = diag_row('Clé app_key', $appKeyOk, $appKeyOk ? 'format 64 hex OK' : 'format à corriger', true);

$lodgifyKey = (string)($GLOBALS['CONFIG']['lodgify']['api_key'] ?? '');
$lodgifyOk = $lodgifyKey !== '' && stripos($lodgifyKey, 'VOTRE_') === false;
$checks[] = diag_row('Clé API Lodgify', $lodgifyOk, $lodgifyOk ? 'renseignée' : 'non renseignée', true);

try {
    db_val('SELECT 1');
    $checks[] = diag_row('Connexion MySQL', true, 'OK');
} catch (Throwable $e) {
    $checks[] = diag_row('Connexion MySQL', false, $e->getMessage());
}

$requiredTables = ['properties', 'conversations', 'messages', 'history', 'settings'];
foreach ($requiredTables as $table) {
    try {
        db_val('SELECT COUNT(*) FROM ' . $table . ' LIMIT 1');
        $checks[] = diag_row('Table ' . $table, true, 'OK');
    } catch (Throwable $e) {
        $checks[] = diag_row('Table ' . $table, false, 'absente ou inaccessible');
    }
}

foreach (['arrival_date', 'departure_date', 'read_at'] as $column) {
    try {
        $exists = db_one('SHOW COLUMNS FROM conversations LIKE ?', [$column]);
        $checks[] = diag_row('Colonne conversations.' . $column, (bool)$exists, $exists ? 'OK' : 'lancer sql/upgrade-2026-06-21-conversation-view.sql');
    } catch (Throwable $e) {
        $checks[] = diag_row('Colonne conversations.' . $column, false, 'controle impossible');
    }
}

$cronDir = APP_ROOT . '/cron';
$cronWritable = is_writable($cronDir);
$checks[] = diag_row('Écriture cron/', $cronWritable, $cronWritable ? 'poll.log peut être créé' : 'dossier non inscriptible', true);

$docRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
$publicRoot = realpath(__DIR__);
$docRootOk = $docRoot === $publicRoot;
$checks[] = diag_row('Document Root', $docRootOk, $docRootOk ? 'pointe vers public/' : 'doit pointer vers public/', true);

$cronLast = setting_get('cron_last_run_at');
$checks[] = diag_row(
    'Dernier cron',
    $cronLast !== null,
    $cronLast ? date('d/m/Y H:i:s', strtotime($cronLast)) . ' - ' . setting_get('cron_last_detail', '') : 'pas encore exécuté',
    true
);

require __DIR__ . '/_layout.php';
?>
<h1>Diagnostic de déploiement</h1>
<p class="muted">Contrôle rapide de l'environnement cPanel, de la base MySQL et du cron.</p>

<table class="table">
  <thead><tr><th>Contrôle</th><th>Statut</th><th>Détail</th></tr></thead>
  <tbody>
  <?php foreach ($checks as $c): ?>
    <tr>
      <td><?= e($c['label']) ?></td>
      <td class="nowrap">
        <?php if ($c['state'] === 'ok'): ?>
          <span class="check-ok">OK</span>
        <?php elseif ($c['state'] === 'warn'): ?>
          <span class="check-warn">À vérifier</span>
        <?php else: ?>
          <span class="check-err">Erreur</span>
        <?php endif; ?>
      </td>
      <td class="muted"><?= e($c['detail']) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php require __DIR__ . '/_footer.php'; ?>
