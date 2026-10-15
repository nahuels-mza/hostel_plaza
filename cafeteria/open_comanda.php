<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_auth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$input = hp_cafeteria_json_input();
$name = trim((string)($input['name'] ?? ''));
$type = (string)($input['type'] ?? 'mesa');

if (!in_array($type, ['mesa', 'evento', 'mostrador'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

// Mostrador (venta rápida al paso) se abre con un click desde el home, sin
// pedir nombre — se autogenera uno numerado para poder distinguir varias
// ventas de mostrador abiertas a la vez. Mesa/evento siguen requiriendo nombre.
if ($type === 'mostrador' && $name === '') {
    $openMostrador = array_filter(hp_cafeteria_load_json('comandas.json'), fn($c) => $c['type'] === 'mostrador');
    $name = 'Mostrador ' . (count($openMostrador) + 1);
}

if ($name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$comanda = [
    'id' => uniqid('cmd_', true),
    'name' => $name,
    'type' => $type,
    'items' => [],
    'opened_at' => date('c'),
    'opened_by' => $_SESSION['cafeteria_username'] ?? '',
];

hp_cafeteria_append_json('comandas.json', $comanda);

echo json_encode(['ok' => true, 'comanda' => $comanda]);
