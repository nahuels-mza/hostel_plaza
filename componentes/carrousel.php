<?php
/**
 * Carrousel horizontal compartido de Hostel Plaza.
 *
 * Uso (dentro de un <section> de la página):
 *
 *   <?php
 *   require_once __DIR__ . '/componentes/carrousel.php';
 *
 *   hp_carrousel([
 *       'id'       => 'rooms',                 // → #rooms-carousel y #rooms-dots (único por página)
 *       'eyebrow'  => 'Rest & Recharge',       // texto chico arriba del título (opcional)
 *       'title'    => 'Private & Shared Rooms',
 *       'subtitle' => '',                      // párrafo bajo el título (opcional)
 *       'items'    => $rooms,
 *       'card'     => function ($room, $i) { ?>
 *           <div class="<?php echo HP_CARROUSEL_SLIDE; ?> bg-white ...">
 *               ...
 *           </div>
 *       <?php },
 *   ]);
 *   ?>
 *
 * Qué hace el componente:
 *   - Dibuja el encabezado (eyebrow / título / subtítulo) con las flechas ◀ ▶ (solo desktop).
 *   - Dibuja el track con scroll horizontal + snap, loop infinito y puntos de posición
 *     (solo mobile).
 *   - Imprime una sola vez el CSS y el JS necesarios, aunque haya varios carrousels.
 *
 * Qué NO hace: la tarjeta. Cada carrousel dibuja la suya en `card` (habitaciones, áreas,
 * eventos...). El elemento raíz de la tarjeta tiene que ser UN solo elemento y debe llevar
 * las clases de ancho: HP_CARROUSEL_SLIDE (3 por fila en desktop) o una propia con
 * `snap-start shrink-0`.
 *
 * Requisitos en la página: Tailwind y Lucide cargados (las flechas usan <i data-lucide>) y
 * `lucide.createIcons()` invocado en el script principal.
 */

// Ancho estándar de una tarjeta: 85vw en mobile, 2 por fila en tablet, 3 en desktop.
const HP_CARROUSEL_SLIDE = 'snap-start shrink-0 w-[85vw] md:w-[calc(50%-12px)] lg:w-[calc(33.333%-16px)]';

/** CSS + JS compartidos. Se imprimen una sola vez por request (los llama hp_carrousel). */
function hp_carrousel_assets(): void
{
    static $printed = false;
    if ($printed) return;
    $printed = true;
    ?>
<style>
    .hide-scrollbar::-webkit-scrollbar { display: none; }
    .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    /* Tarjeta de evento: 1 por fila en mobile, 2 en tablet, 3 en desktop */
    .event-card { flex: 0 0 100%; }
    @media (min-width: 768px)  { .event-card { flex: 0 0 calc(50% - 12px); } }
    @media (min-width: 1024px) { .event-card { flex: 0 0 calc(33.333% - 16px); } }
</style>
<script>
(function () {
    if (window.hpCarrousel) return;

    var GAP = 24; // gap-6

    // Duplica las tarjetas para el loop infinito y "teletransporta" el scroll al llegar
    // a un borde. Devuelve { scrollBy } para que las flechas no disparen ese salto en
    // medio de su animación.
    function setupEndless(track) {
        Array.from(track.children).forEach(function (child) {
            var clone = child.cloneNode(true);
            clone.setAttribute('aria-hidden', 'true');
            track.appendChild(clone);
        });

        var teleporting = false;
        var btnScrolling = false;

        track.addEventListener('scroll', function () {
            if (teleporting || btnScrolling) return;

            var left = track.scrollLeft;
            var half = track.scrollWidth / 2;
            var target = null;
            if (left >= half)   target = left - half;
            else if (left <= 2) target = left + half;
            if (target === null) return;

            teleporting = true;
            var prev = track.style.scrollBehavior;
            track.style.scrollBehavior = 'auto';
            track.scrollLeft = target;
            requestAnimationFrame(function () {
                track.style.scrollBehavior = prev;
                teleporting = false;
            });
        }, { passive: true });

        return {
            scrollBy: function (delta) {
                btnScrolling = true;
                track.scrollBy({ left: delta, behavior: 'smooth' });
                setTimeout(function () { btnScrolling = false; }, 500);
            }
        };
    }

    // Puntos de posición (solo mobile: el contenedor ya trae md:hidden).
    function setupDots(track, container, count) {
        if (!container || count === 0) return;

        function cardWidth() {
            return (track.children[0] ? track.children[0].offsetWidth : 0) + GAP;
        }

        for (var i = 0; i < count; i++) {
            (function (index) {
                var dot = document.createElement('span');
                dot.style.cssText = 'display:block;height:8px;border-radius:9999px;transition:all .3s;cursor:pointer;';
                dot.style.width = index === 0 ? '24px' : '8px';
                dot.style.backgroundColor = index === 0 ? '#1c5457' : '#cbd5e1';
                dot.addEventListener('click', function () {
                    track.scrollTo({ left: index * cardWidth(), behavior: 'smooth' });
                });
                container.appendChild(dot);
            })(i);
        }

        var dots = Array.from(container.children);

        function update() {
            var w = cardWidth() || 1;
            var idx = ((Math.round(track.scrollLeft / w) % count) + count) % count;
            dots.forEach(function (d, i) {
                d.style.width = i === idx ? '24px' : '8px';
                d.style.backgroundColor = i === idx ? '#1c5457' : '#cbd5e1';
            });
        }

        track.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);
        update();
    }

    var controllers = {};

    function init() {
        document.querySelectorAll('[data-hp-carrousel]').forEach(function (track) {
            var id = track.getAttribute('data-hp-carrousel');
            if (controllers[id]) return;
            var count = track.children.length; // antes de clonar
            controllers[id] = setupEndless(track);
            setupDots(track, document.getElementById(id + '-dots'), count);
        });
    }

    function scroll(id, direction) {
        var track = document.getElementById(id + '-carousel');
        var ctrl = controllers[id];
        if (!track || !ctrl) return;
        var first = track.children[0];
        var step = first ? first.offsetWidth + GAP : track.clientWidth / 2;
        ctrl.scrollBy(direction * step);
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-hp-carrousel-dir]');
        if (!btn) return;
        scroll(btn.getAttribute('data-hp-carrousel-target'), parseInt(btn.getAttribute('data-hp-carrousel-dir'), 10));
    });

    window.hpCarrousel = { init: init, scroll: scroll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
    <?php
}

