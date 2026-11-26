<?php
// --- PRECIOS HOY DESDE BANANADESK (cacheados por día) ---
require_once __DIR__ . '/whatsapp/prices_cache.php';
$todayPrices = hp_today_prices(__DIR__);

// --- SLUG GENERATOR HELPER ---
function createSlug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string); // Replace non-alphanumeric with hyphens
    $string = preg_replace('/-+/', '-', $string); // Remove duplicate hyphens
    return trim($string, '-');
}

$requestedSlug = $_GET['name'] ?? null;
$fallbackId = $_GET['id'] ?? null;

// --- LOAD ROOMS DATABASE ---
$roomsFile = 'rooms.json';
$rooms = [];
if (file_exists($roomsFile)) {
    $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
}


// Find the requested room
$currentRoom = null;
$foundById = false; // Flag to indicate if the room was found by ID
foreach ($rooms as $r) {
    $roomSlug = createSlug($r['name']);

    if ($requestedSlug && $roomSlug === $requestedSlug) {
        $currentRoom = $r;
        break;
    } elseif ($fallbackId && $r['id'] == $fallbackId) {
        $currentRoom = $r; // Set currentRoom here
        $foundById = true; // Mark that it was found by ID
        break;
    }
}

// If a room was found by ID, permanently redirect to the new clean URL
if ($foundById) {
    $roomSlug = createSlug($currentRoom['name']);
    header("HTTP/1.1 301 Moved Permanently");
    header('Location: /room/' . $roomSlug);
    exit;
}

// If no room is found, redirect back to rooms list
if (!$currentRoom) {
    header('Location: /rooms.php');
    exit;
}

