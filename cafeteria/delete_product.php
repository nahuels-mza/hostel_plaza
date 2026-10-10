<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_admin_json();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$input = hp_cafeteria_json_input();
$productId = (string)($input['id'] ?? '');

if ($productId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$fh = null;
$products = hp_cafeteria_load_json_locked('products.json', [], $fh);

$filtered = array_values(array_filter($products, fn($p) => $p['id'] !== $productId));

if (count($filtered) === count($products)) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'product_not_found']);
    exit;
}

hp_cafeteria_save_json_locked($fh, $filtered);

echo json_encode(['ok' => true, 'products' => $filtered]);
