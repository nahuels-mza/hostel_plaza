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
$buyerType = (string)($input['buyer_type'] ?? '');
$guest = trim((string)($input['guest'] ?? ''));
$paymentMethod = trim((string)($input['payment_method'] ?? ''));

$validMethods = ['Efectivo', 'Transferencia', 'Tarjeta'];
$validBuyerTypes = ['huesped', 'no_huesped'];

if ($comandaId === ''
    || !in_array($paymentMethod, $validMethods, true)
    || !in_array($buyerType, $validBuyerTypes, true)
    || ($buyerType === 'huesped' && $guest === '')
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_request']);
    exit;
}

if ($buyerType === 'no_huesped') {
    $guest = '';
}

// Paso 1: leer la comanda (sin tocar todavía) para no dejar nunca dos locks
// (comandas.json + products.json) tomados a la vez — evita deadlocks entre
// este endpoint y add_item.php/remove_item.php.
$comanda = null;
foreach (hp_cafeteria_load_json('comandas.json') as $c) {
    if ($c['id'] === $comandaId) {
        $comanda = $c;
        break;
    }
}

if ($comanda === null) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'comanda_not_found']);
    exit;
}

$cart = $comanda['items'];
if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'no_valid_items']);
    exit;
}

// Paso 2: validar y descontar stock. Si falta stock, la comanda queda intacta
// (no se pierde lo cargado) y el cajero puede reintentar tras ajustar el pedido.
$productsFh = null;
$products = hp_cafeteria_load_json_locked('products.json', [], $productsFh);

$insufficient = [];
foreach ($cart as $line) {
    foreach ($products as $product) {
        if ($product['id'] === $line['id']
            && ($product['tracks_stock'] ?? true)
            && $line['qty'] > $product['stock']
        ) {
            $insufficient[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'requested' => $line['qty'],
                'available' => $product['stock'],
            ];
        }
    }
}

if (!empty($insufficient)) {
    flock($productsFh, LOCK_UN);
    fclose($productsFh);
    http_response_code(409);
    echo json_encode(['ok' => false, 'error' => 'insufficient_stock', 'details' => $insufficient]);
    exit;
}

$saleItems = [];
$total = 0;
foreach ($cart as $line) {
    $saleItems[] = [
        'id' => $line['id'],
        'name' => $line['name'],
        'qty' => $line['qty'],
        'price' => $line['price'],
        'subtotal' => $line['price'] * $line['qty'],
    ];
    $total += $line['price'] * $line['qty'];
    foreach ($products as &$product) {
        if ($product['id'] === $line['id'] && ($product['tracks_stock'] ?? true)) {
            $product['stock'] -= $line['qty'];
            break;
        }
    }
    unset($product);
}

hp_cafeteria_save_json_locked($productsFh, $products);

// Paso 3: recién ahora se saca la comanda de la lista de abiertas. Si en el
// medio ya la cerraron/cancelaron desde otro lado, no hay nada que sacar.
$fh = null;
$comandas = hp_cafeteria_load_json_locked('comandas.json', [], $fh);
foreach ($comandas as $i => $c) {
    if ($c['id'] === $comandaId) {
        array_splice($comandas, $i, 1);
        break;
    }
}
hp_cafeteria_save_json_locked($fh, $comandas);

$sale = [
    'id' => uniqid('sale_', true),
    'items' => $saleItems,
    'buyer_type' => $buyerType,
    'guest' => $guest,
    'payment_method' => $paymentMethod,
    'total' => $total,
    'date' => date('c'),
    'table_name' => $comanda['name'],
    'tab_type' => $comanda['type'],
];

hp_cafeteria_append_json(hp_cafeteria_sales_file(), $sale);

echo json_encode(['ok' => true, 'sale' => $sale]);
