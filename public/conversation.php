<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repo.php';
auth_require();

$convId = (int)($_GET['id'] ?? 0);
$conv = conv_by_id($convId);
if (!$conv) {
    http_response_code(404);
    exit('Conversation introuvable');
}

conv_mark_read($convId);
$conv = conv_by_id($convId);
$messages = conv_messages($convId);
$property = prop_by_lodgify_id((int)$conv['lodgify_property_id']);
$propertyName = $property['name'] ?? ('Logement ' . $conv['lodgify_property_id']);
$sensitiveHits = [];
foreach ($messages as $m) {
    if ($m['direction'] !== 'guest') {
        continue;
    }
    $hit = needs_escalation((string)($m['body'] ?? ''), $GLOBALS['CONFIG']['escalation_keywords'] ?? []);
    if ($hit !== null && !in_array($hit, $sensitiveHits, true)) {
        $sensitiveHits[] = $hit;
    }
}
$quickReplies = [
    'Wifi' => "Bonjour,\n\nLe wifi est disponible dans le logement. Vous trouverez le nom du réseau et le mot de passe dans les instructions d'arrivée / le livret d'accueil.\n\nBonne journée.",
    'Parking' => "Bonjour,\n\nPour le stationnement, vous pouvez utiliser les informations indiquées dans votre guide d'arrivée. Si vous ne les retrouvez pas, dites-moi où vous êtes garé et je vous aide.\n\nBonne journée.",
    'Arrivée' => "Bonjour,\n\nL'arrivée se fait selon les instructions envoyées avant votre séjour. Je vous invite à les suivre étape par étape, et je reste disponible si vous êtes bloqué.\n\nBonne arrivée.",
    'Départ' => "Bonjour,\n\nPour le départ, merci de bien vérifier que les fenêtres sont fermées, que les lumières sont éteintes et de laisser les clés selon les consignes de départ.\n\nMerci et bonne journée.",
    'Linge' => "Bonjour,\n\nLe linge prévu pour votre séjour est disponible dans le logement. Si quelque chose manque, envoyez-moi une photo ou le détail et je regarde cela rapidement.\n\nBonne journée.",
    'Caution' => "Bonjour,\n\nPour toute question concernant la caution, je vais vérifier les informations de votre réservation avant de vous confirmer précisément la situation.\n\nMerci pour votre patience.",
];

$PAGE_TITLE = 'Conversation';
$ACTIVE = 'inbox';
$csrf = csrf_token();

require __DIR__ . '/_layout.php';
?>
<div class="conversation-page" data-id="<?= (int)$convId ?>">
  <div class="conversation-head">
    <a class="back-link" href="index.php">← Boîte de réception</a>
    <h1><?= e($conv['guest_name'] ?: 'Voyageur') ?></h1>
    <div class="conversation-meta">
      <span><?= e($propertyName) ?></span>
      <span><?= e($conv['channel'] ?: 'Canal inconnu') ?></span>
      <span><?= e(fmt_stay($conv['arrival_date'] ?? null, $conv['departure_date'] ?? null)) ?></span>
      <span>Dernier message : <?= e(fmt_date_time($conv['last_message_date'] ?? null)) ?></span>
    </div>
    <?php if (!empty($conv['ai_reason'])): ?>
      <div class="alert soft"><?= e($conv['ai_reason']) ?></div>
    <?php endif; ?>
    <?php if ($sensitiveHits): ?>
      <div class="alert danger">Attention : message potentiellement sensible détecté (<?= e(implode(', ', $sensitiveHits)) ?>). Relire avec prudence avant envoi.</div>
    <?php endif; ?>
  </div>

  <div class="thread">
    <?php foreach ($messages as $m):
        $isGuest = $m['direction'] === 'guest';
        $label = $isGuest ? ($conv['guest_name'] ?: 'Voyageur') : 'Hôte';
        $date = $m['created_at_remote'] ?? $m['created_at'];
    ?>
      <article class="message-row <?= $isGuest ? 'guest' : 'owner' ?>">
        <div class="message-card">
          <div class="message-meta">
            <strong><?= e($label) ?></strong>
            <span><?= e(fmt_date_time($date)) ?></span>
          </div>
          <div class="message-body"><?= nl2br(e($m['body'] ?? '')) ?></div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <div class="reply-panel">
    <div class="quick-replies">
      <?php foreach ($quickReplies as $label => $template): ?>
        <button type="button" class="quick-btn" data-template="<?= e($template) ?>"><?= e($label) ?></button>
      <?php endforeach; ?>
    </div>
    <label for="reply-text">Réponse</label>
    <textarea id="reply-text" class="draft-text" rows="6" placeholder="Écrire la réponse au voyageur..."><?= e($conv['ai_draft'] ?? '') ?></textarea>
    <div class="actions">
      <button class="btn act-ai">Demander à l'IA</button>
      <button class="btn act-improve">Améliorer ma réponse</button>
      <button class="btn primary act-send">Envoyer</button>
      <button class="btn act-ignore">Ignorer</button>
      <span class="act-status muted"></span>
    </div>
  </div>
</div>

<input type="hidden" id="csrf" value="<?= e($csrf) ?>">
<?php require __DIR__ . '/_footer.php'; ?>
