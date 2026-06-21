<?php
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/repo.php';
auth_require();

$PAGE_TITLE = 'Instructions des chatbots';
$ACTIVE = 'instructions';

// Bouton « Synchroniser les logements depuis Lodgify »
$syncMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['do'] ?? '') === 'sync') {
    if (csrf_check($_POST['csrf'] ?? null)) {
        $items = lodgify_list_properties(50);
        $n = 0;
        foreach ($items as $it) {
            $roomId = $it['rooms'][0]['id'] ?? null;
            prop_ensure((int)$it['id'], $roomId ? (int)$roomId : null, (string)$it['name']);
            $n++;
        }
        $syncMsg = "$n logement(s) synchronisé(s) depuis Lodgify.";
    }
}

$props = props_all();
$csrf = csrf_token();
require __DIR__ . '/_layout.php';
?>
<div class="row-between">
  <h1>Instructions des chatbots</h1>
  <form method="post" class="inline">
    <input type="hidden" name="do" value="sync">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <button class="btn">Synchroniser les logements (Lodgify)</button>
  </form>
</div>
<p class="muted">Un chatbot, une instruction et une clé API par logement. Les clés sont chiffrées en base.</p>
<?php if ($syncMsg): ?><div class="alert ok"><?= e($syncMsg) ?></div><?php endif; ?>

<?php if (!$props): ?>
  <div class="empty">Aucun logement enregistré.<br><span class="muted">Cliquez sur « Synchroniser les logements (Lodgify) » pour les importer.</span></div>
<?php endif; ?>

<div class="feed">
<?php foreach ($props as $p):
    $keyPlain = decrypt_secret($p['ai_key_enc'] ?? '');
?>
  <div class="card prop" data-id="<?= (int)$p['id'] ?>">
    <div class="prop-head">
      <div class="conv-meta">
        <div class="conv-name"><?= e($p['name']) ?></div>
        <div class="conv-sub">ID Lodgify <?= (int)$p['lodgify_property_id'] ?></div>
      </div>
      <label class="switch">
        <input type="checkbox" class="f-active" <?= $p['is_active'] ? 'checked' : '' ?>>
        <span>Actif</span>
      </label>
    </div>

    <div class="grid2">
      <div>
        <label>Fournisseur IA</label>
        <select class="f-provider">
          <option value="anthropic" <?= $p['ai_provider']==='anthropic'?'selected':'' ?>>Claude (Anthropic)</option>
          <option value="openai" <?= $p['ai_provider']==='openai'?'selected':'' ?>>OpenAI</option>
        </select>
      </div>
      <div>
        <label>Modèle</label>
        <input type="text" class="f-model" value="<?= e($p['ai_model']) ?>">
      </div>
    </div>

    <div class="grid2">
      <div>
        <label>Clé API du logement <span class="muted">(laisser vide pour ne pas changer)</span></label>
        <input type="text" class="f-key mono" placeholder="<?= e(mask_secret($keyPlain)) ?>" value="">
      </div>
      <div class="auto-wrap">
        <label class="switch">
          <input type="checkbox" class="f-auto" disabled>
          <span>Envoi auto désactivé pour l'instant</span>
        </label>
      </div>
    </div>

    <label>Instruction du chatbot</label>
    <textarea class="f-instruction" rows="10" placeholder="Décrivez le logement, le ton, les infos pratiques (wifi, parking, check-in/out), et ce que le bot ne doit PAS gérer seul…"><?= e($p['instruction'] ?? '') ?></textarea>

    <div class="actions">
      <button class="btn primary act-save-prop">Enregistrer</button>
      <button class="btn act-test">Tester</button>
      <span class="act-status muted"></span>
    </div>

    <div class="test-zone" style="display:none">
      <input type="text" class="test-q" placeholder="Pose une question de voyageur pour tester…">
      <button class="btn act-test-run">Envoyer le test</button>
      <div class="test-out muted"></div>
    </div>
  </div>
<?php endforeach; ?>
</div>

<input type="hidden" id="csrf" value="<?= e($csrf) ?>">
<?php require __DIR__ . '/_footer.php'; ?>
