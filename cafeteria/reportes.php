<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_admin();
$cafeteriaRole = hp_cafeteria_role();
$cafeteriaUsername = $_SESSION['cafeteria_username'] ?? '';

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$sales = hp_cafeteria_load_sales_for_month($month);

$totalRevenue = 0;
$saleCount = count($sales);
$productTotals = []; // id => ['name'=>, 'qty'=>, 'revenue'=>]
$buyerTotals = [
    'huesped' => ['count' => 0, 'revenue' => 0],
    'no_huesped' => ['count' => 0, 'revenue' => 0],
];
$paymentTotals = ['Efectivo' => 0, 'Transferencia' => 0, 'Tarjeta' => 0];

foreach ($sales as $sale) {
    $totalRevenue += $sale['total'];

    $buyerType = $sale['buyer_type'] ?? 'no_huesped';
    if (!isset($buyerTotals[$buyerType])) {
        $buyerTotals[$buyerType] = ['count' => 0, 'revenue' => 0];
    }
    $buyerTotals[$buyerType]['count']++;
    $buyerTotals[$buyerType]['revenue'] += $sale['total'];

    $method = $sale['payment_method'] ?? '';
    if (isset($paymentTotals[$method])) {
        $paymentTotals[$method] += $sale['total'];
    }

    foreach ($sale['items'] as $item) {
        $id = $item['id'];
        if (!isset($productTotals[$id])) {
            $productTotals[$id] = ['name' => $item['name'], 'qty' => 0, 'revenue' => 0];
        }
        $productTotals[$id]['qty'] += $item['qty'];
        $productTotals[$id]['revenue'] += $item['subtotal'];
    }
}

uasort($productTotals, fn($a, $b) => $b['qty'] <=> $a['qty']);
$topProducts = array_slice($productTotals, 0, 15, true);

$totalGuestSales = $buyerTotals['huesped']['count'] + $buyerTotals['no_huesped']['count'];

// Para el selector de mes: últimos 12 meses.
$monthOptions = [];
for ($i = 0; $i < 12; $i++) {
    $monthOptions[] = date('Y-m', strtotime("-$i months"));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Ventas | Gato Negro</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
    <header class="bg-teal text-white px-6 py-4 flex items-center justify-between shadow">
        <h1 class="text-xl font-bold">Gato Negro · Reportes de Ventas</h1>
        <nav class="flex items-center gap-4 text-sm">
            <span class="text-xs bg-white/15 px-2 py-1 rounded-full"><?= htmlspecialchars($cafeteriaUsername) ?> · <?= htmlspecialchars($cafeteriaRole) ?></span>
            <a href="/cafeteriagatonegro" class="hover:underline">Comanda</a>
            <a href="/cafeteriagatonegro/stock" class="hover:underline">Stock</a>
            <a href="/cafeteriagatonegro/insumos" class="hover:underline">Insumos</a>
            <a href="/cafeteriagatonegro/costos" class="hover:underline">Costos</a>
            <a href="/cafeteriagatonegro/logout" class="hover:underline">Salir</a>
        </nav>
    </header>

    <main class="max-w-5xl mx-auto p-4 space-y-6">
        <form method="GET" class="bg-white rounded-xl shadow p-4 flex items-center gap-3 text-sm">
            <label class="font-semibold">Mes:</label>
            <select name="month" onchange="this.form.submit()" class="border rounded px-2 py-1">
                <?php foreach ($monthOptions as $m): ?>
                    <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $m ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white rounded-xl shadow p-4">
                <div class="text-xs text-slate-500 uppercase">Total vendido</div>
                <div class="text-2xl font-bold text-teal">$<?= number_format($totalRevenue, 2, ',', '.') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <div class="text-xs text-slate-500 uppercase">Cantidad de ventas</div>
                <div class="text-2xl font-bold text-teal"><?= $saleCount ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <div class="text-xs text-slate-500 uppercase">Ticket promedio</div>
                <div class="text-2xl font-bold text-teal">$<?= $saleCount > 0 ? number_format($totalRevenue / $saleCount, 2, ',', '.') : '0,00' ?></div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2 text-teal">Huésped vs. no huésped</h2>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Ventas</th>
                            <th class="px-4 py-3">% de las ventas</th>
                            <th class="px-4 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t">
                            <td class="px-4 py-2 font-semibold">Huésped</td>
                            <td class="px-4 py-2"><?= $buyerTotals['huesped']['count'] ?></td>
                            <td class="px-4 py-2"><?= $totalGuestSales > 0 ? round($buyerTotals['huesped']['count'] / $totalGuestSales * 100, 1) : 0 ?>%</td>
                            <td class="px-4 py-2 font-semibold">$<?= number_format($buyerTotals['huesped']['revenue'], 2, ',', '.') ?></td>
                        </tr>
                        <tr class="border-t">
                            <td class="px-4 py-2 font-semibold">No huésped</td>
                            <td class="px-4 py-2"><?= $buyerTotals['no_huesped']['count'] ?></td>
                            <td class="px-4 py-2"><?= $totalGuestSales > 0 ? round($buyerTotals['no_huesped']['count'] / $totalGuestSales * 100, 1) : 0 ?>%</td>
                            <td class="px-4 py-2 font-semibold">$<?= number_format($buyerTotals['no_huesped']['revenue'], 2, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2 text-teal">Por método de pago</h2>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Método</th>
                            <th class="px-4 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentTotals as $method => $amount): ?>
                            <tr class="border-t">
                                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($method) ?></td>
                                <td class="px-4 py-2">$<?= number_format($amount, 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-bold mb-2 text-teal">Productos más vendidos</h2>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3">Cantidad vendida</th>
                            <th class="px-4 py-3">Total facturado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topProducts)): ?>
                            <tr><td colspan="4" class="px-4 py-3 text-slate-400 text-center">No hay ventas registradas en <?= $month ?>.</td></tr>
                        <?php endif; ?>
                        <?php $rank = 1; foreach ($topProducts as $p): ?>
                            <tr class="border-t">
                                <td class="px-4 py-2 text-slate-500"><?= $rank++ ?></td>
                                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($p['name']) ?></td>
                                <td class="px-4 py-2"><?= $p['qty'] ?></td>
                                <td class="px-4 py-2 font-semibold">$<?= number_format($p['revenue'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>
