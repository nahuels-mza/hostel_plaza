<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_auth();
$cafeteriaRole = hp_cafeteria_role();
$cafeteriaUsername = $_SESSION['cafeteria_username'] ?? '';
$isAdmin = hp_cafeteria_is_admin();

$allProducts = hp_cafeteria_load_json('products.json');
$ingredients = hp_cafeteria_load_json('ingredients.json');
$recipes = hp_cafeteria_load_json('recipes.json');

$products = array_values(array_filter($allProducts, fn($p) => $p['tracks_stock'] ?? true));
$noStockProducts = array_values(array_filter($allProducts, fn($p) => !($p['tracks_stock'] ?? true)));

$colCount = $isAdmin ? 6 : 4;
$noStockColCount = $isAdmin ? 4 : 3;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock de Productos | Gato Negro</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } } } } }
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
    <header class="bg-teal text-white px-6 py-4 flex items-center justify-between shadow">
        <h1 class="text-xl font-bold">Gato Negro · Stock de Productos</h1>
        <nav class="flex items-center gap-4 text-sm">
            <span class="text-xs bg-white/15 px-2 py-1 rounded-full"><?= htmlspecialchars($cafeteriaUsername) ?> · <?= htmlspecialchars($cafeteriaRole) ?></span>
            <a href="/cafeteriagatonegro" class="hover:underline">Comanda</a>
            <a href="/cafeteriagatonegro/insumos" class="hover:underline">Insumos</a>
            <?php if ($isAdmin): ?><a href="/cafeteriagatonegro/costos" class="hover:underline">Costos</a><?php endif; ?>
            <?php if ($isAdmin): ?><a href="/cafeteriagatonegro/reportes" class="hover:underline">Reportes</a><?php endif; ?>
            <a href="/cafeteriagatonegro/logout" class="hover:underline">Salir</a>
        </nav>
    </header>

    <main class="max-w-5xl mx-auto p-4 space-y-4">
        <?php if (!$isAdmin): ?>
            <p class="text-sm text-slate-500 bg-white rounded-xl shadow p-3">
                Solo podés consultar el stock. Cargar cantidades o nuevos productos es una acción de administrador.
            </p>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Categoría</th>
                        <th class="px-4 py-3">Precio</th>
                        <?php if ($isAdmin): ?><th class="px-4 py-3">Costo últ. compra/producción</th><?php endif; ?>
                        <th class="px-4 py-3">Stock</th>
                        <?php if ($isAdmin): ?><th class="px-4 py-3">Acciones</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody id="stock-body">
                    <?php foreach ($products as $p): ?>
                        <?php
                        $madeInHouse = $p['made_in_house'] ?? false;
                        $recipeLines = $recipes[$p['id']] ?? [];
                        $recipeByIngredient = [];
                        foreach ($recipeLines as $line) {
                            $recipeByIngredient[$line['ingredient_id']] = $line['qty'];
                        }
                        ?>
                        <tr class="product-row border-t" data-id="<?= htmlspecialchars($p['id']) ?>">
                            <td class="px-4 py-2 font-semibold">
                                <?= htmlspecialchars($p['name']) ?>
                                <?php if ($madeInHouse): ?><span class="text-xs text-slate-400" title="Producido por nosotros">🏠</span><?php endif; ?>
                            </td>
                            <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($p['category']) ?></td>
                            <td class="px-4 py-2 price-cell">
                                <?= $p['price'] > 0 ? '$' . number_format($p['price'], 2, ',', '.') : '<span class="text-slate-400">sin definir</span>' ?>
                                <?php if ($isAdmin): ?>
                                    <button class="price-edit-toggle text-teal underline text-xs ml-1">editar</button>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td class="px-4 py-2 text-slate-500">
                                <?= isset($p['last_cost']) ? '$' . number_format($p['last_cost'], 0, ',', '.') : '—' ?>
                            </td>
                            <?php endif; ?>
                            <td class="px-4 py-2 stock-cell">
                                <span class="font-bold"><?= (int)$p['stock'] ?></span>
                                <?= $p['stock'] <= $p['min_stock'] ? '<span title="Stock bajo">⚠️</span>' : '' ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <button class="adjust-toggle text-xs border rounded px-2 py-1 hover:bg-slate-100">Ajustar</button>
                                    <button class="purchase-toggle text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Registrar compra</button>
                                    <?php if ($madeInHouse): ?>
                                        <button class="recipe-toggle text-xs border rounded px-2 py-1 hover:bg-slate-100">Receta</button>
                                        <button class="produce-toggle text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Registrar producción</button>
                                    <?php endif; ?>
                                    <button class="config-toggle text-xs border rounded px-2 py-1 hover:bg-slate-100">Config</button>
                                    <button class="delete-btn text-xs border border-red-200 text-red-600 rounded px-2 py-1 hover:bg-red-50">Eliminar</button>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php if ($isAdmin): ?>
                        <tr class="price-edit-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($p['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <label class="text-xs text-slate-500">Nuevo precio de venta:</label>
                                    <input type="number" min="0" step="0.01" class="price-input w-28 border rounded px-2 py-1" placeholder="ej: 1500.00">
                                    <button class="price-save-btn text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Guardar</button>
                                    <span class="price-error text-xs text-red-600"></span>
                                </div>
                            </td>
                        </tr>
                        <tr class="adjust-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($p['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-xs text-slate-500">Corrección manual (sin costo):</span>
                                    <button class="delta-btn border rounded px-2 py-1 hover:bg-white" data-delta="-1">−1</button>
                                    <button class="delta-btn border rounded px-2 py-1 hover:bg-white" data-delta="1">+1</button>
                                    <label class="text-xs text-slate-500 ml-2">o fijar en:</label>
                                    <input type="number" class="set-input w-20 border rounded px-2 py-1" placeholder="cant.">
                                    <button class="set-btn text-xs border rounded px-2 py-1 bg-slate-600 text-white hover:bg-slate-700">Fijar</button>
                                </div>
                            </td>
                        </tr>
                        <tr class="purchase-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($p['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-xs text-slate-500">Compra:</span>
                                    <label class="text-xs text-slate-500">Cantidad</label>
                                    <input type="number" min="1" class="purchase-qty-input w-20 border rounded px-2 py-1" placeholder="cant.">
                                    <label class="text-xs text-slate-500">Costo unitario</label>
                                    <input type="number" min="0" step="0.01" class="purchase-cost-input w-24 border rounded px-2 py-1" placeholder="costo u.">
                                    <button class="purchase-btn text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Confirmar compra</button>
                                </div>
                            </td>
                        </tr>
                        <?php if ($madeInHouse): ?>
                        <tr class="recipe-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($p['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-3">
                                <div class="text-xs text-slate-500 mb-2">Receta (cantidad de cada insumo por unidad producida de "<?= htmlspecialchars($p['name']) ?>"):</div>
                                <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm recipe-inputs">
                                    <?php foreach ($ingredients as $ing): ?>
                                        <label class="flex items-center gap-1">
                                            <span class="text-xs w-28 truncate" title="<?= htmlspecialchars($ing['name']) ?>"><?= htmlspecialchars($ing['name']) ?></span>
                                            <input type="number" min="0" step="0.001" class="recipe-qty-input w-20 border rounded px-2 py-1" data-ingredient-id="<?= htmlspecialchars($ing['id']) ?>" value="<?= isset($recipeByIngredient[$ing['id']]) ? $recipeByIngredient[$ing['id']] : '' ?>" placeholder="0">
                                            <span class="text-xs text-slate-400"><?= htmlspecialchars($ing['unit']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button class="recipe-save-btn text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover mt-2">Guardar receta</button>
                                <span class="recipe-error text-xs text-red-600"></span>
                            </td>
                        </tr>
                        <tr class="produce-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($p['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="text-xs text-slate-500">Producir:</span>
                                    <label class="text-xs text-slate-500">Cantidad a producir</label>
                                    <input type="number" min="1" class="produce-qty-input w-20 border rounded px-2 py-1" placeholder="cant.">
                                    <button class="produce-btn text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Producir</button>
                                    <span class="produce-error text-xs text-red-600"></span>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr class="config-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($p['id']) ?>">
                            <td colspan="<?= $colCount ?>" class="px-4 py-2">
                                <div class="flex items-center gap-4 text-sm">
                                    <label class="flex items-center gap-1">
                                        <input type="checkbox" class="made-in-house-checkbox" <?= $madeInHouse ? 'checked' : '' ?>>
                                        Producido por nosotros
                                    </label>
                                    <button class="mark-no-stock-btn text-xs border border-amber-300 text-amber-700 rounded px-2 py-1 hover:bg-amber-50">
                                        Marcar como "sin stock propio" (lo saca de esta tabla)
                                    </button>
                                    <button class="save-flags-btn text-xs border rounded px-2 py-1 bg-slate-600 text-white hover:bg-slate-700">Guardar</button>
                                </div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($noStockProducts)): ?>
        <section>
            <h2 class="text-lg font-bold mb-2 text-teal">Vendibles sin stock propio</h2>
            <p class="text-xs text-slate-500 mb-2">Se venden siempre desde la comanda, sin llevar un contador de unidades (ej. algo preparado al momento, o un servicio).</p>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3">Categoría</th>
                            <th class="px-4 py-3">Precio</th>
                            <?php if ($isAdmin): ?><th class="px-4 py-3">Acciones</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="no-stock-body">
                        <?php foreach ($noStockProducts as $p): ?>
                            <tr class="border-t" data-id="<?= htmlspecialchars($p['id']) ?>">
                                <td class="px-4 py-2 font-semibold"><?= htmlspecialchars($p['name']) ?></td>
                                <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($p['category']) ?></td>
                                <td class="px-4 py-2 price-cell">
                                    <?= $p['price'] > 0 ? '$' . number_format($p['price'], 2, ',', '.') : '<span class="text-slate-400">sin definir</span>' ?>
                                    <?php if ($isAdmin): ?><button class="price-edit-toggle text-teal underline text-xs ml-1">editar</button><?php endif; ?>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-2">
                                        <button class="activate-stock-btn text-xs border rounded px-2 py-1 hover:bg-slate-100">Activar stock</button>
                                        <button class="delete-btn text-xs border border-red-200 text-red-600 rounded px-2 py-1 hover:bg-red-50">Eliminar</button>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php if ($isAdmin): ?>
                            <tr class="price-edit-row hidden border-t bg-slate-50" data-id="<?= htmlspecialchars($p['id']) ?>">
                                <td colspan="<?= $noStockColCount ?>" class="px-4 py-2">
                                    <div class="flex items-center gap-2 text-sm">
                                        <label class="text-xs text-slate-500">Nuevo precio de venta:</label>
                                        <input type="number" min="0" step="0.01" class="price-input w-28 border rounded px-2 py-1" placeholder="ej: 1500.00">
                                        <button class="price-save-btn text-xs border rounded px-2 py-1 bg-teal text-white hover:bg-teal-hover">Guardar</button>
                                        <span class="price-error text-xs text-red-600"></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="text-lg font-bold mb-3">Nuevo producto</h2>
            <p class="text-xs text-slate-500 mb-2">
                El precio de venta es opcional acá — se puede definir más adelante desde "editar" en la tabla.
                El stock y la unidad se cargan después, al registrar la primera compra o producción.
            </p>
            <form id="new-product-form" class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                <input name="name" required placeholder="Nombre" class="border rounded px-2 py-1 col-span-2">
                <input name="category" required placeholder="Categoría (Comida/Bebida/...)" class="border rounded px-2 py-1">
                <input name="price" type="number" min="0" step="0.01" placeholder="Precio (opcional)" class="border rounded px-2 py-1">
                <button type="submit" class="bg-teal text-white rounded px-3 py-1 hover:bg-teal-hover col-span-2 md:col-span-4">Crear producto</button>
            </form>
            <p id="new-product-feedback" class="text-sm mt-2"></p>
        </div>
        <?php endif; ?>
    </main>

    <?php if ($isAdmin): ?>
    <script>
        // Número positivo con hasta 2 decimales (acepta "1500", "1500.5", "1500.50").
        const PRICE_RE = /^\d+(\.\d{1,2})?$/;

        function toggleRow(id, cls) {
            document.querySelector(`tr.${cls}[data-id="${id}"]`)?.classList.toggle('hidden');
        }

        async function updateStock(id, mode, value) {
            const res = await fetch('/cafeteriagatonegro/update_stock.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, mode, value }),
            });
            return res.json();
        }

        function applyStock(id, product) {
            const cell = document.querySelector(`tr.product-row[data-id="${id}"] .stock-cell`);
            if (!cell) return;
            cell.innerHTML = `<span class="font-bold">${product.stock}</span>` +
                (product.stock <= product.min_stock ? ' <span title="Stock bajo">⚠️</span>' : '');
        }

        async function handlePriceSave(row, id) {
            const priceInput = row.querySelector('.price-input');
            const errorEl = row.querySelector('.price-error');
            errorEl.textContent = '';
            if (priceInput.value === '') return;
            if (!PRICE_RE.test(priceInput.value.trim())) {
                errorEl.textContent = 'Precio inválido: usá un número con hasta 2 decimales (ej: 1500.50).';
                return;
            }
            const res = await fetch('/cafeteriagatonegro/update_price.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, price: parseFloat(priceInput.value) }),
            });
            const data = await res.json();
            if (data.ok) {
                location.reload();
            } else {
                errorEl.textContent = 'Error al guardar el precio.';
            }
        }

        async function handleDelete(row, id) {
            const name = row.querySelector('td')?.textContent.trim() || id;
            if (!confirm(`¿Eliminar "${name}"? Esta acción no se puede deshacer. El historial de ventas y compras ya registrado no se toca.`)) {
                return;
            }
            const res = await fetch('/cafeteriagatonegro/delete_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id }),
            });
            const data = await res.json();
            if (data.ok) {
                location.reload();
            } else {
                alert('Error al eliminar el producto.');
            }
        }

        document.getElementById('stock-body').addEventListener('click', async (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const id = row.dataset.id;

            if (e.target.classList.contains('adjust-toggle')) toggleRow(id, 'adjust-row');
            if (e.target.classList.contains('purchase-toggle')) toggleRow(id, 'purchase-row');
            if (e.target.classList.contains('price-edit-toggle')) toggleRow(id, 'price-edit-row');
            if (e.target.classList.contains('recipe-toggle')) toggleRow(id, 'recipe-row');
            if (e.target.classList.contains('produce-toggle')) toggleRow(id, 'produce-row');
            if (e.target.classList.contains('config-toggle')) toggleRow(id, 'config-row');

            if (e.target.classList.contains('delta-btn')) {
                const data = await updateStock(id, 'delta', parseInt(e.target.dataset.delta, 10));
                if (data.ok) applyStock(id, data.products.find(p => p.id === id));
            }

            if (e.target.classList.contains('set-btn')) {
                const input = row.querySelector('.set-input');
                if (input.value === '') return;
                const data = await updateStock(id, 'set', parseInt(input.value, 10));
                if (data.ok) {
                    applyStock(id, data.products.find(p => p.id === id));
                    input.value = '';
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
                        kind: 'product',
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

            if (e.target.classList.contains('recipe-save-btn')) {
                const recipeRow = document.querySelector(`tr.recipe-row[data-id="${id}"]`);
                const errorEl = recipeRow.querySelector('.recipe-error');
                errorEl.textContent = '';
                const items = Array.from(recipeRow.querySelectorAll('.recipe-qty-input'))
                    .map(input => ({ ingredient_id: input.dataset.ingredientId, qty: parseFloat(input.value) }))
                    .filter(item => !isNaN(item.qty) && item.qty > 0);
                const res = await fetch('/cafeteriagatonegro/update_recipe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: id, items }),
                });
                const data = await res.json();
                if (data.ok) {
                    errorEl.textContent = 'Receta guardada ✔';
                    errorEl.className = 'text-xs text-green-600';
                } else {
                    errorEl.textContent = 'Error al guardar la receta.';
                    errorEl.className = 'text-xs text-red-600';
                }
            }

            if (e.target.classList.contains('produce-btn')) {
                const qtyInput = row.querySelector('.produce-qty-input');
                const errorEl = row.querySelector('.produce-error');
                errorEl.textContent = '';
                if (qtyInput.value === '') return;
                const res = await fetch('/cafeteriagatonegro/produce_product.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: id, qty: parseFloat(qtyInput.value) }),
                });
                const data = await res.json();
                if (data.ok) {
                    location.reload();
                } else if (data.error === 'insufficient_ingredients') {
                    const detail = data.details.map(d => `${d.name} (necesita: ${d.needed}, disponible: ${d.available})`).join(', ');
                    errorEl.textContent = 'Insumos insuficientes: ' + detail;
                } else if (data.error === 'no_recipe') {
                    errorEl.textContent = 'Definí la receta primero.';
                } else {
                    errorEl.textContent = 'Error al registrar la producción.';
                }
            }

            if (e.target.classList.contains('save-flags-btn')) {
                const checkbox = row.querySelector('.made-in-house-checkbox');
                const res = await fetch('/cafeteriagatonegro/update_flags.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, made_in_house: checkbox.checked }),
                });
                const data = await res.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error al guardar la configuración.');
                }
            }

            if (e.target.classList.contains('mark-no-stock-btn')) {
                if (!confirm('¿Marcar este producto como "sin stock propio"? Va a dejar de aparecer en esta tabla y en la comanda nunca se va a bloquear por falta de stock.')) {
                    return;
                }
                const res = await fetch('/cafeteriagatonegro/update_flags.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, tracks_stock: false }),
                });
                const data = await res.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error al actualizar el producto.');
                }
            }

            if (e.target.classList.contains('delete-btn')) await handleDelete(row, id);
            if (e.target.classList.contains('price-save-btn')) await handlePriceSave(row, id);
        });

        document.getElementById('no-stock-body')?.addEventListener('click', async (e) => {
            const row = e.target.closest('tr');
            if (!row) return;
            const id = row.dataset.id;

            if (e.target.classList.contains('price-edit-toggle')) toggleRow(id, 'price-edit-row');
            if (e.target.classList.contains('price-save-btn')) await handlePriceSave(row, id);
            if (e.target.classList.contains('delete-btn')) await handleDelete(row, id);

            if (e.target.classList.contains('activate-stock-btn')) {
                const res = await fetch('/cafeteriagatonegro/update_flags.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id, tracks_stock: true }),
                });
                const data = await res.json();
                if (data.ok) {
                    location.reload();
                } else {
                    alert('Error al actualizar el producto.');
                }
            }
        });

        document.getElementById('new-product-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const feedback = document.getElementById('new-product-feedback');
            if (form.price.value !== '' && !PRICE_RE.test(form.price.value.trim())) {
                feedback.textContent = 'Precio inválido: usá un número con hasta 2 decimales (ej: 1500.50).';
                feedback.className = 'text-sm mt-2 text-red-600';
                return;
            }
            const payload = {
                name: form.name.value.trim(),
                category: form.category.value.trim(),
                price: form.price.value === '' ? 0 : parseFloat(form.price.value),
            };
            const res = await fetch('/cafeteriagatonegro/create_product.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.ok) {
                location.reload();
            } else {
                feedback.textContent = data.error === 'duplicate_id'
                    ? 'Ya existe un producto con ese nombre.'
                    : 'Error al crear el producto.';
                feedback.className = 'text-sm mt-2 text-red-600';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
