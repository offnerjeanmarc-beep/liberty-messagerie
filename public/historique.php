<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repo.php';
auth_require();

$PAGE_TITLE = 'Historique';
$ACTIVE = 'history';

$propNames = [];
foreach (props_all() as $p) {
    $propNames[(string)$p['lodgify_property_id']] = $p['name'];
}

$filter = (string)($_GET['prop'] ?? '');
$params = [];
$where = '';
if ($filter !== '') {
    $where = 'WHERE lodgify_property_id = ?';
    $params[] = (int)$filter;
}
$rows = db_all("SELECT * FROM history $where ORDER BY created_at DESC LIMIT 300", $params);

$labels = [
    'auto_sent'  => ['Auto', 'badge ok'],
    'human_sent' => ['Validé', 'badge info'],
    'ignored'    => ['Ignoré', 'badge'],
    'error'      => ['Erreur', 'badge err'],
];

require __DIR__ . '/_layout.php';
?>
<h1>Historique des conversations</h1>

<form method="get" class="inline">
  <select name="prop" onchange="this.form.submit()">
    <option value="">Tous les logements</option>
    <?php foreach ($propNames as $pid => $pn): ?>
      <option value="<?= e($pid) ?>" <?= $filter===(string)$pid?'selected':'' ?>><?= e($pn) ?></option>
    <?php endforeach; ?>
  </select>
</form>

<?php if (!$rows): ?>
  <div class="empty">Aucune entrée pour le moment.</div>
<?php else: ?>
<table class="table">
  <thead><tr><th>Date</th><th>Voyageur</th><th>Logement</th><th>Type</th><th>Message envoyé</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r):
      $lab = $labels[$r['action']] ?? [$r['action'], 'badge'];
      $pname = $propNames[(string)$r['lodgify_property_id']] ?? ('Logement ' . $r['lodgify_property_id']);
  ?>
    <tr>
      <td class="nowrap muted"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
      <td><?= e($r['guest_name'] ?: '—') ?></td>
      <td class="muted"><?= e($pname) ?></td>
      <td><span class="<?= e($lab[1]) ?>"><?= e($lab[0]) ?></span></td>
      <td class="msg-cell"><?= nl2br(e(mb_strimwidth((string)($r['body'] ?? ($r['error_text'] ?? '')), 0, 220, '…'))) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php require __DIR__ . '/_footer.php'; ?>
