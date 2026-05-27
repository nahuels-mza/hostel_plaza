<?php
/**
 * Webhook público para WhatsApp Cloud API.
 *
 *  - GET: verificación inicial cuando das de alta el webhook en Meta.
 *  - POST: mensajes entrantes / cambios de estado.
 *
 * URL pública: https://hostelplaza.com.ar/whatsapp/webhook.php
 */

require_once __DIR__ . '/agent.php';

$cfg = hp_cfg();

/* ---------- 1. Verificación (handshake con Meta) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode      = $_GET['hub_mode']         ?? '';
    $token     = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge']    ?? '';

    if ($mode === 'subscribe' && hash_equals($cfg['whatsapp']['verify_token'], $token)) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

/* ---------- 2. Mensajes entrantes ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Responder rápido a Meta antes de procesar (timeout = 5s)
    http_response_code(200);
    if (function_exists('fastcgi_finish_request')) {
        echo 'OK';
        fastcgi_finish_request();
    } else {
        // En servidores no FastCGI igualmente devolvemos OK al final
        ignore_user_abort(true);
        set_time_limit(60);
    }

    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!$body) {
        hp_log('POST no-JSON: ' . substr($raw, 0, 200));
        exit;
    }

    // Estructura típica: entry[0].changes[0].value.messages[0]
    $entries = $body['entry'] ?? [];
    foreach ($entries as $entry) {
        foreach (($entry['changes'] ?? []) as $change) {
            $value = $change['value'] ?? [];
            $messages = $value['messages'] ?? [];

            foreach ($messages as $msg) {
                $from = $msg['from'] ?? null;
                $id   = $msg['id']   ?? null;
                $type = $msg['type'] ?? '';

                if (!$from) continue;

                if ($type === 'text') {
                    $text = $msg['text']['body'] ?? '';
                    try {
                        hp_handle_message($from, $text, $id);
                    } catch (Throwable $e) {
                        hp_log('Exception: ' . $e->getMessage());
                    }
                } else {
                    // audio, imagen, ubicación, etc. → respuesta cordial genérica
                    $fallback = "¡Hola! Soy el asistente virtual de Hostel Plaza. "
                              . "Por ahora solo entiendo mensajes de texto. "
                              . "¿Podés escribirme tu consulta? 🙂";
                    try {
                        wa_send_text($cfg, $from, $fallback);
                        hp_log("IN  <{$from}> [type={$type}] -> fallback enviado");
                    } catch (Throwable $e) {
                        hp_log('Exception fallback: ' . $e->getMessage());
                    }
                }
            }
        }
    }
    exit;
}

/* ---------- 3. Cualquier otro método ---------- */
http_response_code(405);
echo 'Method Not Allowed';
