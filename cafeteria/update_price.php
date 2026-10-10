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
$price = $input['price'] ?? null;

if ($productId === '' || !hp_cafeteria_valid_price($price)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_price']);
    exit;
}

$fh = null;
$products = hp_cafeteria_load_json_locked('products.json', [], $fh);

$found = false;
foreach ($products as &$product) {
    if ($product['id'] === $productId) {
        $found = true;
        $product['price'] = round((float)$price, 2);
        break;
    }
}
unset($product);

if (!$found) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'product_not_found']);
    exit;
}

hp_cafeteria_save_json_locked($fh, $products);

echo json_encode(['ok' => true, 'products' => $products]);