// --- SEO ---
$_roomSlugSeo = createSlug($currentRoom['name']);
$seo = [
    'title'       => htmlspecialchars($currentRoom['name']) . ' | Hostel Plaza Mendoza',
    'description' => mb_strimwidth(strip_tags($currentRoom['description'] ?? ''), 0, 155, '…') . ' Book at Hostel Plaza Mendoza.',
    'url'         => 'https://hostelplaza.com.ar/room/' . $_roomSlugSeo,
    'image'       => $currentRoom['image'] ?? 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=',
    'schema'      => [
        '@context'   => 'https://schema.org',
        '@type'      => 'HotelRoom',
        'name'       => $currentRoom['name'] ?? '',
        'description'=> $currentRoom['description'] ?? '',
        'image'      => $currentRoom['image'] ?? '',
        'occupancy'  => [
            '@type'    => 'QuantitativeValue',
            'maxValue' => (int)($currentRoom['capacity'] ?? 1),
        ],
        'containedInPlace' => [
            '@type' => 'Hostel',
            'name'  => 'Hostel Plaza Mendoza',
            'url'   => 'https://hostelplaza.com.ar',
        ],
    ],
];

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '_seo.php'; ?>

    <base href="/">

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
        .glass {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* CSS de nav/lang/google translate vive en componentes/header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased overflow-x-hidden">

    <?php $hasHero = true; include __DIR__ . '/componentes/header.php'; ?>

    <div class="w-full h-[50vh] md:h-[65vh] relative bg-slate-900">
        <img src="<?php echo htmlspecialchars($currentRoom['image']); ?>" alt="<?php echo htmlspecialchars($currentRoom['name']); ?>" class="w-full h-full object-cover opacity-80">
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>

        <div class="absolute bottom-0 left-0 w-full p-8 md:p-12 pb-16 max-w-7xl mx-auto">
            <a href="rooms.php" class="text-teal-300 font-bold uppercase tracking-widest text-xs mb-4 inline-flex items-center gap-2 hover:text-white transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Rooms
            </a>
            <h1 class="text-4xl md:text-6xl font-serif font-bold text-white mb-4"><?php echo htmlspecialchars($currentRoom['name']); ?></h1>
            <div class="flex items-center gap-4 text-white/80 font-medium">
                <span class="bg-white/10 px-3 py-1 rounded-md border border-white/10 text-sm flex items-center gap-2"><i data-lucide="bed" class="w-4 h-4"></i> <?php echo htmlspecialchars($currentRoom['type']); ?></span>
                <span class="bg-white/10 px-3 py-1 rounded-md border border-white/10 text-sm flex items-center gap-2"><i data-lucide="users" class="w-4 h-4"></i> Sleeps <?php echo htmlspecialchars($currentRoom['capacity']); ?></span>
            </div>
        </div>
    </div>

    <main class="flex-1 w-full bg-[#FDFBF7] py-16">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-20">

            <div class="lg:col-span-2 space-y-14">

                <section>
                    <h2 class="text-[26px] font-serif font-bold text-[#0f172a] mb-6">About this space</h2>
                    <p class="text-lg text-[#475569] leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($currentRoom['description'])); ?>
                    </p>
                </section>

                <hr class="border-slate-200">

                <section>
                    <h2 class="text-[26px] font-serif font-bold text-[#0f172a] mb-8">What this room offers</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-y-8 gap-x-6">
                        <?php
                        // Map amenities to specific Lucide icons based on standard text
                        function getAmenityIcon($text) {
                            $text = strtolower($text);
                            if(strpos($text, 'wifi') !== false || strpos($text, 'wi-fi') !== false) return 'wifi';
                            if(strpos($text, 'air') !== false || strpos($text, 'accondinated') !== false) return 'wind';
                            if(strpos($text, 'bath') !== false) return 'shower-head';
                            if(strpos($text, 'locker') !== false || strpos($text, 'safe') !== false) return 'lock';
                            if(strpos($text, 'tv') !== false) return 'tv';
                            if(strpos($text, 'bunk bed') !== false) return 'server';
                            if(strpos($text, 'double bed') !== false || strpos($text, 'dorm') !== false) return 'bed-double';
                            if(strpos($text, 'balcony') !== false) return 'sun';
                            if(strpos($text, 'window') !== false) return 'layout-template';
                            if(strpos($text, 'fan') !== false) return 'fan';
                            if(strpos($text, 'smook') !== false || strpos($text, 'smok') !== false) return 'ban';
                            if(strpos($text, 'plug') !== false) return 'plug';
                            if(strpos($text, 'breakfast') !== false) return 'coffee';
                            if(strpos($text, 'stove') !== false) return 'flame';
                            if(strpos($text, 'clothes') !== false || strpos($text, 'fresh') !== false) return 'sparkles';
                            return 'check-circle-2';
                        }

                        foreach ($currentRoom['amenities'] as $amenity):
                            $iconName = getAmenityIcon($amenity);
                        ?>
                        <div class="flex items-center gap-3">
                            <i data-lucide="<?php echo $iconName; ?>" class="w-[22px] h-[22px] text-[#1c5457] opacity-80 stroke-[1.5]"></i>
                            <span class="text-[17px] text-[#334155]"><?php echo htmlspecialchars($amenity); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- New Photo Carousel Section -->
                <section>
                    <h2 class="text-[26px] font-serif font-bold text-[#0f172a] mb-8">Photo Gallery</h2>
                    <div class="flex overflow-x-auto snap-x snap-mandatory gap-4 pb-4 hide-scrollbar" role="group" aria-label="Room Photos Carousel">
                        <?php
                        $imageList = $currentRoom['image_list'] ?? [];
                        $photosToShow = $imageList;

                        if (empty($photosToShow)) {
                            echo '<p class="text-slate-500">No additional photos available for this room.</p>';
                        } else {
                            foreach ($photosToShow as $index => $imageUrl) {
                                echo '<div class="flex-shrink-0 w-full md:w-1/2 lg:w-1/3 snap-center">';
                                echo '<img src="' . htmlspecialchars($imageUrl) . '" alt="' . htmlspecialchars($currentRoom['name']) . ' Photo ' . ($index + 1) . '" class="w-full h-64 object-cover rounded-xl shadow-md">';
                                echo '</div>';
                            }
                        }
                        ?>
                    </div>
                </section>
            </div>

            <div class="lg:col-span-1">
                <div class="lg:sticky lg:top-32 bg-white rounded-3xl p-8 shadow-xl border border-slate-100">
                    <?php $ars = hp_format_price($todayPrices, $currentRoom['id']); ?>
                    <div class="mb-8">
                        <?php if ($ars): ?>
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-4xl font-bold text-[#1c5457]"><?php echo $ars; ?></span>
                                <span class="text-[#64748b] text-base">/noche</span>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">Precio de hoy · puede variar según fechas</p>
                        <?php else: ?>
                            <p class="text-slate-500 text-sm">Seleccioná fechas para ver el precio</p>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-4 mb-8 text-[13px] text-[#475569] leading-relaxed">
                        <div class="flex items-start gap-3">
                            <i data-lucide="info" class="w-4 h-4 text-slate-400 mt-0.5 shrink-0"></i>
                            <p>Foreign guests presenting a valid passport and immigration stamp are exempt from the 21% VAT tax.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <i data-lucide="coffee" class="w-4 h-4 text-slate-400 mt-0.5 shrink-0"></i>
                            <p>Complimentary breakfast is included with all stays.</p>
                        </div>
                    </div>

                    <a href="book.php?room_id=<?php echo urlencode($currentRoom['id']); ?>" class="block text-center w-full py-3.5 bg-[#1c5457] text-white rounded-xl font-bold text-[17px] hover:bg-[#144042] transition-all shadow-md">
                        Check Availability
                    </a>

                    <a href="faq.php" target="_blank" class="mt-4 flex items-center justify-center gap-2 w-full py-3 bg-slate-50 text-slate-600 rounded-xl font-semibold text-sm border border-slate-200 hover:bg-slate-100 hover:text-slate-900 transition-all">
                        <i data-lucide="file-text" class="w-4 h-4"></i> Read our FAQ
                    </a>
                </div>
            </div>

        </div>
    </main>

    <?php include __DIR__ . '/componentes/footer.php'; ?>

    <script>
        // Nav (google-translate, scroll, lang toggle, mobile menu) vive en componentes/header.php
        lucide.createIcons();
    </script>
</body>
</html>