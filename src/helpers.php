<?php
/**
 * Petites fonctions utilitaires partagées.
 */

declare(strict_types=1);

/** Échappement HTML court. */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Convertit une date ISO Lodgify en DATETIME MySQL (ou null). */
function to_mysql_datetime(?string $iso): ?string
{
    if (!$iso) {
        return null;
    }
    $ts = strtotime($iso);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

/** Renvoie la premiere valeur non vide trouvee dans un tableau imbrique simple. */
function array_first_value(array $data, array $keys): ?string
{
    foreach ($keys as $key) {
        $value = $data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                $value = null;
                break;
            }
            $value = $value[$part];
        }
        if ($value !== null && $value !== '') {
            return (string)$value;
        }
    }
    return null;
}

function fmt_date_time(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

function fmt_stay(?string $arrival, ?string $departure): string
{
    if (!$arrival && !$departure) {
        return 'Séjour non renseigné';
    }
    $a = $arrival ? date('d/m/Y', strtotime($arrival)) : '?';
    $d = $departure ? date('d/m/Y', strtotime($departure)) : '?';
    return $a . ' → ' . $d;
}

/** Nettoie le HTML d'un message Lodgify en texte simple lisible. */
function html_to_text(?string $html): string
{
    if (!$html) {
        return '';
    }
    $t = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $t = preg_replace('/<\/p>/i', "\n\n", $t);
    $t = strip_tags($t);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim($t);
}

/** Détecte si un texte contient un mot d'escalade -> validation humaine. */
function needs_escalation(string $text, array $keywords): ?string
{
    $hay = mb_strtolower($text, 'UTF-8');
    foreach ($keywords as $kw) {
        $kw = trim($kw);
        if ($kw !== '' && mb_strpos($hay, mb_strtolower($kw, 'UTF-8')) !== false) {
            return $kw;
        }
    }
    return null;
}

/** Journalise une ligne dans cron/poll.log (utile pour déboguer le cron). */
function log_line(string $msg): void
{
    $file = APP_ROOT . '/cron/poll.log';
    @file_put_contents($file, '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

/** Réponse JSON courte + arrêt. */
function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
