<?php
/**
 * Helpers de entorno de desarrollo — SOLO deben usarse para diferencias de
 * comportamiento en localhost (secrets locales, imágenes, supresión de
 * warnings de PHP en respuestas JSON). En producción hp_is_localhost()
 * siempre da false y el comportamiento no cambia.
 */

function hp_is_localhost(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
    $host = explode(':', $host)[0];
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

/**
 * Ruta al archivo de secrets. En localhost usa do_not_upload/secrets.php
 * (vive dentro del repo, gitignored); en producción /storagedir/secrets.php
 * (un nivel arriba de public_html), como siempre.
 *
 * @param string $repoRoot La raíz del repo vista desde el archivo que llama
 *                          (ej: __DIR__ si el archivo está en la raíz,
 *                          dirname(__DIR__) si está un nivel adentro como
 *                          whatsapp/).
 */
function hp_secrets_path(string $repoRoot): string
{
    return hp_is_localhost()
        ? $repoRoot . '/do_not_upload/secrets.php'
        : dirname($repoRoot) . '/storagedir/secrets.php';
}
