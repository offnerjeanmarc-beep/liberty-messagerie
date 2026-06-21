<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/repo.php';
auth_start();
if (!auth_is_logged()) json_out(['ok' => false, 'error' => 'non connecté'], 401);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_check($in['csrf'] ?? null)) json_out(['ok' => false, 'error' => 'CSRF'], 403);

$convId = (int)($in['id'] ?? 0);
$conv = conv_by_id($convId);
if (!$conv) json_out(['ok' => false, 'error' => 'conversation introuvable'], 404);

$property = prop_by_lodgify_id((int)$conv['lodgify_property_id']);
if (!$property) json_out(['ok' => false, 'error' => 'logement introuvable'], 404);

$recent = msgs_recent($convId, 12);
$ai = ai_generate_reply($property, $conv, $recent);

if (!empty($ai['error'])) {
    history_add([
        'conversation_id' => $convId,
        'thread_uid' => $conv['thread_uid'],
        'lodgify_property_id' => (int)$conv['lodgify_property_id'],
        'booking_id' => (int)$conv['booking_id'],
        'guest_name' => $conv['guest_name'],
        'action' => 'error',
        'body' => null,
        'actor' => 'admin',
        'error_text' => 'Brouillon IA : ' . $ai['error'],
    ]);
    json_out(['ok' => false, 'error' => $ai['error'], 'reason' => $ai['reason'] ?? ''], 502);
}

$reason = $ai['requires_validation'] ? ($ai['reason'] ?: 'Validation conseillée') : 'Brouillon IA généré';
conv_set_draft($convId, $ai['reply'], 'manual', $reason, 'drafted', isset($conv['last_guest_msg_id']) ? (int)$conv['last_guest_msg_id'] : null);

json_out([
    'ok' => true,
    'reply' => $ai['reply'],
    'requires_validation' => $ai['requires_validation'],
    'reason' => $reason,
]);
