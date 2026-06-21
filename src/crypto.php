<?php
/**
 * Chiffrement symétrique des clés API stockées en base (AES-256-CBC).
 * La clé maître provient de config['app_key'] (64 hex = 32 octets).
 */

declare(strict_types=1);

function crypto_key(): string
{
    $hex = $GLOBALS['CONFIG']['app_key'] ?? '';
    $key = @hex2bin($hex);
    if ($key === false || strlen($key) !== 32) {
        // Repli : dérive une clé 32 octets si app_key n'est pas un hex de 64.
        $key = hash('sha256', (string)$hex, true);
    }
    return $key;
}

/** Chiffre une chaîne, renvoie base64( iv . ciphertext ). */
function encrypt_secret(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', crypto_key(), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('Échec du chiffrement');
    }
    return base64_encode($iv . $cipher);
}

/** Déchiffre une chaîne produite par encrypt_secret(). */
function decrypt_secret(?string $blob): string
{
    if ($blob === null || $blob === '') {
        return '';
    }
    $raw = base64_decode($blob, true);
    if ($raw === false || strlen($raw) < 17) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', crypto_key(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

/** Masque une clé pour l'affichage : garde les 4 derniers caractères. */
function mask_secret(string $plain): string
{
    $len = strlen($plain);
    if ($len === 0) {
        return '— non configurée —';
    }
    $tail = substr($plain, -4);
    return str_repeat('•', min(16, max(4, $len - 4))) . $tail;
}
