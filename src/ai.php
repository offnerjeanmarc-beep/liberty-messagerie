<?php
/**
 * Client IA multi-fournisseur (Claude / OpenAI).
 *
 * ai_generate_reply() prend l'instruction du chatbot (prompt système propre
 * au logement), le message du voyageur et un contexte, puis renvoie :
 *   ['reply' => string, 'requires_validation' => bool, 'reason' => string]
 *
 * Le modèle est invité à répondre en JSON strict afin de fournir à la fois
 * la réponse au client ET un drapeau de validation.
 */

declare(strict_types=1);

function ai_system_wrapper(string $instruction): string
{
    return $instruction . "\n\n"
        . "RÈGLES DE SORTIE (impératif) :\n"
        . "- Réponds toujours dans la langue du dernier message du voyageur.\n"
        . "- Tu dois renvoyer UNIQUEMENT un objet JSON valide, sans texte autour, "
        . "de la forme : {\"reply\": \"...\", \"requires_validation\": true|false, \"reason\": \"...\"}.\n"
        . "- \"reply\" : ta réponse prête à envoyer au voyageur.\n"
        . "- \"requires_validation\" : true si le message concerne un litige, un "
        . "remboursement, une remise, un dégât, une annulation, une plainte, une "
        . "urgence, ou toute situation sensible ou inhabituelle ; false si c'est une "
        . "question simple et factuelle (wifi, parking, horaires, équipements, etc.).\n"
        . "- \"reason\" : courte justification (en français) si requires_validation est true, sinon \"\".\n"
        . "- N'invente jamais d'information non fournie ; en cas de doute, mets requires_validation à true.";
}

function ai_build_context(array $conv, array $recentMessages): string
{
    $lines = [];
    $lines[] = 'Contexte de la réservation :';
    if (!empty($conv['guest_name']))  $lines[] = '- Voyageur : ' . $conv['guest_name'];
    if (!empty($conv['channel']))     $lines[] = '- Canal : ' . $conv['channel'];
    if (!empty($conv['language']))    $lines[] = '- Langue déclarée : ' . $conv['language'];
    $lines[] = '';
    $lines[] = 'Derniers échanges (du plus ancien au plus récent) :';
    foreach ($recentMessages as $m) {
        $who = $m['direction'] === 'guest' ? 'Voyageur' : 'Hôte';
        $lines[] = $who . ' : ' . $m['body'];
    }
    return implode("\n", $lines);
}

/**
 * Appel principal.
 * @param array $property  ligne SQL de la table properties (avec clé déchiffrée dans 'ai_key_plain')
 */
function ai_generate_reply(array $property, array $conv, array $recentMessages): array
{
    $instruction = trim((string)($property['instruction'] ?? ''));
    if ($instruction === '') {
        $instruction = "Tu es l'assistant d'accueil d'une location de vacances. "
            . "Réponds de façon chaleureuse, claire et professionnelle.";
    }
    $system = ai_system_wrapper($instruction);
    $context = ai_build_context($conv, $recentMessages);
    $userMsg = $context . "\n\nRédige la réponse au dernier message du voyageur.";

    $provider = $property['ai_provider'] ?: 'anthropic';
    $model    = $property['ai_model'] ?: ($GLOBALS['CONFIG']['ai']['default_model'] ?? 'claude-haiku-4-5-20251001');
    $key      = (string)($property['ai_key_plain'] ?? '');

    if ($key === '') {
        return ['reply' => '', 'requires_validation' => true,
                'reason' => 'Aucune clé IA configurée pour ce logement', 'error' => 'no_key'];
    }

    if ($provider === 'openai') {
        $out = ai_call_openai($key, $model, $system, $userMsg);
    } else {
        $out = ai_call_anthropic($key, $model, $system, $userMsg);
    }

    if (!empty($out['error'])) {
        return ['reply' => '', 'requires_validation' => true,
                'reason' => 'Erreur IA : ' . $out['error'], 'error' => $out['error']];
    }

    return ai_parse_json_reply($out['text']);
}

