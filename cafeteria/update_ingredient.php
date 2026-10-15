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
$ingredientId = (string)($input['id'] ?? '');
$quantity = $input['quantity'] ?? null;
$updatedBy = (string)($_SESSION['cafeteria_username'] ?? '');

if ($ingredientId === '' || !is_numeric($quantity)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$fh = null;
$ingredients = hp_cafeteria_load_json_locked('ingredients.json', [], $fh);

$found = false;
$today = date('Y-m-d');
foreach ($ingredients as &$ingredient) {
    if ($ingredient['id'] === $ingredientId) {
        $found = true;
        $ingredient['stock'] = (float)$quantity;
        $ingredient['updated_at'] = $today;
        $ingredient['updated_by'] = $updatedBy;
        break;
    }
}
unset($ingredient);

if (!$found) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'ingredient_not_found']);
    exit;
}

hp_cafeteria_save_json_locked($fh, $ingredients);

hp_cafeteria_append_json('ingredient_history.json', [
    'id' => $ingredientId,
    'quantity' => (float)$quantity,
    'updated_by' => $updatedBy,
    'date' => date('c'),
]);

echo json_encode(['ok' => true, 'ingredients' => $ingredients]);
