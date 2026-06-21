<?php
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/repo.php';
auth_start();
if (!auth_is_logged()) json_out(['ok' => false, 'error' => 'non connecté'], 401);

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!csrf_check($in['csrf'] ?? null)) json_out(['ok' => false, 'error' => 'CSRF'], 403);

$convId = (int)($in['id'] ?? 0);
$text   = trim((string)($in['text'] ?? ''));
$conv = db_one('SELECT * FROM conversations WHERE id = ?', [$convId]);
if (!$conv) json_out(['ok' => false, 'error' => 'conversation introuvable'], 404);
if ($text === '') json_out(['ok' => false, 'error' => 'message vide'], 400);

$bookingId = (int)$conv['booking_id'];
if ($bookingId <= 0) json_out(['ok' => false, 'error' => 'réservation inconnue pour ce fil'], 400);

$res = lodgify_send_message($bookingId, $text, 'Re: votre message', true);
if (!$res['ok']) {
    history_add([
        'conversation_id' => $convId, 'thread_uid' => $conv['thread_uid'],
        'lodgify_property_id' => (int)$conv['lodgify_property_id'], 'booking_id' => $bookingId,
        'guest_name' => $conv['guest_name'], 'action' => 'error', 'body' => $text,
        'actor' => 'admin', 'error_text' => $res['error'],
    ]);
    json_out(['ok' => false, 'error' => $res['error']], 502);
}

msg_store($convId, null, 'owner', $text, date('Y-m-d H:i:s'));
conv_mark_replied($convId, (int)$conv['last_guest_msg_id'], 'answered');
db_run('UPDATE conversations SET ai_draft = ? WHERE id = ?', [$text, $convId]);
history_add([
    'conversation_id' => $convId, 'thread_uid' => $conv['thread_uid'],
    'lodgify_property_id' => (int)$conv['lodgify_property_id'], 'booking_id' => $bookingId,
    'guest_name' => $conv['guest_name'], 'action' => 'human_sent', 'body' => $text, 'actor' => 'admin',
]);

json_out(['ok' => true]);
