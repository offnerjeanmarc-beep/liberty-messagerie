<?php
/**
 * Accès aux données (logements + conversations + historique).
 */

declare(strict_types=1);

require_once __DIR__ . '/crypto.php';

/** Tous les logements. */
function props_all(): array
{
    return db_all('SELECT * FROM properties ORDER BY name ASC');
}

/** Logements actifs, indexés par lodgify_property_id, avec clé déchiffrée. */
function props_active_by_lodgify(): array
{
    $rows = db_all('SELECT * FROM properties WHERE is_active = 1');
    $out = [];
    foreach ($rows as $r) {
        $r['ai_key_plain'] = decrypt_secret($r['ai_key_enc'] ?? '');
        $out[(string)$r['lodgify_property_id']] = $r;
    }
    return $out;
}

function prop_by_id(int $id): ?array
{
    $r = db_one('SELECT * FROM properties WHERE id = ?', [$id]);
    if ($r) {
        $r['ai_key_plain'] = decrypt_secret($r['ai_key_enc'] ?? '');
    }
    return $r;
}

function prop_by_lodgify_id(int $lodgifyPropertyId): ?array
{
    $r = db_one('SELECT * FROM properties WHERE lodgify_property_id = ?', [$lodgifyPropertyId]);
    if ($r) {
        $r['ai_key_plain'] = decrypt_secret($r['ai_key_enc'] ?? '');
    }
    return $r;
}

/** Crée le logement s'il n'existe pas (à partir des données Lodgify). */
function prop_ensure(int $lodgifyPropertyId, ?int $roomId, string $name): void
{
    $exists = db_val('SELECT id FROM properties WHERE lodgify_property_id = ?', [$lodgifyPropertyId]);
    if ($exists) {
        // met à jour le nom au cas où il a changé
        db_run('UPDATE properties SET name = ?, lodgify_room_id = COALESCE(?, lodgify_room_id) WHERE lodgify_property_id = ?',
            [$name, $roomId, $lodgifyPropertyId]);
        return;
    }
    db_run(
        'INSERT INTO properties (lodgify_property_id, lodgify_room_id, name, is_active) VALUES (?,?,?,0)',
        [$lodgifyPropertyId, $roomId, $name]
    );
}

/** Récupère une conversation par thread_uid. */
function conv_by_thread(string $threadUid): ?array
{
    return db_one('SELECT * FROM conversations WHERE thread_uid = ?', [$threadUid]);
}

/** Insère / met à jour l'entête de conversation, renvoie l'id local. */
function conv_upsert(array $c): int
{
    $existing = conv_by_thread($c['thread_uid']);
    if ($existing) {
        db_run(
            'UPDATE conversations SET booking_id=?, arrival_date=?, departure_date=?, guest_name=?, guest_email=?, channel=?, language=?, last_message_date=? WHERE id=?',
            [$c['booking_id'], $c['arrival_date'] ?? null, $c['departure_date'] ?? null, $c['guest_name'], $c['guest_email'], $c['channel'], $c['language'], $c['last_message_date'], $existing['id']]
        );
        return (int)$existing['id'];
    }
    db_run(
        'INSERT INTO conversations (thread_uid, lodgify_property_id, booking_id, arrival_date, departure_date, guest_name, guest_email, channel, language, last_message_date, status)
         VALUES (?,?,?,?,?,?,?,?,?,?, "new")',
        [$c['thread_uid'], $c['lodgify_property_id'], $c['booking_id'], $c['arrival_date'] ?? null, $c['departure_date'] ?? null, $c['guest_name'], $c['guest_email'], $c['channel'], $c['language'], $c['last_message_date']]
    );
    return (int)db()->lastInsertId();
}

function conv_by_id(int $id): ?array
{
    return db_one('SELECT * FROM conversations WHERE id = ?', [$id]);
}

function conv_messages(int $convId): array
{
    return db_all(
        'SELECT * FROM messages WHERE conversation_id = ? ORDER BY COALESCE(created_at_remote, created_at) ASC, id ASC',
        [$convId]
    );
}

