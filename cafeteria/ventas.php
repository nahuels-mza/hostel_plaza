<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_admin();
$cafeteriaRole = hp_cafeteria_role();
$cafeteriaUsername = $_SESSION['cafeteria_username'] ?? '';

$today = date('Y-m-d');
$from = $_GET['from'] ?? $today;
$to = $_GET['to'] ?? $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today;
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $today;

$sales = hp_cafeteria_load_sales_for_range($from, $to);
usort($sales, fn($a, $b) => strcmp($b['date'], $a['date'])); // más reciente primero

$totalRevenue = 0;
foreach ($sales as $sale) {
    $totalRevenue += $sale['total'];
}
$saleCount = count($sales);

$tabTypeLabels = ['mesa' => 'Mesa', 'evento' => 'Evento', 'mostrador' => 'Mostrador'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas | Gato Negro</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
    <header class="bg-teal text-white px-6 py-4 flex items-center justify-between shadow">
        <h1 class="text-xl font-bold">Gato Negro · Ventas</h1>
        <nav class="flex items-center gap-4 text-sm">
            <span class="text-xs bg-white/15 px-2 py-1 rounded-full"><?= htmlspecialchars($cafeteriaUsername) ?> · <?= htmlspecialchars($cafeteriaRole) ?></span>
            <a href="/cafeteriagatonegro" class="hover:underline">Comanda</a>
            <a href="/cafeteriagatonegro/stock" class="hover:underline">Stock</a>
            <a href="/cafeteriagatonegro/insumos" class="hover:underline">Insumos</a>
            <a href="/cafeteriagatonegro/costos" class="hover:underline">Costos</a>
            <a href="/cafeteriagatonegro/reportes" class="hover:underline">Reportes</a>
            <a href="/cafeteriagatonegro/logout" class="hover:underline">Salir</a>
        </nav>
    </header>

    <main class="max-w-5xl mx-auto p-4 space-y-4">
        <form method="GET" class="bg-white rounded-xl shadow p-4 flex flex-wrap items-end gap-3 text-sm">
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Desde</label>
                <input type="date" name="from" value="<?= htmlspecialchars($from) ?>" max="<?= $today ?>" class="border rounded px-2 py-1">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Hasta</label>
                <input type="date" name="to" value="<?= htmlspecialchars($to) ?>" max="<?= $today ?>" class="border rounded px-2 py-1">
            </div>
            <button type="submit" class="bg-teal text-white rounded px-4 py-1.5 hover:bg-teal-hover">Buscar</button>
            <div class="flex gap-2 ml-auto text-xs">
                <a href="?from=<?= $today ?>&to=<?= $today ?>" class="text-teal underline">Hoy</a>
                <a href="?from=<?= date('Y-m-d', strtotime('-6 days')) ?>&to=<?= $today ?>" class="text-teal underline">Últimos 7 días</a>
                <a href="?from=<?= date('Y-m-01') ?>&to=<?= $today ?>" class="text-teal underline">Este mes</a>
            </div>
        </form>

        <section class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow p-4">
                <div class="text-xs text-slate-500 uppercase">Total del período</div>
                <div class="text-2xl font-bold text-teal">$<?= number_format($totalRevenue, 2, ',', '.') ?></div>
            </div>
            <div class="bg-white rounded-xl shadow p-4">
                <div class="text-xs text-slate-500 uppercase">Comandas cerradas</div>
                <div class="text-2xl font-bold text-teal"><?= $saleCount ?></div>
            </div>
        </section>

        <section class="space-y-3">
            <?php if (empty($sales)): ?>
                <div class="bg-white rounded-xl shadow p-4 text-slate-400 text-center">No hay ventas registradas en ese período.</div>
            <?php endif; ?>
            <?php foreach ($sales as $sale): ?>
                <?php
                $buyerLabel = ($sale['buyer_type'] ?? 'no_huesped') === 'huesped'
                    ? 'Huésped' . ($sale['guest'] !== '' ? ' — ' . $sale['guest'] : '')
                    : 'No huésped';
                $tabType = $sale['tab_type'] ?? null;
                ?>
                <div class="bg-white rounded-xl shadow p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <div class="font-semibold">
                            <?= htmlspecialchars(date('d/m/Y H:i', strtotime($sale['date']))) ?>
                            <?php if (!empty($sale['table_name'])): ?>
                                <span class="text-slate-500 font-normal">
                                    · <?= htmlspecialchars($tabTypeLabels[$tabType] ?? 'Mesa') ?>: <?= htmlspecialchars($sale['table_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-slate-500">
                            <?= htmlspecialchars($buyerLabel) ?> · <?= htmlspecialchars($sale['payment_method']) ?>
                        </div>
                    </div>
                    <table class="w-full text-sm">
                        <tbody>
                            <?php foreach ($sale['items'] as $item): ?>
                                <tr class="border-t">
                                    <td class="py-1"><?= (int)$item['qty'] ?>x <?= htmlspecialchars($item['name']) ?></td>
                                    <td class="py-1 text-slate-500 text-right">$<?= number_format($item['price'], 2, ',', '.') ?></td>
                                    <td class="py-1 text-right w-28">$<?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="border-t font-bold">
                                <td class="py-1" colspan="2">Total</td>
                                <td class="py-1 text-right">$<?= number_format($sale['total'], 2, ',', '.') ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