/**
 * Dibuja un carrousel completo. Opciones: id, eyebrow, title, subtitle, items, card.
 * Ver el comentario de arriba del archivo.
 */
function hp_carrousel(array $o): void
{
    $id       = preg_replace('/[^a-z0-9_-]/i', '', (string)($o['id'] ?? 'carrousel'));
    $eyebrow  = (string)($o['eyebrow']  ?? '');
    $title    = (string)($o['title']    ?? '');
    $subtitle = (string)($o['subtitle'] ?? '');
    $items    = $o['items'] ?? [];
    $card     = $o['card']  ?? null;

    hp_carrousel_assets();
    ?>
    <div class="flex flex-col md:flex-row justify-between items-end mb-12 gap-6">
        <div class="text-left">
            <?php if ($eyebrow !== ''): ?>
            <span class="text-teal font-bold tracking-widest uppercase text-sm mb-4 block"><?php echo htmlspecialchars($eyebrow); ?></span>
            <?php endif; ?>
            <h2 class="text-4xl md:text-5xl mb-4 font-serif"><?php echo htmlspecialchars($title); ?></h2>
            <?php if ($subtitle !== ''): ?>
            <p class="text-slate-500 max-w-2xl"><?php echo htmlspecialchars($subtitle); ?></p>
            <?php endif; ?>
        </div>
        <div class="hidden md:flex gap-3 shrink-0">
            <button type="button" data-hp-carrousel-target="<?php echo $id; ?>" data-hp-carrousel-dir="-1" aria-label="Previous" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-teal hover:text-white hover:border-teal transition-all shadow-sm"><i data-lucide="chevron-left"></i></button>
            <button type="button" data-hp-carrousel-target="<?php echo $id; ?>" data-hp-carrousel-dir="1" aria-label="Next" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-teal hover:text-white hover:border-teal transition-all shadow-sm"><i data-lucide="chevron-right"></i></button>
        </div>
    </div>

    <div id="<?php echo $id; ?>-carousel" data-hp-carrousel="<?php echo $id; ?>" class="flex overflow-x-auto snap-x snap-mandatory gap-6 pb-8 hide-scrollbar scroll-smooth">
        <?php
        if (is_callable($card)) {
            foreach ($items as $i => $item) {
                $card($item, $i);
            }
        }
        ?>
    </div>
    <div id="<?php echo $id; ?>-dots" class="flex md:hidden justify-center items-center gap-2 mt-2"></div>
    <?php
}
