<?php
require __DIR__ . '/helpers.php';
hp_cafeteria_require_auth();
// Esta página cambia a cada add/remove de item — sin esto, el botón "atrás"
// del navegador (sobre todo en mobile) restaura una foto vieja del DOM en vez
// de volver a pedirle la lista/comanda al server.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$cafeteriaRole = hp_cafeteria_role();
$cafeteriaUsername = $_SESSION['cafeteria_username'] ?? '';
$isAdmin = hp_cafeteria_is_admin();

$comandas = hp_cafeteria_load_json('comandas.json');
$comandaId = $_GET['comanda'] ?? '';
$comanda = null;
foreach ($comandas as $c) {
    if ($c['id'] === $comandaId) {
        $comanda = $c;
        break;
    }
}
$view = $_GET['view'] ?? 'home';

// Mesa/evento vuelven al listado de mesas al cerrar/cancelar/volver; mostrador
// (venta rápida, no se abre desde ese listado) vuelve directo al home.
$backUrl = ($comanda !== null && $comanda['type'] === 'mostrador') ? '/cafeteriagatonegro' : '/cafeteriagatonegro?view=mesas';
$backLabel = ($comanda !== null && $comanda['type'] === 'mostrador') ? '‹ Inicio' : '‹ Volver a mesas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda | Gato Negro</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } } } } }
        // El botón "atrás" del navegador puede restaurar esta página desde su
        // caché (bfcache) en vez de volver a pedirla — eso deja ver una foto
        // vieja de la comanda/lista de mesas. Si pasó eso, forzamos un reload.
        window.addEventListener('pageshow', (event) => {
            if (event.persisted) window.location.reload();
        });
    </script>
