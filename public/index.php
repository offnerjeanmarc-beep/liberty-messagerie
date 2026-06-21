<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repo.php';
auth_require();

$PAGE_TITLE = 'Boîte de réception';
$ACTIVE = 'inbox';

$propNames = [];
foreach (props_all() as $p) {
    $propNames[(string)$p['lodgify_property_id']] = $p['name'];
}

$rows = db_all(
    "SELECT * FROM conversations
     WHERE status IN ('new', 'pending_review', 'drafted')
     ORDER BY read_at IS NULL DESC, COALESCE(last_message_date, updated_at) DESC
     LIMIT 100"
);

$statPending = stat_pending();
$statAuto = stat_auto_today();
$cronLastAt = setting_get('cron_last_run_at');
$cronStatus = setting_get('cron_last_status', 'inconnu');
$cronCount = setting_get('cron_last_processed', '0');
$cronLabel = $cronLastAt ? date('d/m/Y H:i', strtotime($cronLastAt)) : 'jamais';

require __DIR__ . '/_layout.php';
?>
<div class="stats">
  <div class="stat"><div class="stat-l">À traiter</div><div class="stat-v warn"><?= (int)$statPending ?></div></div>
  <div class="stat"><div class="stat-l">Réponses envoyées aujourd'hui</div><div class="stat-v ok"><?= (int)$statAuto ?></div></div>
  <div class="stat"><div class="stat-l">Logements actifs</div><div class="stat-v"><?= (int)db_val('SELECT COUNT(*) FROM properties WHERE is_active=1') ?></div></div>
  <div class="stat"><div class="stat-l">Dernier cron</div><div class="stat-v small"><?= e($cronLabel) ?></div><div class="stat-note"><?= e($cronStatus) ?> · <?= (int)$cronCount ?> traité(s)</div></div>
</div>

<h1>Boîte de réception</h1>

<?php if (!$rows): ?>
  <div class="empty">Aucune conversation à traiter.<br>
  <span class="muted">Les nouveaux messages voyageurs apparaîtront ici après le passage du cron.</span></div>
<?php endif; ?>

<div class="inbox-list">
<?php foreach ($rows as $c):
    $pname = $propNames[(string)$c['lodgify_property_id']] ?? ('Logement ' . $c['lodgify_property_id']);
    $lastGuestMsg = db_one("SELECT body, created_at_remote, created_at FROM messages WHERE conversation_id=? AND direction='guest' ORDER BY id DESC LIMIT 1", [$c['id']]);
    $isUnread = empty($c['read_at']);
    $preview = trim((string)($lastGuestMsg['body'] ?? $c['ai_reason'] ?? ''));
    $msgDate = $lastGuestMsg['created_at_remote'] ?? $c['last_message_date'] ?? $c['updated_at'];
?>
  <a class="inbox-item <?= $isUnread ? 'unread' : '' ?>" href="conversation.php?id=<?= (int)$c['id'] ?>">
    <div class="inbox-main">
      <div class="inbox-top">
        <span class="inbox-guest"><?= e($c['guest_name'] ?: 'Voyageur') ?></span>
        <span class="inbox-time"><?= e(fmt_date_time($msgDate)) ?></span>
      </div>
      <div class="inbox-property"><?= e($pname) ?></div>
      <div class="inbox-stay"><?= e(fmt_stay($c['arrival_date'] ?? null, $c['departure_date'] ?? null)) ?></div>
      <div class="inbox-preview"><?= e(mb_strimwidth($preview, 0, 140, '…')) ?></div>
    </div>
    <div class="inbox-side">
      <?php if ($isUnread): ?><span class="badge unread-badge">Non lu</span><?php endif; ?>
      <span class="badge warn">À traiter</span>
      <?php if (!empty($c['ai_draft'])): ?><span class="badge info">IA prête</span><?php endif; ?>
    </div>
  </a>
<?php endforeach; ?>
</div>

<?php require __DIR__ . '/_footer.php'; ?>
