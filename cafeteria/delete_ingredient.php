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

if ($ingredientId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$fh = null;
$ingredients = hp_cafeteria_load_json_locked('ingredients.json', [], $fh);

$filtered = array_values(array_filter($ingredients, fn($i) => $i['id'] !== $ingredientId));

if (count($filtered) === count($ingredients)) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'ingredient_not_found']);
    exit;
}

hp_cafeteria_save_json_locked($fh, $filtered);

echo json_encode(['ok' => true, 'ingredients' => $filtered]);
