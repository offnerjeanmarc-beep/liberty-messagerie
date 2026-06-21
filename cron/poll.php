<?php
/**
 * CRON - Surveillance des messages Lodgify.
 *
 * Ce cron ne genere pas et n'envoie pas de reponse IA automatiquement.
 * Il synchronise les messages, repere le dernier message voyageur et place
 * la conversation dans la boite de reception pour traitement manuel.
 */

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/repo.php';

$CONFIG = $GLOBALS['CONFIG'];
log_line('--- Passage du cron ---');
setting_set('cron_last_start_at', date('Y-m-d H:i:s'));

$activeProps = props_active_by_lodgify();
if (!$activeProps) {
    log_line('Aucun logement actif. Fin.');
    cron_mark_status('ok', 0, 'Aucun logement actif');
    exit;
}

$bookings = lodgify_list_bookings((int)($CONFIG['app']['poll_bookings_size'] ?? 50));
log_line('Reservations recuperees : ' . count($bookings));

$processed = 0;

foreach ($bookings as $b) {
    $propId = (string)($b['property_id'] ?? '');
    $threadUid = $b['thread_uid'] ?? null;
    if ($propId === '' || !$threadUid || !isset($activeProps[$propId])) {
        continue;
    }

    $channel = $b['source'] ?? null;
    $language = $b['language'] ?? null;
    $guest = $b['guest']['name'] ?? null;
    $email = $b['guest']['email'] ?? null;
    $bookingId = (int)($b['id'] ?? 0);
    $arrivalDate = to_mysql_datetime(array_first_value($b, ['arrival', 'arrival_date', 'check_in', 'checkin', 'date_arrival']));
    $departureDate = to_mysql_datetime(array_first_value($b, ['departure', 'departure_date', 'check_out', 'checkout', 'date_departure']));

    $thread = lodgify_get_thread($threadUid);
    if (!$thread || empty($thread['messages'])) {
        continue;
    }

    $convId = conv_upsert([
        'thread_uid' => $threadUid,
        'lodgify_property_id' => (int)$propId,
        'booking_id' => $bookingId,
        'arrival_date' => $arrivalDate,
        'departure_date' => $departureDate,
        'guest_name' => ($thread['guest_name'] ?? null) ?: $guest,
        'guest_email' => ($thread['guest_email'] ?? null) ?: $email,
        'channel' => $channel,
        'language' => $language,
        'last_message_date' => to_mysql_datetime($thread['last_message_date'] ?? null),
    ]);

    $messages = $thread['messages'];
    usort($messages, fn($x, $y) => strcmp((string)($x['date_created'] ?? ''), (string)($y['date_created'] ?? '')));

    $lastGuest = null;
    foreach ($messages as $m) {
        $type = $m['type'] ?? '';
        $dir = ($type === 'Renter') ? 'guest' : 'owner';
        $body = html_to_text($m['message'] ?? '');
        $rid = isset($m['id']) ? (int)$m['id'] : null;
        msg_store($convId, $rid, $dir, $body, to_mysql_datetime($m['date_created'] ?? null));
        if ($dir === 'guest') {
            $lastGuest = ['id' => $rid, 'body' => $body];
        }
    }

    if (!$lastGuest) {
        continue;
    }

    $lastMsg = end($messages);
    if (($lastMsg['type'] ?? '') !== 'Renter') {
        conv_mark_replied($convId, $lastGuest['id'], 'answered');
        continue;
    }

    $conv = conv_by_id($convId);
    if ($conv && (int)$conv['last_replied_msg_id'] === (int)$lastGuest['id']) {
        continue;
    }
    if ($conv && (int)$conv['last_guest_msg_id'] === (int)$lastGuest['id']
        && in_array($conv['status'], ['pending_review', 'answered', 'ignored'], true)) {
        continue;
    }

    $kwHit = needs_escalation($lastGuest['body'], $CONFIG['escalation_keywords'] ?? []);
    $reason = $kwHit ? ('Mot sensible detecte : ' . $kwHit) : 'Nouveau message voyageur';
    conv_queue_review($convId, $lastGuest['id'], $reason);
    log_line("Logement $propId / conv $convId : nouveau message en attente");
    $processed++;
}

cron_mark_status('ok', $processed, 'Fin normale');
log_line("Fin du cron. Conversations traitees : $processed");
