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
$productId = (string)($input['product_id'] ?? '');
$items = is_array($input['items'] ?? null) ? $input['items'] : null;

if ($productId === '' || $items === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$products = hp_cafeteria_load_json('products.json');
$productExists = false;
foreach ($products as $p) {
    if ($p['id'] === $productId) {
        $productExists = true;
        break;
    }
}
if (!$productExists) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'product_not_found']);
    exit;
}

$ingredients = hp_cafeteria_load_json('ingredients.json');
$ingredientIds = array_column($ingredients, 'id');

$cleanItems = [];
foreach ($items as $line) {
    $ingredientId = (string)($line['ingredient_id'] ?? '');
    $qty = $line['qty'] ?? null;
    if ($ingredientId === '' || !is_numeric($qty) || (float)$qty <= 0) {
        continue;
    }
    if (!in_array($ingredientId, $ingredientIds, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_ingredient', 'ingredient_id' => $ingredientId]);
        exit;
    }
    $cleanItems[] = ['ingredient_id' => $ingredientId, 'qty' => (float)$qty];
}

$fh = null;
$recipes = hp_cafeteria_load_json_locked('recipes.json', [], $fh);
$recipes[$productId] = $cleanItems;
hp_cafeteria_save_json_locked($fh, $recipes);

echo json_encode(['ok' => true, 'recipe' => $cleanItems]);
