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
$productId = (string)($input['product_id'] ?? '');

if ($comandaId === '' || $productId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

// Solo lectura: el stock recién se descuenta al cerrar la comanda. Esta
// consulta es best-effort para no dejar sumar más de lo que hay ahora mismo
// (no contempla lo ya cargado en otras mesas abiertas, ver requirements.md fase 6).
$product = null;
foreach (hp_cafeteria_load_json('products.json') as $p) {
    if ($p['id'] === $productId) {
        $product = $p;
        break;
    }
}

if ($product === null || !$product['active']) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'product_not_found']);
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

$itemIndex = null;
foreach ($comandas[$comandaIndex]['items'] as $i => $line) {
    if ($line['id'] === $productId) {
        $itemIndex = $i;
        break;
    }
}
$currentQty = $itemIndex !== null ? $comandas[$comandaIndex]['items'][$itemIndex]['qty'] : 0;

if (($product['tracks_stock'] ?? true) && $currentQty + 1 > $product['stock']) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'insufficient_stock', 'details' => [[
        'id' => $product['id'],
        'name' => $product['name'],
        'requested' => $currentQty + 1,
        'available' => $product['stock'],
    ]]]);
    exit;
}

if ($itemIndex !== null) {
    $comandas[$comandaIndex]['items'][$itemIndex]['qty']++;
} else {
    $comandas[$comandaIndex]['items'][] = [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $product['price'],
        'qty' => 1,
    ];
}

hp_cafeteria_save_json_locked($fh, $comandas);

echo json_encode(['ok' => true, 'comanda' => $comandas[$comandaIndex]]);
