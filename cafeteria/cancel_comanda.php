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
$comandaId = (string)($input['comanda_id'] ?? '');

if ($comandaId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$fh = null;
$comandas = hp_cafeteria_load_json_locked('comandas.json', [], $fh);

$comandaIndex = null;
foreach ($comandas as $i => $c) {
    if ($c['id'] === $comandaId) {
        $comandaIndex = $i;
        break;
    }
}

if ($comandaIndex === null) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'comanda_not_found']);
    exit;
}

array_splice($comandas, $comandaIndex, 1);
hp_cafeteria_save_json_locked($fh, $comandas);

echo json_encode(['ok' => true]);
