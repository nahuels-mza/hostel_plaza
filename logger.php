<?php
/**
 * Logger central del sitio.
 *
 * Un archivo de log por día y por canal:
 *   logs/mail/2026-08-18.log
 *   logs/whatsapp/2026-08-18.log
 *   logs/payway/2026-08-18.log
 *   logs/paypal/2026-08-18.log
 *   logs/bananadesk/2026-08-18.log
 *
 * logs/.htaccess (Deny from all) protege recursivamente todas las subcarpetas.
 */

/**
 * Escribe una línea (o bloque multilínea) en logs/<channel>/AAAA-MM-DD.log.
 * Cada llamada antepone un timestamp "[Y-m-d H:i:s]" a la primera línea.
 *
 * Nombre "hp_write_log" (no "hp_log") a propósito: whatsapp/agent.php ya
 * tiene su propio hp_log($msg) de un solo canal fijo — así evitamos choque
 * de nombres si algún día se cargan ambos archivos en el mismo request.
 */
function hp_write_log(string $channel, string $message): void
{
    $dir = __DIR__ . '/logs/' . $channel;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $line = '[' . date('Y-m-d H:i:s') . '] ' . rtrim($message) . "\n";
    @file_put_contents($dir . '/' . date('Y-m-d') . '.log', $line, FILE_APPEND);
}

/**
 * Datos del request HTTP actual — IP real, user-agent y referer — para poder
 * distinguir, ante una reserva/mail duplicado, si vino del mismo browser
 * (doble click / doble submit) o de dos requests distintos (retry de red,
 * webhook duplicado, bot, etc).
 */
function hp_client_info(): string
{
    $ipHeader = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '-';
    $ip  = trim(explode(',', $ipHeader)[0]);
    $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '-';
    $ref = $_SERVER['HTTP_REFERER'] ?? '-';
    return "{$ip} | {$ua} | ref={$ref}";
}
