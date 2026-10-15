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
$name = trim((string)($input['name'] ?? ''));
$category = trim((string)($input['category'] ?? ''));
// El precio de venta es opcional al alta: muchas veces el producto se carga
// para llevar stock antes de tener definido a cuánto se va a vender.
$price = $input['price'] ?? 0;
$stock = $input['stock'] ?? 0;
$minStock = $input['min_stock'] ?? 0;
$unit = trim((string)($input['unit'] ?? 'unidad'));

if ($name === '' || $category === '' || !hp_cafeteria_valid_price($price) || !is_numeric($stock) || !is_numeric($minStock)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
if ($slug === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_name']);
    exit;
}

$fh = null;
$products = hp_cafeteria_load_json_locked('products.json', [], $fh);

foreach ($products as $p) {
    if ($p['id'] === $slug) {
        flock($fh, LOCK_UN);
        fclose($fh);
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'duplicate_id', 'id' => $slug]);
        exit;
    }
}

$product = [
    'id' => $slug,
    'name' => $name,
    'category' => $category,
    'price' => round((float)$price, 2),
    'stock' => (int)round((float)$stock),
    'min_stock' => (int)round((float)$minStock),
    'unit' => $unit !== '' ? $unit : 'unidad',
    'active' => true,
];

$products[] = $product;
hp_cafeteria_save_json_locked($fh, $products);

echo json_encode(['ok' => true, 'product' => $product, 'products' => $products]);
