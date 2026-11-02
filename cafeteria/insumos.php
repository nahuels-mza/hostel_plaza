<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_auth();
$cafeteriaRole = hp_cafeteria_role();
$cafeteriaUsername = $_SESSION['cafeteria_username'] ?? '';
$isAdmin = hp_cafeteria_is_admin();

$ingredients = hp_cafeteria_load_json('ingredients.json');
$history = hp_cafeteria_load_json('ingredient_history.json');
$colCount = $isAdmin ? 6 : 4;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insumos | Gato Negro</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } } } } }
    </script>
    <?php include __DIR__ . '/_responsive_table.php'; ?>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
    <?php $cafeteriaTitle = 'Insumos'; $cafeteriaActive = 'insumos'; include __DIR__ . '/_header.php'; ?>

    <main class="max-w-5xl mx-auto p-4 space-y-4">
        <p class="text-sm text-slate-500 bg-white rounded-xl shadow p-3">
            Insumos de cocina/desayuno (no se venden por unidad, no bajan solos con la comanda).
            <?= $isAdmin ? 'Actualizá la cantidad cuando hagas el conteo o registrá una compra con su costo.' : 'Solo podés consultar cantidades — actualizarlas es una acción de administrador.' ?>
        </p>

        <input id="ingredient-search" type="search" placeholder="Buscar insumo..." class="w-full border rounded-xl px-4 py-2.5 text-sm shadow-sm">

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-sm responsive-table">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Insumo</th>
                        <th class="px-4 py-3">Cantidad</th>
                        <?php if ($isAdmin): ?><th class="px-4 py-3">Costo últ. compra</th><?php endif; ?>
                        <th class="px-4 py-3">Última actualización</th>
                        <?php if ($isAdmin): ?><th class="px-4 py-3">Acciones</th><?php endif; ?>
                        <th class="px-4 py-3">Historial</th>
                    </tr>
                </thead>
                <tbody id="ingredients-body">
                    <?php foreach ($ingredients as $ing): ?>
                        <tr class="ingredient-row border-t" data-id="<?= htmlspecialchars($ing['id']) ?>" data-name="<?= htmlspecialchars(mb_strtolower($ing['name'])) ?>">
                            <td class="px-4 py-2 font-semibold" data-label="Insumo"><?= htmlspecialchars($ing['name']) ?></td>
                            <td class="px-4 py-2 qty-cell" data-label="Cantidad">
                                <span class="font-bold"><?= rtrim(rtrim(number_format($ing['stock'], 2, ',', ''), '0'), ',') ?></span>
                                <span class="text-slate-500"><?= htmlspecialchars($ing['unit']) ?></span>
                                <?= $ing['stock'] <= $ing['min_stock'] ? ' <span title="Stock bajo">⚠️</span>' : '' ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td class="px-4 py-2 text-slate-500" data-label="Costo últ. compra">
                                <?= isset($ing['last_cost']) ? '$' . number_format($ing['last_cost'], 0, ',', '.') : '—' ?>
                            </td>
                            <?php endif; ?>
                            <td class="px-4 py-2 text-slate-500 updated-cell" data-label="Actualizado">
                                <?= htmlspecialchars($ing['updated_at']) ?> (<?= htmlspecialchars($ing['updated_by']) ?>)
                            </td>
                            <?php if ($isAdmin): ?>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <button class="adjust-toggle text-xs border rounded px-2 py-1 hover:bg-slate-100">Ajustar</button>
                                    <button class="purchase-toggle text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Registrar compra</button>
                                    <button class="delete-btn text-xs border border-red-200 text-red-600 rounded px-2 py-1 hover:bg-red-50">Eliminar</button>
                                </div>
                            </td>
                            <?php endif; ?>
                            <td class="px-4 py-2">
                                <button class="history-toggle text-teal underline text-xs">ver</button>
                            </td>
                        </tr>
                        <?php if ($isAdmin): ?>
                        <tr class="adjust-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($ing['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2">
                                <div class="flex items-center gap-2 text-sm flex-wrap">
                                    <span class="text-xs text-slate-500">Fijar cantidad (conteo manual, sin costo):</span>
                                    <input type="number" step="0.5" class="qty-input w-24 border rounded px-2 py-1" placeholder="cant.">
                                    <button class="save-btn text-xs border rounded px-2 py-1 bg-slate-600 text-white hover:bg-slate-700">Fijar</button>
                                </div>
                            </td>
                        </tr>
                        <tr class="purchase-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($ing['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2">
                                <div class="flex items-center gap-2 text-sm flex-wrap">
                                    <span class="text-xs text-slate-500">Compra:</span>
                                    <label class="text-xs text-slate-500">Cantidad</label>
                                    <input type="number" step="0.5" min="0" class="purchase-qty-input w-24 border rounded px-2 py-1" placeholder="cant.">
                                    <label class="text-xs text-slate-500">Costo unitario</label>
                                    <input type="number" min="0" step="0.01" class="purchase-cost-input w-24 border rounded px-2 py-1" placeholder="costo u.">
                                    <button class="purchase-btn text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Confirmar compra</button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="history-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($ing['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2 text-xs text-slate-500">
                                <?php
                                $rows = array_reverse(array_filter($history, fn($h) => $h['id'] === $ing['id']));
                                $rows = array_slice($rows, 0, 10);
                                if (empty($rows)) {
                                    echo 'Sin historial todavía.';
                                } else {
                                    foreach ($rows as $h) {
                                        echo htmlspecialchars(date('d/m/Y H:i', strtotime($h['date']))) . ' — ' .
                                            htmlspecialchars($h['quantity']) . ' ' . htmlspecialchars($ing['unit']) .
                                            ' (' . htmlspecialchars($h['updated_by']) . ')<br>';
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($isAdmin): ?>
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="text-lg font-bold mb-3">Nuevo insumo</h2>
            <form id="new-ingredient-form" class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
                <input name="name" required placeholder="Nombre" class="border rounded px-2 py-1 col-span-2">
                <input name="unit" required placeholder="Unidad (litro, kg, caja...)" class="border rounded px-2 py-1">
                <input name="stock" type="number" step="0.5" min="0" placeholder="Cantidad inicial" value="0" class="border rounded px-2 py-1">
                <input name="min_stock" type="number" step="0.5" min="0" placeholder="Mínimo" value="0" class="border rounded px-2 py-1">
                <button type="submit" class="bg-teal text-white rounded px-3 py-1 hover:bg-teal-hover col-span-2">Crear insumo</button>
            </form>
            <p id="new-ingredient-feedback" class="text-sm mt-2"></p>
        </div>
        <?php endif; ?>
    </main>

    <script>
        document.getElementById('ingredient-search').addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            document.querySelectorAll('#ingredients-body tr.ingredient-row').forEach(row => {
                row.classList.toggle('hidden', !row.dataset.name.includes(q));
            });
        });

        function toggleRow(id, cls) {
            document.querySelector(`tr.${cls}[data-id="${id}"]`)?.classList.toggle('hidden');
        }

        document.getElementById('ingredients-body').addEventListener('click', async (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const id = row.dataset.id;

            if (e.target.classList.contains('history-toggle')) {
                toggleRow(id, 'history-row');
                return;
            }

            <?php if ($isAdmin): ?>
            if (e.target.classList.contains('adjust-toggle')) toggleRow(id, 'adjust-row');
            if (e.target.classList.contains('purchase-toggle')) toggleRow(id, 'purchase-row');

            if (e.target.classList.contains('save-btn')) {
                const qtyInput = row.querySelector('.qty-input');
                if (qtyInput.value === '') return;
                const res = await fetch('/cafeteriagatonegro/update_ingredient.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, quantity: parseFloat(qtyInput.value) }),
                });
                const data = await res.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error al actualizar.');
                }
            }

            if (e.target.classList.contains('purchase-btn')) {
                const qtyInput = row.querySelector('.purchase-qty-input');
                const costInput = row.querySelector('.purchase-cost-input');
                if (qtyInput.value === '' || costInput.value === '') {
                    alert('Completá cantidad y costo unitario de la compra.');
                    return;
                }
                const res = await fetch('/cafeteriagatonegro/add_purchase.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        kind: 'ingredient',
                        item_id: id,
                        qty: parseFloat(qtyInput.value),
                        unit_cost: parseFloat(costInput.value),
                    }),
                });
                const data = await res.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error al registrar la compra.');
                }
            }

            if (e.target.classList.contains('delete-btn')) {
                const name = row.querySelector('td')?.textContent.trim() || id;
                if (!confirm(`¿Eliminar "${name}"? Esta acción no se puede deshacer. El historial ya registrado no se toca.`)) {
                    return;
                }
                const res = await fetch('/cafeteriagatonegro/delete_ingredient.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id }),
                });
                const data = await res.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error al eliminar el insumo.');
                }
            }
            <?php endif; ?>
        });

        <?php if ($isAdmin): ?>
        document.getElementById('new-ingredient-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const feedback = document.getElementById('new-ingredient-feedback');
            const payload = {
                name: form.name.value.trim(),
                unit: form.unit.value.trim(),
                stock: parseFloat(form.stock.value || 0),
                min_stock: parseFloat(form.min_stock.value || 0),
            };
            const res = await fetch('/cafeteriagatonegro/create_ingredient.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.ok) {
                location.reload();
            } else {
                feedback.textContent = data.error === 'duplicate_id'
                    ? 'Ya existe un insumo con ese nombre.'
                    : 'Error al crear el insumo.';
                feedback.className = 'text-sm mt-2 text-red-600';
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>
