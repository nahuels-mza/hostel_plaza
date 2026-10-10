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
$kind = (string)($input['kind'] ?? ''); // 'product' | 'ingredient'
$itemId = (string)($input['item_id'] ?? '');
$qty = $input['qty'] ?? null;
$unitCost = $input['unit_cost'] ?? null;
$note = trim((string)($input['note'] ?? ''));

$catalogFile = $kind === 'product' ? 'products.json' : ($kind === 'ingredient' ? 'ingredients.json' : null);

if ($catalogFile === null || $itemId === '' || !is_numeric($qty) || (float)$qty <= 0 || !is_numeric($unitCost) || (float)$unitCost < 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

$qty = (float)$qty;
$unitCost = (float)$unitCost;
$today = date('Y-m-d');

$fh = null;
$catalog = hp_cafeteria_load_json_locked($catalogFile, [], $fh);

$found = null;
foreach ($catalog as &$item) {
    if ($item['id'] === $itemId) {
        // Productos vendibles llevan stock entero; insumos admiten cantidades fraccionarias.
        $item['stock'] = $kind === 'product' ? $item['stock'] + (int)round($qty) : $item['stock'] + $qty;
        $item['last_cost'] = $unitCost;
        $item['last_purchase_at'] = $today;
        if ($kind === 'ingredient') {
            $item['updated_at'] = $today;
            $item['updated_by'] = (string)($_SESSION['cafeteria_username'] ?? '');
        }
        $found = $item;
        break;
    }
}
unset($item);

if ($found === null) {
    flock($fh, LOCK_UN);
    fclose($fh);
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'item_not_found']);
    exit;
}

hp_cafeteria_save_json_locked($fh, $catalog);

$purchase = [
    'id' => uniqid('purchase_', true),
    'kind' => $kind,
    'item_id' => $itemId,
    'item_name' => $found['name'],
    'qty' => $qty,
    'unit_cost' => $unitCost,
    'total_cost' => round($qty * $unitCost, 2),
    'note' => $note,
    'admin_username' => (string)($_SESSION['cafeteria_username'] ?? ''),
    'date' => date('c'),
];

hp_cafeteria_append_json('purchases.json', $purchase);

echo json_encode(['ok' => true, 'purchase' => $purchase, 'catalog' => $catalog]);