function conv_mark_read(int $convId): void
{
    db_run('UPDATE conversations SET read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$convId]);
}

function conv_queue_review(int $convId, ?int $lastGuestMsgId, string $reason = 'Nouveau message'): void
{
    db_run(
        'UPDATE conversations SET status=?, ai_mode=?, ai_reason=?, last_guest_msg_id=?, read_at=NULL WHERE id=?',
        ['pending_review', 'manual', $reason, $lastGuestMsgId, $convId]
    );
}

/** Enregistre un message s'il n'est pas déjà présent. */
function msg_store(int $convId, ?int $remoteId, string $direction, string $body, ?string $remoteDate): void
{
    if ($remoteId !== null) {
        $dup = db_val('SELECT id FROM messages WHERE lodgify_message_id = ?', [$remoteId]);
        if ($dup) {
            return;
        }
    }
    db_run(
        'INSERT INTO messages (conversation_id, lodgify_message_id, direction, body, created_at_remote) VALUES (?,?,?,?,?)',
        [$convId, $remoteId, $direction, $body, $remoteDate]
    );
}

/** Derniers messages d'une conversation (pour le contexte IA). */
function msgs_recent(int $convId, int $limit = 8): array
{
    $rows = db_all('SELECT direction, body FROM messages WHERE conversation_id = ? ORDER BY id DESC LIMIT ' . (int)$limit, [$convId]);
    return array_reverse($rows);
}

/** Met à jour le brouillon IA et le statut. */
function conv_set_draft(int $convId, string $draft, string $mode, string $reason, string $status, ?int $lastGuestMsgId): void
{
    db_run(
        'UPDATE conversations SET ai_draft=?, ai_mode=?, ai_reason=?, status=?, last_guest_msg_id=? WHERE id=?',
        [$draft, $mode, $reason, $status, $lastGuestMsgId, $convId]
    );
}

function conv_mark_replied(int $convId, ?int $msgId, string $status = 'answered'): void
{
    db_run('UPDATE conversations SET last_replied_msg_id=?, status=? WHERE id=?', [$msgId, $status, $convId]);
}

/** Ajoute une ligne d'historique. */
function history_add(array $h): void
{
    db_run(
        'INSERT INTO history (conversation_id, thread_uid, lodgify_property_id, booking_id, guest_name, action, body, actor, error_text)
         VALUES (?,?,?,?,?,?,?,?,?)',
        [
            $h['conversation_id'] ?? null,
            $h['thread_uid'] ?? null,
            $h['lodgify_property_id'] ?? null,
            $h['booking_id'] ?? null,
            $h['guest_name'] ?? null,
            $h['action'],
            $h['body'] ?? null,
            $h['actor'] ?? 'ia',
            $h['error_text'] ?? null,
        ]
    );
}

/** Compteurs pour le tableau de bord. */
function stat_pending(): int
{
    return (int)db_val("SELECT COUNT(*) FROM conversations WHERE status IN ('new', 'pending_review', 'drafted')");
}
function stat_auto_today(): int
{
    return (int)db_val("SELECT COUNT(*) FROM history WHERE action IN ('auto_sent', 'human_sent') AND DATE(created_at) = CURDATE()");
}

/** Lit un reglage cle/valeur. */
function setting_get(string $key, ?string $default = null): ?string
{
    $value = db_val('SELECT sval FROM settings WHERE skey = ?', [$key]);
    return $value === null ? $default : (string)$value;
}

/** Enregistre un reglage cle/valeur. */
function setting_set(string $key, string $value): void
{
    db_run(
        'INSERT INTO settings (skey, sval) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE sval = VALUES(sval)',
        [$key, $value]
    );
}

/** Trace le dernier passage connu du cron. */
function cron_mark_status(string $status, int $processed = 0, string $detail = ''): void
{
    setting_set('cron_last_run_at', date('Y-m-d H:i:s'));
    setting_set('cron_last_status', $status);
    setting_set('cron_last_processed', (string)$processed);
    setting_set('cron_last_detail', $detail);
}
