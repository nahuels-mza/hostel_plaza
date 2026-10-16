<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_admin();
$cafeteriaRole = hp_cafeteria_role();
$cafeteriaUsername = $_SESSION['cafeteria_username'] ?? '';

$products = hp_cafeteria_load_json('products.json');
$ingredients = hp_cafeteria_load_json('ingredients.json');
$purchases = hp_cafeteria_load_json('purchases.json');

// Margen actual por producto vendible (insumos no tienen precio de venta propio).
$margins = [];
foreach ($products as $p) {
    if (!$p['active']) continue;
    $price = (float)$p['price'];
    $cost = isset($p['last_cost']) ? (float)$p['last_cost'] : null;
    $margin = null;
    $marginPct = null;
    if ($cost !== null && $price > 0) {
        $margin = round($price - $cost, 2);
        $marginPct = round(($margin / $price) * 100, 1);
    }
    $margins[] = [
        'name' => $p['name'],
        'price' => $price,
        'cost' => $cost,
        'margin' => $margin,
        'margin_pct' => $marginPct,
        'low_margin' => $marginPct !== null && $marginPct < 20,
        'missing_data' => $cost === null || $price <= 0,
    ];
}
usort($margins, function ($a, $b) {
    // Sin dato primero (necesitan atención), después menor margen % primero.
    if ($a['missing_data'] !== $b['missing_data']) return $a['missing_data'] ? -1 : 1;
    return ($a['margin_pct'] ?? -1) <=> ($b['margin_pct'] ?? -1);
});

function hp_cafeteria_kind_label(string $kind): string
{
    return match ($kind) {
        'ingredient' => 'Insumo',
        'production' => 'Producción propia',
        default => 'Producto',
    };
}

// Evolución de costo: para cada ítem con 2+ compras, comparar la última vs la anterior.
// 'product' y 'production' del mismo item_id se agrupan juntos: ambos representan
// "cuánto sale conseguir una unidad más de este producto", ya sea comprándolo o
// haciéndolo en casa.
$byItem = [];
foreach ($purchases as $purchase) {
    $group = $purchase['kind'] === 'ingredient' ? 'ingredient' : 'product';
    $key = $group . ':' . $purchase['item_id'];
    $byItem[$key][] = $purchase;
}
$evolution = [];
foreach ($byItem as $key => $rows) {
    usort($rows, fn($a, $b) => strcmp($a['date'], $b['date']));
    $count = count($rows);
    if ($count < 2) continue;
    $last = $rows[$count - 1];
    $prev = $rows[$count - 2];
    $change = $prev['unit_cost'] > 0 ? round((($last['unit_cost'] - $prev['unit_cost']) / $prev['unit_cost']) * 100, 1) : null;
    $evolution[] = [
        'name' => $last['item_name'],
        'kind' => $last['kind'],
        'prev_cost' => $prev['unit_cost'],
        'last_cost' => $last['unit_cost'],
        'change_pct' => $change,
        'last_date' => $last['date'],
    ];
}
usort($evolution, fn($a, $b) => ($b['change_pct'] ?? -999) <=> ($a['change_pct'] ?? -999));

