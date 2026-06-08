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
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Rooms | Hostel Plaza</title>

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

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
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

        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Nav (google-translate, scroll, lang toggle, mobile menu) vive en header.php
        lucide.createIcons();
    </script>
</body>
</html>