<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repo.php';
auth_require();

$PAGE_TITLE = 'Journal technique';
$ACTIVE = 'journal';

function tail_lines(string $file, int $limit = 120): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!$lines) {
        return [];
    }
    return array_slice($lines, -$limit);
}

$logFile = APP_ROOT . '/cron/poll.log';
$logLines = tail_lines($logFile, 120);
$errors = db_all(
    "SELECT * FROM history
     WHERE action = 'error'
     ORDER BY created_at DESC
     LIMIT 50"
);

require __DIR__ . '/_layout.php';
?>
<h1>Journal technique</h1>
<p class="muted">Derniers événements du cron et erreurs IA/Lodgify enregistrées.</p>

<div class="log-grid">
  <section>
    <div class="row-between">
      <h2 class="section-title">Cron Lodgify</h2>
      <span class="muted mono"><?= e($logFile) ?></span>
    </div>
    <?php if (!$logLines): ?>
      <div class="empty">Aucun log cron disponible pour le moment.</div>
    <?php else: ?>
      <pre class="log-box"><?= e(implode("\n", $logLines)) ?></pre>
    <?php endif; ?>
  </section>

  <section>
    <h2 class="section-title">Dernières erreurs</h2>
    <?php if (!$errors): ?>
      <div class="empty">Aucune erreur enregistrée.</div>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Date</th><th>Voyageur</th><th>Erreur</th></tr></thead>
        <tbody>
        <?php foreach ($errors as $e): ?>
          <tr>
            <td class="nowrap muted"><?= e(fmt_date_time($e['created_at'])) ?></td>
            <td><?= e($e['guest_name'] ?: '—') ?></td>
            <td><?= e($e['error_text'] ?: mb_strimwidth((string)$e['body'], 0, 180, '…')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
