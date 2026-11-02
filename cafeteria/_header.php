<?php
/**
 * Header compartido de Gato Negro (cafetería): título, usuario, nav de
 * escritorio y menú ☰ en mobile (el nav completo no entra en una fila
 * angosta).
 *
 * La página que incluye este archivo debe definir antes:
 *   $cafeteriaUsername, $cafeteriaRole  (ya vienen de helpers.php)
 *   $cafeteriaTitle   string  Texto después de "Gato Negro · " en el <h1>/<title>
 *   $cafeteriaActive  string  Clave de la página actual, para no repetirla en el nav:
 *                             'comanda' | 'stock' | 'insumos' | 'costos' | 'reportes' | 'ventas'
 * Opcional:
 *   $isAdmin  bool  Si no está definida, se calcula acá.
 */

if (!isset($isAdmin)) {
    $isAdmin = hp_cafeteria_is_admin();
}

$cafeteriaNavLinks = [
    'comanda'  => ['href' => '/cafeteriagatonegro',          'label' => 'Comanda'],
    'stock'    => ['href' => '/cafeteriagatonegro/stock',    'label' => 'Stock de Productos'],
    'insumos'  => ['href' => '/cafeteriagatonegro/insumos',  'label' => 'Insumos'],
    'costos'   => ['href' => '/cafeteriagatonegro/costos',   'label' => 'Costos',   'admin' => true],
    'reportes' => ['href' => '/cafeteriagatonegro/reportes', 'label' => 'Reportes', 'admin' => true],
    'ventas'   => ['href' => '/cafeteriagatonegro/ventas',   'label' => 'Ventas',   'admin' => true],
];
?>
    <header class="bg-teal text-white px-4 sm:px-6 py-3 sm:py-4 shadow relative z-30">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-lg sm:text-xl font-bold truncate">Gato Negro · <?= htmlspecialchars($cafeteriaTitle) ?></h1>
            <div class="flex items-center gap-2 sm:gap-4 shrink-0">
                <span class="hidden sm:inline-block text-xs bg-white/15 px-2 py-1 rounded-full"><?= htmlspecialchars($cafeteriaUsername) ?> · <?= htmlspecialchars($cafeteriaRole) ?></span>
                <nav class="hidden md:flex items-center gap-4 text-sm">
                    <?php foreach ($cafeteriaNavLinks as $key => $link): ?>
                        <?php if ($key === $cafeteriaActive) continue; ?>
                        <?php if (!empty($link['admin']) && !$isAdmin) continue; ?>
                        <a href="<?= htmlspecialchars($link['href']) ?>" class="hover:underline"><?= htmlspecialchars($link['label']) ?></a>
                    <?php endforeach; ?>
                    <a href="/cafeteriagatonegro/logout" class="hover:underline">Salir</a>
                </nav>
                <button id="menu-toggle" type="button" class="md:hidden w-9 h-9 rounded-lg bg-white/15 flex items-center justify-center" aria-label="Abrir menú">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Menú mobile (☰): el nav de arriba no entra en una fila en celular, así que se colapsa acá -->
    <div id="menu-scrim" class="hidden md:hidden fixed inset-0 bg-slate-900/50 z-40"></div>
    <nav id="mobile-menu" class="md:hidden fixed top-0 left-0 right-0 bg-teal text-white px-4 pt-4 pb-4 -translate-y-full transition-transform duration-200 z-50 shadow-xl">
        <div class="flex items-center justify-between mb-2">
            <span class="text-xs bg-white/15 px-2 py-1 rounded-full"><?= htmlspecialchars($cafeteriaUsername) ?> · <?= htmlspecialchars($cafeteriaRole) ?></span>
            <button id="menu-close" type="button" class="w-8 h-8 rounded-lg bg-white/15 flex items-center justify-center" aria-label="Cerrar menú">✕</button>
        </div>
        <?php foreach ($cafeteriaNavLinks as $key => $link): ?>
            <?php if ($key === $cafeteriaActive) continue; ?>
            <?php if (!empty($link['admin']) && !$isAdmin) continue; ?>
            <a href="<?= htmlspecialchars($link['href']) ?>" class="block py-3 border-t border-white/15"><?= htmlspecialchars($link['label']) ?></a>
        <?php endforeach; ?>
        <a href="/cafeteriagatonegro/logout" class="block py-3 border-t border-white/15 text-red-100">Salir</a>
    </nav>

    <script>
        (function () {
            const toggle = document.getElementById('menu-toggle');
            const close = document.getElementById('menu-close');
            const menu = document.getElementById('mobile-menu');
            const scrim = document.getElementById('menu-scrim');
            function openMenu() { menu.classList.remove('-translate-y-full'); scrim.classList.remove('hidden'); }
            function closeMenu() { menu.classList.add('-translate-y-full'); scrim.classList.add('hidden'); }
            toggle.addEventListener('click', openMenu);
            close.addEventListener('click', closeMenu);
            scrim.addEventListener('click', closeMenu);
        })();
    </script>
