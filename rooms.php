<?php
// --- EXCHANGE RATE SETTING ---
// --- LOAD ROOMS DATABASE ---
$roomsFile = 'rooms.json';
$rooms = [];
if (file_exists($roomsFile)) {
    $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
}

// --- PRECIOS HOY DESDE BANANADESK (cacheados por día) ---
require_once __DIR__ . '/whatsapp/prices_cache.php';
$todayPrices = hp_today_prices(__DIR__);

// --- SEO ---
$_roomItems = [];
foreach ($rooms as $_i => $_r) {
    $_roomItems[] = [
        '@type'    => 'ListItem',
        'position' => $_i + 1,
        'url'      => 'https://hostelplaza.com.ar/rooms',
        'name'     => $_r['name'] ?? '',
    ];
}
$seo = [
    'title'       => 'Rooms & Rates | Hostel Plaza Mendoza',
    'description' => 'Private rooms and shared dorms in the center of Mendoza, Argentina. Real-time availability and prices. Choose from single, double, female-only dorms and mixed dorms.',
    'url'         => 'https://hostelplaza.com.ar/rooms',
    'image'       => 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495898.jpg?k=35f2d062726868da5ba53f68a2d7f964da6e6839de4e4476b483ec6b909ee07c&o=',
    'schema'      => [
        '@context'        => 'https://schema.org',
        '@type'           => 'ItemList',
        'name'            => 'Hostel Plaza Mendoza — Rooms & Dorms',
        'description'     => 'All rooms and dorms available at Hostel Plaza Mendoza',
        'numberOfItems'   => count($rooms),
        'itemListElement' => $_roomItems,
    ],
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '_seo.php'; ?>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        malbec: '#722F37',
                        'malbec-light': '#8C3A44',
                        cream: '#FDFBF7',
                        teal: {
                            DEFAULT: '#1c5457',
                            hover: '#144042'
                        }
                    },
                    fontFamily: {
                        serif: ['"Playfair Display"', 'Georgia', 'serif'],
                        sans: ['"Inter"', 'system-ui', '-apple-system', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .room-card-image-container { overflow: hidden; }
        .room-card-image-container img { transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1); }
        .group:hover .room-card-image-container img { transform: scale(1.05); }
        /* Mobile carousel */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        @media (max-width: 767px) {
            #rooms-list {
                display: flex;
                overflow-x: auto;
                scroll-snap-type: x mandatory;
                gap: 20px;
                padding-bottom: 24px;
                scroll-behavior: smooth;
            }
            #rooms-list > div {
                flex: 0 0 85vw;
                scroll-snap-align: start;
            }
        }
        /* CSS de nav/lang/google translate vive en header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <?php $hasHero = false; include __DIR__ . '/header.php'; ?>

    <header class="pt-32 pb-16 bg-slate-900 text-center border-b border-white/10">
        <div class="max-w-3xl mx-auto px-6">
            <span class="text-teal-300 font-bold tracking-widest uppercase text-xs mb-3 block">Rest & Relax</span>
            <h1 class="text-4xl md:text-6xl font-serif font-bold mb-4 text-white">Our Rooms & Dorms</h1>
            <p class="text-lg text-white/60">Find the perfect space for your stay in Mendoza. All rates are tax-exempt for foreign guests presenting a valid passport.</p>
        </div>
    </header>

    <main class="flex-1 w-full py-20 bg-[#FDFBF7]">
        <div class="max-w-7xl mx-auto px-6">

            <div id="rooms-list" class="hide-scrollbar md:grid md:grid-cols-2 lg:grid-cols-3 md:gap-10">
                <?php foreach ($rooms as $room): ?>
                <div class="group bg-white rounded-[2rem] overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 border border-slate-100 flex flex-col relative">

                    <div class="h-72 relative room-card-image-container">
                        <img src="<?php echo htmlspecialchars($room['image']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>" class="w-full h-full object-cover">

                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>

                        <div class="absolute top-5 right-5 bg-white/95 backdrop-blur-sm px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest text-slate-900 shadow-sm border border-white/20">
                            <?php echo htmlspecialchars($room['type']); ?>
                        </div>

                        <?php $ars = hp_format_price($todayPrices, $room['id']); ?>
                        <?php if ($ars): ?>
                        <div class="absolute bottom-5 left-5">
                            <div class="bg-white/95 backdrop-blur-sm px-4 py-2 rounded-xl text-teal-800 font-bold shadow-lg border border-white/50 flex items-baseline gap-1">
                                <span class="text-base"><?php echo $ars; ?></span>
                                <span class="text-xs font-normal text-slate-500">/noche</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="p-8 flex-1 flex flex-col bg-white z-10">
                        <div class="flex justify-between items-start mb-3">
                            <h3 class="text-2xl font-serif font-bold text-slate-900 leading-tight pr-4"><?php echo htmlspecialchars($room['name']); ?></h3>
                            <div class="flex items-center gap-1.5 text-slate-500 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-100 flex-shrink-0">
                                <i data-lucide="users" class="w-4 h-4 text-teal"></i>
                                <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($room['capacity']); ?></span>
                            </div>
                        </div>

                        <p class="text-slate-500 text-sm leading-relaxed mb-8 line-clamp-3">
                            <?php echo htmlspecialchars($room['description']); ?>
                        </p>

                        <div class="mt-auto">
                            <a href="room.php?id=<?php echo urlencode($room['id']); ?>" class="block text-center w-full py-4 bg-white text-teal rounded-2xl font-bold transition-all shadow-sm border-2 border-teal hover:bg-teal hover:text-white group-hover:shadow-md">
                                Check Availability
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Mobile carousel dots -->
            <div id="rooms-dots" class="flex md:hidden justify-center items-center gap-2 mt-3"></div>

        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        lucide.createIcons();

        // Mobile-only carousel dots
        (function () {
            const list = document.getElementById('rooms-list');
            const dotsEl = document.getElementById('rooms-dots');
            const total = <?php echo count($rooms); ?>;
            if (!list || !dotsEl || total === 0) return;

            for (let i = 0; i < total; i++) {
                const dot = document.createElement('span');
                dot.style.cssText = 'display:block;height:8px;border-radius:9999px;transition:all .3s;cursor:pointer;';
                dot.style.width = i === 0 ? '24px' : '8px';
                dot.style.backgroundColor = i === 0 ? '#1c5457' : '#cbd5e1';
                dot.addEventListener('click', () => {
                    const card = list.children[i];
                    if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
                });
                dotsEl.appendChild(dot);
            }

            const dots = Array.from(dotsEl.children);
            function update() {
                const w = (list.children[0]?.offsetWidth ?? 1) + 20;
                const idx = Math.min(total - 1, Math.round(list.scrollLeft / w));
                dots.forEach((d, i) => {
                    d.style.width = i === idx ? '24px' : '8px';
                    d.style.backgroundColor = i === idx ? '#1c5457' : '#cbd5e1';
                });
            }
            list.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
        })();
    </script>
</body>
</html>