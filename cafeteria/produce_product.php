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
$batchQty = $input['qty'] ?? null;

if ($productId === '' || !is_numeric($batchQty) || (float)$batchQty <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}
$batchQty = (float)$batchQty;

$recipes = hp_cafeteria_load_json('recipes.json');
$recipe = $recipes[$productId] ?? [];
if (empty($recipe)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'no_recipe']);
    exit;
}

// 1) Insumos: validar y descontar. Se hace primero y por separado de products.json
// para no dejar los dos archivos lockeados al mismo tiempo (evita deadlocks con
// otros endpoints que solo tocan uno de los dos).
$ingredientsFh = null;
$ingredients = hp_cafeteria_load_json_locked('ingredients.json', [], $ingredientsFh);

$insufficient = [];
$consumed = [];
$totalCost = 0;

foreach ($recipe as $line) {
    $ingredientId = $line['ingredient_id'];
    $neededQty = $line['qty'] * $batchQty;
    $ingredient = null;
    foreach ($ingredients as $ing) {
        if ($ing['id'] === $ingredientId) {
            $ingredient = $ing;
            break;
        }
    }
    if ($ingredient === null) {
        continue; // ingrediente borrado después de definir la receta; se ignora esa línea
    }
    if ($neededQty > $ingredient['stock']) {
        $insufficient[] = [
            'id' => $ingredient['id'],
            'name' => $ingredient['name'],
            'needed' => $neededQty,
            'available' => $ingredient['stock'],
        ];
    }
}

if (!empty($insufficient)) {
    flock($ingredientsFh, LOCK_UN);
    fclose($ingredientsFh);
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'insufficient_ingredients', 'details' => $insufficient]);
    exit;
}

foreach ($recipe as $line) {
    $ingredientId = $line['ingredient_id'];
    $neededQty = $line['qty'] * $batchQty;
    foreach ($ingredients as &$ing) {
        if ($ing['id'] === $ingredientId) {
            $ing['stock'] -= $neededQty;
            $cost = $neededQty * (float)($ing['last_cost'] ?? 0);
            $totalCost += $cost;
            $consumed[] = [
                'ingredient_id' => $ing['id'],
                'name' => $ing['name'],
                'qty' => $neededQty,
                'cost' => round($cost, 2),
            ];
            break;
        }
    }
    unset($ing);
}

hp_cafeteria_save_json_locked($ingredientsFh, $ingredients);

// 2) Producto: sumar stock producido y actualizar el costo unitario calculado.
$productsFh = null;
$products = hp_cafeteria_load_json_locked('products.json', [], $productsFh);

$unitCost = round($totalCost / $batchQty, 2);
$today = date('Y-m-d');
$found = null;

foreach ($products as &$product) {
    if ($product['id'] === $productId) {
        $product['stock'] += (int)round($batchQty);
        $product['last_cost'] = $unitCost;
        $product['last_purchase_at'] = $today;
        $found = $product;
        break;
    }
}
unset($product);

if ($found === null) {
    flock($productsFh, LOCK_UN);
    fclose($productsFh);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'product_not_found']);
    exit;
}

hp_cafeteria_save_json_locked($productsFh, $products);

$production = [
    'id' => uniqid('production_', true),
    'kind' => 'production',
    'item_id' => $productId,
    'item_name' => $found['name'],
    'qty' => $batchQty,
    'unit_cost' => $unitCost,
    'total_cost' => round($totalCost, 2),
    'consumed' => $consumed,
    'note' => '',
    'admin_username' => (string)($_SESSION['cafeteria_username'] ?? ''),
    'date' => date('c'),
];

hp_cafeteria_append_json('purchases.json', $production);

echo json_encode(['ok' => true, 'production' => $production, 'products' => $products, 'ingredients' => $ingredients]);
