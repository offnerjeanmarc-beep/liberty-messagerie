<?php
/**
 * Client API Lodgify.
 *  - Lecture : GET /v2/properties, /v2/reservations/bookings, /v2/messaging/{uid}
 *  - Envoi   : POST /v1/reservation/booking/{id}/messages
 */

declare(strict_types=1);

function lodgify_request(string $method, string $path, ?array $body = null): array
{
    $cfg  = $GLOBALS['CONFIG']['lodgify'];
    $url  = rtrim($cfg['base_url'], '/') . $path;
    $key  = $cfg['api_key'];

    $headers = [
        'X-ApiKey: ' . $key,
        'accept: application/json',
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE);
        $headers[] = 'content-type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'code' => 0, 'error' => $err, 'data' => null];
    }
    $data = json_decode($raw, true);
    return [
        'ok'   => $code >= 200 && $code < 300,
        'code' => $code,
        'error'=> $code >= 300 ? ('HTTP ' . $code . ' ' . substr((string)$raw, 0, 300)) : null,
        'data' => $data,
        'raw'  => $raw,
    ];
}

/** Liste les logements du compte. */
function lodgify_list_properties(int $size = 50): array
{
    $r = lodgify_request('GET', '/v2/properties?size=' . $size);
    return $r['ok'] && isset($r['data']['items']) ? $r['data']['items'] : [];
}

/** Liste les réservations récentes (avec thread_uid). */
function lodgify_list_bookings(int $size = 50): array
{
    $r = lodgify_request('GET', '/v2/reservations/bookings?size=' . $size . '&includeTransactions=false');
    return $r['ok'] && isset($r['data']['items']) ? $r['data']['items'] : [];
}

/** Récupère un fil de conversation par thread_uid. */
function lodgify_get_thread(string $threadUid): ?array
{
    $r = lodgify_request('GET', '/v2/messaging/' . rawurlencode($threadUid));
    return $r['ok'] ? $r['data'] : null;
}

/**
 * Envoie un message au voyageur (de la part de l'hôte).
 * Renvoie ['ok'=>bool, 'code'=>int, 'error'=>?string].
 */
function lodgify_send_message(int $bookingId, string $message, string $subject = '', bool $notify = true): array
{
    $body = [
        'subject'           => $subject !== '' ? $subject : 'Message',
        'message'           => $message,
        'type'              => 'Owner',
        'send_notification' => $notify,
    ];
    $r = lodgify_request('POST', '/v1/reservation/booking/' . $bookingId . '/messages', $body);
    return ['ok' => $r['ok'], 'code' => $r['code'], 'error' => $r['error']];
}