// Historial de compras, más reciente primero.
$purchaseLog = $purchases;
usort($purchaseLog, fn($a, $b) => strcmp($b['date'], $a['date']));
$purchaseLog = array_slice($purchaseLog, 0, 50);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Costos | Gato Negro</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
    <header class="bg-teal text-white px-6 py-4 flex items-center justify-between shadow">
        <h1 class="text-xl font-bold">Gato Negro · Costos</h1>
        <nav class="flex items-center gap-4 text-sm">
            <span class="text-xs bg-white/15 px-2 py-1 rounded-full"><?= htmlspecialchars($cafeteriaUsername) ?> · <?= htmlspecialchars($cafeteriaRole) ?></span>
            <a href="/cafeteriagatonegro" class="hover:underline">Comanda</a>
            <a href="/cafeteriagatonegro/stock" class="hover:underline">Stock</a>
            <a href="/cafeteriagatonegro/insumos" class="hover:underline">Insumos</a>
            <a href="/cafeteriagatonegro/reportes" class="hover:underline">Reportes</a>
            <a href="/cafeteriagatonegro/ventas" class="hover:underline">Ventas</a>
            <a href="/cafeteriagatonegro/logout" class="hover:underline">Salir</a>
        </nav>
    </header>

    <main class="max-w-5xl mx-auto p-4 space-y-6">
        <section>
            <h2 class="text-lg font-bold mb-2 text-teal">Margen por producto</h2>
            <p class="text-xs text-slate-500 mb-2">
                Margen = precio de venta − costo de la última compra o producción registrada. Sin ninguna de las dos no hay costo con el que comparar.
            </p>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3">Precio venta</th>
                            <th class="px-4 py-3">Costo últ. compra/producción</th>
                            <th class="px-4 py-3">Margen</th>
                            <th class="px-4 py-3">Margen %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($margins as $m): ?>
                            <tr class="border-t <?= $m['missing_data'] ? 'bg-amber-50' : ($m['low_margin'] ? 'bg-red-50' : '') ?>">
                                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($m['name']) ?></td>
                                <td class="px-4 py-2"><?= $m['price'] > 0 ? '$' . number_format($m['price'], 2, ',', '.') : '<span class="text-slate-400">sin definir</span>' ?></td>
                                <td class="px-4 py-2"><?= $m['cost'] !== null ? '$' . number_format($m['cost'], 2, ',', '.') : '<span class="text-slate-400">sin datos</span>' ?></td>
                                <td class="px-4 py-2"><?= $m['margin'] !== null ? '$' . number_format($m['margin'], 2, ',', '.') : '—' ?></td>
                                <td class="px-4 py-2 font-semibold">
                                    <?php if ($m['margin_pct'] !== null): ?>
                                        <?= $m['margin_pct'] ?>%
                                        <?= $m['low_margin'] ? ' ⚠️' : '' ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-500 mt-2">⚠️ = margen por debajo del 20%. Fondo ámbar = falta precio y/o costo para poder calcularlo.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2 text-teal">Evolución de costos</h2>
            <p class="text-xs text-slate-500 mb-2">Comparación entre la última compra registrada y la anterior, por ítem (solo los que ya tienen 2 o más compras).</p>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Ítem</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Costo anterior</th>
                            <th class="px-4 py-3">Costo última compra</th>
                            <th class="px-4 py-3">Variación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($evolution)): ?>
                            <tr><td colspan="5" class="px-4 py-3 text-slate-400 text-center">Todavía no hay suficientes compras registradas para comparar.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($evolution as $ev): ?>
                            <tr class="border-t <?= ($ev['change_pct'] ?? 0) > 10 ? 'bg-red-50' : '' ?>">
                                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($ev['name']) ?></td>
                                <td class="px-4 py-2 text-slate-500"><?= hp_cafeteria_kind_label($ev['kind']) ?></td>
                                <td class="px-4 py-2">$<?= number_format($ev['prev_cost'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2">$<?= number_format($ev['last_cost'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2 font-semibold">
                                    <?php if ($ev['change_pct'] !== null): ?>
                                        <?= $ev['change_pct'] > 0 ? '+' : '' ?><?= $ev['change_pct'] ?>%
                                        <?= $ev['change_pct'] > 10 ? ' ⚠️' : '' ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-slate-500 mt-2">⚠️ = el costo subió más de 10% respecto a la compra anterior.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2 text-teal">Historial de compras y producción</h2>
            <p class="text-xs text-slate-500 mb-2">Últimas 50 registradas, más reciente primero.</p>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Ítem</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Cantidad</th>
                            <th class="px-4 py-3">Costo unitario</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Quién</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($purchaseLog)): ?>
                            <tr><td colspan="7" class="px-4 py-3 text-slate-400 text-center">Todavía no se registraron compras.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($purchaseLog as $row): ?>
                            <tr class="border-t">
                                <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['date']))) ?></td>
                                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($row['item_name']) ?></td>
                                <td class="px-4 py-2 text-slate-500"><?= hp_cafeteria_kind_label($row['kind']) ?></td>
                                <td class="px-4 py-2"><?= rtrim(rtrim(number_format($row['qty'], 2, ',', ''), '0'), ',') ?></td>
                                <td class="px-4 py-2">$<?= number_format($row['unit_cost'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2 font-semibold">$<?= number_format($row['total_cost'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($row['admin_username']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
