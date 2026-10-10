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
$unit = trim((string)($input['unit'] ?? ''));
$stock = $input['stock'] ?? 0;
$minStock = $input['min_stock'] ?? 0;

if ($name === '' || $unit === '' || !is_numeric($stock) || !is_numeric($minStock)) {
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
$ingredients = hp_cafeteria_load_json_locked('ingredients.json', [], $fh);

foreach ($ingredients as $i) {
    if ($i['id'] === $slug) {
        flock($fh, LOCK_UN);
        fclose($fh);
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'duplicate_id', 'id' => $slug]);
        exit;
    }
}

$ingredient = [
    'id' => $slug,
    'name' => $name,
    'stock' => (float)$stock,
    'unit' => $unit,
    'min_stock' => (float)$minStock,
    'updated_at' => date('Y-m-d'),
    'updated_by' => (string)($_SESSION['cafeteria_username'] ?? ''),
];

$ingredients[] = $ingredient;
hp_cafeteria_save_json_locked($fh, $ingredients);

echo json_encode(['ok' => true, 'ingredient' => $ingredient, 'ingredients' => $ingredients]);
