<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/repo.php';
auth_start();
if (!auth_is_logged()) json_out(['ok' => false, 'error' => 'non connecté'], 401);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_check($in['csrf'] ?? null)) json_out(['ok' => false, 'error' => 'CSRF'], 403);

$convId = (int)($in['id'] ?? 0);
$conv = db_one('SELECT * FROM conversations WHERE id = ?', [$convId]);
if (!$conv) json_out(['ok' => false, 'error' => 'introuvable'], 404);

conv_mark_replied($convId, (int)$conv['last_guest_msg_id'], 'ignored');
history_add([
    'conversation_id' => $convId, 'thread_uid' => $conv['thread_uid'],
    'lodgify_property_id' => (int)$conv['lodgify_property_id'], 'booking_id' => (int)$conv['booking_id'],
    'guest_name' => $conv['guest_name'], 'action' => 'ignored',
    'body' => $conv['ai_draft'], 'actor' => 'admin',
]);
json_out(['ok' => true]);
