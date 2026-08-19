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

/**
 * Detección (NO bloqueante) de submits duplicados: recuerda un fingerprint
 * con timestamp en logs/<channel>/.recent.json y avisa si el mismo
 * fingerprint ya se vio hace menos de $windowSeconds. No impide nada — el
 * caller decide qué hacer (típicamente: sólo loggearlo). Pensado para pocos
 * cientos de submits/día; usa flock() para no pisarse entre requests
 * casi simultáneos.
 *
 * @param string $fingerprint  hash que identifica "el mismo intento" (ej: md5 de email+fechas+room)
 * @param array  $meta         datos propios a guardar junto al fingerprint (ej: booking id, client info)
 * @return array{duplicate: bool, seconds_since: float|null, previous: array|null}
 */
function hp_dedupe_check(string $channel, string $fingerprint, array $meta = [], int $windowSeconds = 30): array
{
    $dir = __DIR__ . '/logs/' . $channel;
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $path = $dir . '/.recent.json';

    $result = ['duplicate' => false, 'seconds_since' => null, 'previous' => null];

    $fp = @fopen($path, 'c+');
    if (!$fp) return $result;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return $result; }

    $raw    = stream_get_contents($fp);
    $recent = json_decode($raw ?: '[]', true);
    if (!is_array($recent)) $recent = [];

    $now = microtime(true);
    // Podar entradas viejas para que el archivo no crezca sin límite.
    $keepWindow = max($windowSeconds, 600);
    $recent = array_values(array_filter($recent, fn($r) => $now - (float)($r['t'] ?? 0) < $keepWindow));

    foreach ($recent as $r) {
        if (($r['fp'] ?? '') === $fingerprint && ($now - (float)($r['t'] ?? 0)) < $windowSeconds) {
            $result = [
                'duplicate'     => true,
                'seconds_since' => round($now - (float)$r['t'], 2),
                'previous'      => $r,
            ];
            break;
        }
    }

    $recent[] = array_merge(['fp' => $fingerprint, 't' => $now], $meta);
    if (count($recent) > 200) $recent = array_slice($recent, -200); // cap simple

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($recent));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return $result;
}