</head>
<body class="bg-slate-100 text-slate-900 font-sans">
    <header class="bg-teal text-white px-6 py-4 flex items-center justify-between shadow">
        <h1 class="text-xl font-bold">Gato Negro · Comanda</h1>
        <nav class="flex items-center gap-4 text-sm">
            <span class="text-xs bg-white/15 px-2 py-1 rounded-full"><?= htmlspecialchars($cafeteriaUsername) ?> · <?= htmlspecialchars($cafeteriaRole) ?></span>
            <a href="/cafeteriagatonegro/stock" class="hover:underline">Stock</a>
            <a href="/cafeteriagatonegro/insumos" class="hover:underline">Insumos</a>
            <?php if ($isAdmin): ?><a href="/cafeteriagatonegro/costos" class="hover:underline">Costos</a><?php endif; ?>
            <?php if ($isAdmin): ?><a href="/cafeteriagatonegro/reportes" class="hover:underline">Reportes</a><?php endif; ?>
            <?php if ($isAdmin): ?><a href="/cafeteriagatonegro/ventas" class="hover:underline">Ventas</a><?php endif; ?>
            <a href="/cafeteriagatonegro/logout" class="hover:underline">Salir</a>
        </nav>
    </header>

    <?php if ($comanda === null && $view !== 'mesas'): ?>
    <!-- ===================== HOME: ELEGIR TIPO DE VENTA ===================== -->
    <main class="max-w-2xl mx-auto p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <a href="?view=mesas" class="bg-white rounded-2xl shadow-lg p-8 text-center hover:ring-2 hover:ring-teal transition flex flex-col items-center gap-3">
                <span class="text-5xl">🍽️</span>
                <span class="text-xl font-bold text-teal">Venta en Mesa</span>
                <span class="text-sm text-slate-500">Mesas y eventos con comanda abierta</span>
            </a>
            <button id="btn-mostrador" type="button" class="bg-white rounded-2xl shadow-lg p-8 text-center hover:ring-2 hover:ring-teal transition flex flex-col items-center gap-3">
                <span class="text-5xl">🛎️</span>
                <span class="text-xl font-bold text-teal">Venta en Mostrador</span>
                <span class="text-sm text-slate-500">Venta rápida al paso</span>
            </button>
        </div>
        <p id="home-feedback" class="text-sm mt-4 text-center"></p>
    </main>

    <script>
        document.getElementById('btn-mostrador').addEventListener('click', async () => {
            const feedback = document.getElementById('home-feedback');
            feedback.textContent = 'Abriendo...';
            feedback.className = 'text-sm mt-4 text-center text-slate-500';
            try {
                const res = await fetch('/cafeteriagatonegro/open_comanda.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'mostrador' }),
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'error');
                window.location.href = '?comanda=' + encodeURIComponent(data.comanda.id);
            } catch (err) {
                feedback.textContent = 'No se pudo abrir la venta.';
                feedback.className = 'text-sm mt-4 text-center text-red-600';
            }
        });
    </script>

    <?php elseif ($comanda === null): ?>
    <!-- ===================== LISTADO DE MESAS/COMANDAS ABIERTAS ===================== -->
    <main class="max-w-3xl mx-auto p-4 space-y-6">
        <a href="/cafeteriagatonegro" class="text-sm text-teal hover:underline">‹ Inicio</a>

        <section class="bg-white rounded-xl shadow p-4">
            <h2 class="text-lg font-bold mb-3">Nueva mesa / comanda</h2>
            <form id="open-form" class="flex flex-wrap gap-2 items-end">
                <div class="flex-1 min-w-[160px]">
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Nombre</label>
                    <input id="open-name" type="text" required placeholder="Ej: Mesa 5, Barra, Cumple Juan..." class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo</label>
                    <select id="open-type" class="border rounded-lg px-3 py-2 text-sm">
                        <option value="mesa">Mesa</option>
                        <option value="evento">Evento</option>
                    </select>
                </div>
                <button type="submit" class="bg-teal text-white font-bold px-4 py-2 rounded-lg hover:bg-teal-hover transition">Abrir</button>
            </form>
            <p id="open-feedback" class="text-sm mt-2"></p>
        </section>

        <section class="bg-white rounded-xl shadow p-4">
            <h2 class="text-lg font-bold mb-3">Mesas abiertas</h2>
            <?php if (empty($comandas)): ?>
                <p class="text-sm text-slate-500">No hay ninguna mesa abierta.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($comandas as $c): ?>
                        <?php
                            $itemCount = array_sum(array_column($c['items'], 'qty'));
                            $total = array_sum(array_map(fn($it) => $it['price'] * $it['qty'], $c['items']));
                        ?>
                        <div class="flex items-center justify-between gap-3 border rounded-lg px-3 py-2">
                            <a href="?comanda=<?= urlencode($c['id']) ?>" class="flex-1 min-w-0">
                                <div class="font-semibold flex items-center gap-2">
                                    <?= htmlspecialchars($c['name']) ?>
                                    <span class="text-xs font-normal bg-slate-100 text-slate-500 px-2 py-0.5 rounded-full"><?= htmlspecialchars($c['type']) ?></span>
                                </div>
                                <div class="text-xs text-slate-500"><?= $itemCount ?> ítem<?= $itemCount === 1 ? '' : 's' ?> · $<?= number_format($total, 2, ',', '.') ?></div>
                            </a>
                            <button data-id="<?= htmlspecialchars($c['id']) ?>" class="cancel-comanda text-xs text-red-500 hover:text-red-700 px-2 py-1">Cancelar</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        document.getElementById('open-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('open-name').value.trim();
            const type = document.getElementById('open-type').value;
            const feedback = document.getElementById('open-feedback');
            if (!name) return;
            feedback.textContent = 'Abriendo...';
            feedback.className = 'text-sm mt-2 text-slate-500';
            try {
                const res = await fetch('/cafeteriagatonegro/open_comanda.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, type }),
                });
                const data = await res.json();
                if (!data.ok) throw new Error(data.error || 'error');
                window.location.href = '?comanda=' + encodeURIComponent(data.comanda.id);
            } catch (err) {
                feedback.textContent = 'No se pudo abrir la mesa.';
                feedback.className = 'text-sm mt-2 text-red-600';
            }
        });

        document.querySelectorAll('.cancel-comanda').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('¿Cancelar esta mesa? Se pierde lo cargado, no se registra venta.')) return;
                try {
                    const res = await fetch('/cafeteriagatonegro/cancel_comanda.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ comanda_id: btn.dataset.id }),
                    });
                    const data = await res.json();
                    if (data.ok) window.location.reload();
                } catch (err) {}
            });
        });
    </script>

    <?php else: ?>
    <!-- ===================== DETALLE DE UNA MESA/COMANDA ===================== -->
    <?php
        $products = hp_cafeteria_load_json('products.json');
        $categories = [];
        foreach ($products as $p) {
            if (!$p['active']) continue;
            $categories[$p['category']][] = $p;
        }
        $initialCart = [];
        foreach ($comanda['items'] as $line) {
            $initialCart[$line['id']] = $line;
        }
    ?>
    <main class="max-w-6xl mx-auto p-4 grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="flex items-center justify-between gap-3">
                <a href="<?= htmlspecialchars($backUrl) ?>" class="text-sm text-teal hover:underline shrink-0"><?= htmlspecialchars($backLabel) ?></a>
                <input id="product-search" type="text" placeholder="Buscar producto..." class="flex-1 min-w-0 border rounded-lg px-3 py-1.5 text-sm">
                <button id="cancel-btn" class="text-xs text-red-500 hover:text-red-700 shrink-0">Cancelar</button>
            </div>
            <p id="no-results" class="hidden text-sm text-slate-400 text-center py-4">No se encontró ningún producto.</p>
            <div id="product-sections">
            <?php foreach ($categories as $category => $items): ?>
                <section class="product-category">
                    <h2 class="text-lg font-bold mb-2 text-teal"><?= htmlspecialchars($category) ?></h2>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        <?php foreach ($items as $p): ?>
                            <button
                                type="button"
                                class="product-btn bg-white rounded-xl shadow p-3 text-left hover:ring-2 hover:ring-teal transition disabled:opacity-40 disabled:cursor-not-allowed"
                                data-id="<?= htmlspecialchars($p['id']) ?>"
                                data-name="<?= htmlspecialchars($p['name']) ?>"
                                data-price="<?= (float)$p['price'] ?>"
                                data-tracks-stock="<?= ($p['tracks_stock'] ?? true) ? '1' : '0' ?>"
                                <?= ($p['tracks_stock'] ?? true) && $p['stock'] <= 0 ? 'disabled' : '' ?>
                            >
                                <div class="font-semibold text-sm"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="text-teal font-bold">$<?= number_format($p['price'], 2, ',', '.') ?></div>
                                <?php if ($p['tracks_stock'] ?? true): ?>
                                    <div class="text-xs text-slate-500 stock-label" data-stock="<?= (int)$p['stock'] ?>">
                                        stock: <?= (int)$p['stock'] ?><?= $p['stock'] <= $p['min_stock'] ? ' ⚠️' : '' ?>
                                    </div>
                                <?php endif; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
            </div>
        </div>

        <aside class="bg-white rounded-xl shadow p-4 h-fit sticky top-4">
            <h2 class="text-lg font-bold mb-1"><?= htmlspecialchars($comanda['name']) ?></h2>
            <p class="text-xs text-slate-500 mb-3">Comanda actual</p>
            <div id="cart" class="space-y-2 mb-4 text-sm"></div>
            <div class="flex justify-between font-bold text-lg border-t pt-2 mb-4">
                <span>Total</span>
                <span id="cart-total">$0</span>
            </div>

            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Comprador</label>
            <div class="flex gap-2 mb-3 text-sm">
                <label class="flex-1 border rounded-lg px-2 py-2 text-center cursor-pointer has-[:checked]:bg-teal has-[:checked]:text-white">
                    <input type="radio" name="buyer_type" value="huesped" class="hidden"> Huésped
                </label>
                <label class="flex-1 border rounded-lg px-2 py-2 text-center cursor-pointer has-[:checked]:bg-teal has-[:checked]:text-white">
                    <input type="radio" name="buyer_type" value="no_huesped" class="hidden"> No huésped
                </label>
            </div>
            <div id="guest-field" class="hidden mb-3">
                <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Huésped / Habitación</label>
                <input id="guest" type="text" placeholder="Ej: Habitación 5, Juan..." class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>

            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Método de pago</label>
            <div class="flex gap-2 mb-4 text-sm">
                <label class="flex-1 border rounded-lg px-2 py-2 text-center cursor-pointer has-[:checked]:bg-teal has-[:checked]:text-white">
                    <input type="radio" name="payment" value="Efectivo" class="hidden"> Efectivo
                </label>
                <label class="flex-1 border rounded-lg px-2 py-2 text-center cursor-pointer has-[:checked]:bg-teal has-[:checked]:text-white">
                    <input type="radio" name="payment" value="Transferencia" class="hidden"> Transf.
                </label>
                <label class="flex-1 border rounded-lg px-2 py-2 text-center cursor-pointer has-[:checked]:bg-teal has-[:checked]:text-white">
                    <input type="radio" name="payment" value="Tarjeta" class="hidden"> Tarjeta
                </label>
            </div>

            <button id="checkout" class="w-full bg-teal text-white font-bold py-3 rounded-xl hover:bg-teal-hover transition disabled:opacity-40" disabled>
                Cobrar / Registrar venta
            </button>
            <p id="feedback" class="text-sm mt-3"></p>
        </aside>
    </main>

    <script>
        const comandaId = <?= json_encode($comanda['id']) ?>;
        const backUrl = <?= json_encode($backUrl) ?>;
        let cart = <?= json_encode($initialCart, JSON_UNESCAPED_UNICODE) ?>; // id -> { id, name, price, qty }
        renderCart();

        function formatMoney(n) {
            return '$' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function renderCart() {
            const cartEl = document.getElementById('cart');
            const items = Object.values(cart);
            cartEl.innerHTML = '';
            let total = 0;
            items.forEach(item => {
                total += item.price * item.qty;
                const row = document.createElement('div');
                row.className = 'flex items-center justify-between gap-2';
                row.innerHTML = `
                    <span class="flex-1">${item.qty}x ${item.name}</span>
                    <span class="font-semibold">${formatMoney(item.price * item.qty)}</span>
                    <button data-id="${item.id}" class="cart-minus text-slate-400 hover:text-red-600 px-1">−</button>
                    <button data-id="${item.id}" class="cart-plus text-slate-400 hover:text-teal px-1">+</button>
                    <button data-id="${item.id}" class="cart-remove text-slate-400 hover:text-red-600 px-1">×</button>
                `;
                cartEl.appendChild(row);
            });
            document.getElementById('cart-total').textContent = formatMoney(total);
            document.getElementById('checkout').disabled = items.length === 0;
        }

        function setCartFromComanda(comanda) {
            cart = {};
            comanda.items.forEach(item => { cart[item.id] = item; });
            renderCart();
        }

        function showFeedback(msg, ok) {
            const feedback = document.getElementById('feedback');
            feedback.textContent = msg;
            feedback.className = 'text-sm mt-3 ' + (ok ? 'text-green-600' : 'text-red-600');
        }

        async function addItem(productId) {
            const res = await fetch('/cafeteriagatonegro/add_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ comanda_id: comandaId, product_id: productId }),
            });
            const data = await res.json();
            if (!data.ok) {
                if (data.error === 'insufficient_stock') {
                    showFeedback('No hay más stock disponible de ' + data.details[0].name + '.', false);
                }
                return;
            }
            setCartFromComanda(data.comanda);
        }

        async function removeItem(productId, mode) {
            const res = await fetch('/cafeteriagatonegro/remove_item.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ comanda_id: comandaId, product_id: productId, mode }),
            });
            const data = await res.json();
            if (data.ok) setCartFromComanda(data.comanda);
        }

        document.querySelectorAll('.product-btn').forEach(btn => {
            btn.addEventListener('click', () => addItem(btn.dataset.id));
        });

        document.getElementById('product-search').addEventListener('input', (e) => {
            const q = e.target.value.trim().toLowerCase();
            let anyVisible = false;
            document.querySelectorAll('.product-category').forEach(section => {
                let categoryVisible = false;
                section.querySelectorAll('.product-btn').forEach(btn => {
                    const match = btn.dataset.name.toLowerCase().includes(q);
                    btn.classList.toggle('hidden', !match);
                    if (match) categoryVisible = true;
                });
                section.classList.toggle('hidden', !categoryVisible);
                if (categoryVisible) anyVisible = true;
            });
            document.getElementById('no-results').classList.toggle('hidden', anyVisible);
        });

        document.getElementById('cart').addEventListener('click', (e) => {
            const id = e.target.dataset.id;
            if (!id) return;
            if (e.target.classList.contains('cart-plus')) addItem(id);
            if (e.target.classList.contains('cart-minus')) removeItem(id, 'decrement');
            if (e.target.classList.contains('cart-remove')) removeItem(id, 'delete');
        });

        document.querySelectorAll('input[name="buyer_type"]').forEach(radio => {
            radio.addEventListener('change', () => {
                const guestField = document.getElementById('guest-field');
                guestField.classList.toggle('hidden', radio.value !== 'huesped');
            });
        });

        document.getElementById('cancel-btn').addEventListener('click', async () => {
            if (!confirm('¿Cancelar? Se pierde lo cargado, no se registra venta.')) return;
            const res = await fetch('/cafeteriagatonegro/cancel_comanda.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ comanda_id: comandaId }),
            });
            const data = await res.json();
            if (data.ok) window.location.href = backUrl;
        });

        document.getElementById('checkout').addEventListener('click', async () => {
            const payment = document.querySelector('input[name="payment"]:checked');
            const buyerType = document.querySelector('input[name="buyer_type"]:checked');
            const guest = document.getElementById('guest').value.trim();
            if (!payment) { showFeedback('Elegí un método de pago.', false); return; }
            if (!buyerType) { showFeedback('Indicá si es huésped o no.', false); return; }
            if (buyerType.value === 'huesped' && guest === '') { showFeedback('Ingresá el nombre/habitación del huésped.', false); return; }

            const btn = document.getElementById('checkout');
            btn.disabled = true;
            showFeedback('Registrando...', true);
            try {
                const res = await fetch('/cafeteriagatonegro/close_comanda.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        comanda_id: comandaId,
                        buyer_type: buyerType.value,
                        guest,
                        payment_method: payment.value,
                    }),
                });
                const data = await res.json();
                if (!data.ok) {
                    if (data.error === 'insufficient_stock') {
                        const detail = data.details.map(d => `${d.name} (pedido: ${d.requested}, disponible: ${d.available})`).join(', ');
                        showFeedback('No hay stock suficiente: ' + detail, false);
                        btn.disabled = false;
                        return;
                    }
                    throw new Error(data.error || 'error');
                }
                window.location.href = backUrl;
            } catch (err) {
                showFeedback('Error al registrar la venta.', false);
                btn.disabled = false;
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