function ai_improve_reply(array $property, array $conv, array $recentMessages, string $draft): array
{
    $draft = trim($draft);
    if ($draft === '') {
        return ['reply' => '', 'requires_validation' => true, 'reason' => 'Réponse vide', 'error' => 'empty_draft'];
    }

    $instruction = trim((string)($property['instruction'] ?? ''));
    if ($instruction === '') {
        $instruction = "Tu es l'assistant d'accueil d'une location de vacances. "
            . "Réponds de façon chaleureuse, claire et professionnelle.";
    }

    $system = $instruction . "\n\n"
        . "Tu reformules une réponse écrite par un humain avant envoi à un voyageur.\n"
        . "Règles impératives :\n"
        . "- Ne rajoute aucune information factuelle absente du brouillon ou du contexte.\n"
        . "- Conserve le sens, les décisions et les limites du brouillon.\n"
        . "- Améliore seulement le ton, la clarté, la politesse et la fluidité.\n"
        . "- Réponds dans la langue du brouillon si elle est claire, sinon dans la langue du dernier message voyageur.\n"
        . "- Renvoie uniquement un JSON valide : {\"reply\":\"...\",\"requires_validation\":true|false,\"reason\":\"...\"}.";

    $context = ai_build_context($conv, $recentMessages);
    $userMsg = $context . "\n\nBrouillon humain à améliorer :\n" . $draft;

    $provider = $property['ai_provider'] ?: 'anthropic';
    $model = $property['ai_model'] ?: ($GLOBALS['CONFIG']['ai']['default_model'] ?? 'claude-haiku-4-5-20251001');
    $key = (string)($property['ai_key_plain'] ?? '');

    if ($key === '') {
        return ['reply' => '', 'requires_validation' => true,
                'reason' => 'Aucune clé IA configurée pour ce logement', 'error' => 'no_key'];
    }

    $out = $provider === 'openai'
        ? ai_call_openai($key, $model, $system, $userMsg)
        : ai_call_anthropic($key, $model, $system, $userMsg);

    if (!empty($out['error'])) {
        return ['reply' => '', 'requires_validation' => true,
                'reason' => 'Erreur IA : ' . $out['error'], 'error' => $out['error']];
    }

    return ai_parse_json_reply($out['text']);
}

/** Extrait le JSON renvoyé par le modèle, avec repli robuste. */
function ai_parse_json_reply(string $text): array
{
    $text = trim($text);
    // Retire d'éventuels ``` fences
    $text = preg_replace('/^```(?:json)?|```$/m', '', $text);
    $start = strpos($text, '{');
    $end   = strrpos($text, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $json = substr($text, $start, $end - $start + 1);
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['reply'])) {
            return [
                'reply'               => (string)$data['reply'],
                'requires_validation' => (bool)($data['requires_validation'] ?? false),
                'reason'              => (string)($data['reason'] ?? ''),
            ];
        }
    }
    // Repli : pas de JSON exploitable -> on garde le texte brut et on valide à la main
    return ['reply' => trim($text), 'requires_validation' => true,
            'reason' => 'Réponse IA non structurée, vérification conseillée'];
}

// ---------------------------------------------------------------------------
// Anthropic (Claude) — Messages API
// ---------------------------------------------------------------------------
function ai_call_anthropic(string $key, string $model, string $system, string $user): array
{
    $cfg = $GLOBALS['CONFIG']['ai'];
    $payload = [
        'model'      => $model,
        'max_tokens' => (int)($cfg['max_tokens'] ?? 700),
        'temperature'=> (float)($cfg['temperature'] ?? 0.3),
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $user]],
    ];
    $res = ai_http_post('https://api.anthropic.com/v1/messages', $payload, [
        'x-api-key: ' . $key,
        'anthropic-version: 2023-06-01',
        'content-type: application/json',
    ]);
    if (!empty($res['error'])) {
        return ['error' => $res['error']];
    }
    $d = $res['data'];
    $text = '';
    if (isset($d['content']) && is_array($d['content'])) {
        foreach ($d['content'] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
    }
    if ($text === '' && isset($d['error']['message'])) {
        return ['error' => $d['error']['message']];
    }
    return ['text' => $text];
}

// ---------------------------------------------------------------------------
// OpenAI — Chat Completions API
// ---------------------------------------------------------------------------
function ai_call_openai(string $key, string $model, string $system, string $user): array
{
    $cfg = $GLOBALS['CONFIG']['ai'];
    $payload = [
        'model'       => $model,
        'temperature' => (float)($cfg['temperature'] ?? 0.3),
        'max_tokens'  => (int)($cfg['max_tokens'] ?? 700),
        'messages'    => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $user],
        ],
    ];
    $res = ai_http_post('https://api.openai.com/v1/chat/completions', $payload, [
        'Authorization: Bearer ' . $key,
        'content-type: application/json',
    ]);
    if (!empty($res['error'])) {
        return ['error' => $res['error']];
    }
    $d = $res['data'];
    $text = $d['choices'][0]['message']['content'] ?? '';
    if ($text === '' && isset($d['error']['message'])) {
        return ['error' => $d['error']['message']];
    }
    return ['text' => (string)$text];
}

/** POST JSON générique. */
function ai_http_post(string $url, array $payload, array $headers): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $raw  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        return ['error' => $err ?: 'connexion échouée'];
    }
    $data = json_decode($raw, true);
    if ($code >= 300) {
        $msg = $data['error']['message'] ?? ('HTTP ' . $code);
        return ['error' => $msg];
    }
    return ['data' => $data];
}
