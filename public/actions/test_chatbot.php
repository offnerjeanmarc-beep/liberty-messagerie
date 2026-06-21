<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/repo.php';
auth_start();
if (!auth_is_logged()) json_out(['ok' => false, 'error' => 'non connecté'], 401);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_check($in['csrf'] ?? null)) json_out(['ok' => false, 'error' => 'CSRF'], 403);

$id = (int)($in['id'] ?? 0);
$question = trim((string)($in['question'] ?? ''));
if ($question === '') json_out(['ok' => false, 'error' => 'question vide'], 400);

$prop = prop_by_id($id);
if (!$prop) json_out(['ok' => false, 'error' => 'logement introuvable'], 404);

// Permet de tester une clé/instruction saisies mais pas encore enregistrées
if (!empty($in['key'])) { $prop['ai_key_plain'] = trim((string)$in['key']); }
if (isset($in['instruction'])) { $prop['instruction'] = (string)$in['instruction']; }
if (!empty($in['provider'])) { $prop['ai_provider'] = $in['provider'] === 'openai' ? 'openai' : 'anthropic'; }
if (!empty($in['model'])) { $prop['ai_model'] = trim((string)$in['model']); }

$fakeConv = ['guest_name' => 'Test', 'channel' => 'Test', 'language' => null];
$recent = [['direction' => 'guest', 'body' => $question]];

$ai = ai_generate_reply($prop, $fakeConv, $recent);

json_out([
    'ok' => empty($ai['error']),
    'reply' => $ai['reply'],
    'requires_validation' => $ai['requires_validation'],
    'reason' => $ai['reason'],
    'error' => $ai['error'] ?? null,
]);
