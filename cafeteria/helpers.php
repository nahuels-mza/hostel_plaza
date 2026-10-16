<?php
// Helpers compartidos del subsistema de cafetería (Gato Negro).
// Mismo patrón de storage que el resto del repo: JSON plano en disco, flock para concurrencia.

function hp_cafeteria_path(string $file): string
{
    return __DIR__ . '/' . $file;
}

// Nombre del archivo de ventas del día (uno por día, ej. sales/ventas_2026-09-24.json)
// para que los reportes mensuales de fases futuras solo tengan que sumar los
// archivos del rango de fechas en vez de recorrer un único archivo enorme.
function hp_cafeteria_sales_file(?string $date = null): string
{
    $date = $date ?? date('Y-m-d');
    return 'sales/ventas_' . $date . '.json';
}

// Todas las ventas de un mes (YYYY-MM), juntando los archivos diarios de esa
// carpeta. Cada venta lleva su propio 'date' (día completo), así que alcanza
// con listar los archivos cuyo nombre empieza con ese prefijo.
function hp_cafeteria_load_sales_for_month(string $month): array
{
    $pattern = hp_cafeteria_path('sales/ventas_' . $month . '-*.json');
    $sales = [];
    foreach (glob($pattern) ?: [] as $file) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $sales = array_merge($sales, $data);
        }
    }
    return $sales;
}

// Todas las ventas entre dos fechas (YYYY-MM-DD, inclusive), juntando los
// archivos diarios día por día. Pensado para rangos chicos (vista de admin);
// se limita a 366 días para no abrir miles de archivos por error de input.
function hp_cafeteria_load_sales_for_range(string $from, string $to): array
{
    $start = new DateTime($from);
    $end = new DateTime($to);
    if ($end < $start) {
        [$start, $end] = [$end, $start];
    }
    $end->modify('+1 day'); // DatePeriod no incluye el último día por defecto
    $period = new DatePeriod($start, new DateInterval('P1D'), $end);

    $sales = [];
    $daysChecked = 0;
    foreach ($period as $day) {
        if (++$daysChecked > 366) {
            break;
        }
        $file = hp_cafeteria_path(hp_cafeteria_sales_file($day->format('Y-m-d')));
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                $sales = array_merge($sales, $data);
            }
        }
    }
    return $sales;
}

// Credenciales propias de la cafetería (independientes del login de staff/admin),
// guardadas fuera del código siguiendo el mismo patrón que el resto del repo
// (do_not_upload/secrets.php en local, storagedir/secrets.php en producción).
function hp_cafeteria_secrets(): array
{
    $repoRoot = dirname(__DIR__);
    require_once $repoRoot . '/dev_env.php';
    $secretsPath = hp_secrets_path($repoRoot);
    return file_exists($secretsPath) ? require $secretsPath : [];
}

function hp_cafeteria_require_auth(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['cafeteria_logged_in'])) {
        header('Location: /cafeteriagatonegro/login');
        exit;
    }
}

function hp_cafeteria_role(): string
{
    return $_SESSION['cafeteria_role'] ?? 'general';
}

function hp_cafeteria_is_admin(): bool
{
    return hp_cafeteria_role() === 'admin';
}

// Para páginas HTML: exige sesión + rol admin, si no redirige a la comanda.
// Los endpoints JSON de fases futuras (stock/insumos/compras) deben chequear
// hp_cafeteria_is_admin() ellos mismos y devolver 403 en vez de redirigir.
function hp_cafeteria_require_admin(): void
{
    hp_cafeteria_require_auth();
    if (!hp_cafeteria_is_admin()) {
        header('Location: /cafeteriagatonegro');
        exit;
    }
}

function hp_cafeteria_load_json(string $file, array $default = []): array
{
    $path = hp_cafeteria_path($file);
    if (!file_exists($path)) {
        return $default;
    }
    $fh = fopen($path, 'r');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_SH);
    $content = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

// Abre el archivo ya lockeado (LOCK_EX) para que el caller pueda leer+modificar+escribir
// sin que otra request pise el cambio entre el load y el save (evita carreras en descuentos de stock).
function hp_cafeteria_load_json_locked(string $file, array $default, &$fh): array
{
    $path = hp_cafeteria_path($file);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $fh = fopen($path, 'c+');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_EX);
    $content = stream_get_contents($fh);
    $data = json_decode($content, true);
    return is_array($data) ? $data : $default;
}

function hp_cafeteria_save_json_locked($fh, array $data): void
{
    rewind($fh);
    ftruncate($fh, 0);
    fwrite($fh, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
}

function hp_cafeteria_append_json(string $file, array $entry): void
{
    $fh = null;
    $data = hp_cafeteria_load_json_locked($file, [], $fh);
    $data[] = $entry;
    hp_cafeteria_save_json_locked($fh, $data);
}

// Precio válido: número no negativo con hasta 2 decimales (mismo formato que
// se usa para mostrarlo, así nunca queda un precio con más precisión de la
// que la interfaz puede representar).
function hp_cafeteria_valid_price($value): bool
{
    if (!is_numeric($value) || (float)$value < 0) {
        return false;
    }
    return (bool)preg_match('/^\d+(\.\d{1,2})?$/', trim((string)$value));
}

function hp_cafeteria_json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Para endpoints JSON (no páginas HTML): exige sesión + rol admin, si no
// corta con un 403 en JSON en vez de redirigir.
function hp_cafeteria_require_admin_json(): void
{
    hp_cafeteria_require_auth();
    if (!hp_cafeteria_is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}
