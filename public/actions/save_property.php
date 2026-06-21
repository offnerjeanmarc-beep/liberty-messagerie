<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/repo.php';
auth_start();
if (!auth_is_logged()) json_out(['ok' => false, 'error' => 'non connecté'], 401);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_check($in['csrf'] ?? null)) json_out(['ok' => false, 'error' => 'CSRF'], 403);

$id = (int)($in['id'] ?? 0);
$prop = db_one('SELECT * FROM properties WHERE id = ?', [$id]);
if (!$prop) json_out(['ok' => false, 'error' => 'logement introuvable'], 404);

$provider   = ($in['provider'] ?? 'anthropic') === 'openai' ? 'openai' : 'anthropic';
$model      = trim((string)($in['model'] ?? ''));
$instruction= (string)($in['instruction'] ?? '');
$isActive   = !empty($in['active']) ? 1 : 0;
$autoSend   = !empty($in['auto']) ? 1 : 0;
$newKey     = trim((string)($in['key'] ?? ''));

if ($model === '') {
    $model = $GLOBALS['CONFIG']['ai']['default_model'] ?? 'claude-haiku-4-5-20251001';
}

// Ne remplace la clé que si une nouvelle est fournie
if ($newKey !== '') {
    $keyEnc = encrypt_secret($newKey);
    db_run(
        'UPDATE properties SET ai_provider=?, ai_model=?, instruction=?, is_active=?, auto_send=?, ai_key_enc=? WHERE id=?',
        [$provider, $model, $instruction, $isActive, $autoSend, $keyEnc, $id]
    );
} else {
    db_run(
        'UPDATE properties SET ai_provider=?, ai_model=?, instruction=?, is_active=?, auto_send=? WHERE id=?',
        [$provider, $model, $instruction, $isActive, $autoSend, $id]
    );
}

json_out(['ok' => true]);
