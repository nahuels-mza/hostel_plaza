<?php
// --- LOAD ROOMS DATABASE ---
$roomsFile = 'rooms.json';
$rooms = [];
if (file_exists($roomsFile)) {
    $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
}

// --- PRECIOS HOY DESDE BANANADESK (cacheados por día) ---
require_once __DIR__ . '/whatsapp/prices_cache.php';
$todayPrices = hp_today_prices(__DIR__);

// --- LOAD PLAZA EVENTS FOR THE HOMEPAGE ---
$plazaEventsFile = 'plaza_events.json';
$plazaEvents = [];
if (file_exists($plazaEventsFile)) {
    $plazaEvents = json_decode(file_get_contents($plazaEventsFile), true) ?: [];
}

// Fallback just in case JSON is empty or missing
if (empty($plazaEvents)) {
    $plazaEvents = [
        // ==========================================
        // 🎯 EVENT TEMPLATES (EDIT THESE BELOW)
        // ==========================================

        // Template 1
        [
            "id" => "pevt_template_1",
            "day" => "21",
            "month" => "SEP",
            "title" => "Sunset, DJ & Vino",
            "subtitle" => "PRIMAVERA EN PLAZA",
            "image" => "https://images.unsplash.com/photo-1504609774528-6d4b83740ed1?q=80&w=1000&auto=format&fit=crop"
        ],

        // Template 2
        [
            "id" => "pevt_template_2",
            "day" => "31",
            "month" => "OCT",
            "title" => "Halloween Party",
            "subtitle" => "COSTUME CONTEST",
            "image" => "https://images.unsplash.com/photo-1508361001413-7a9dca21d08a?q=80&w=1000&auto=format&fit=crop"
        ],

        // Template 3
        [
            "id" => "pevt_template_3",
            "day" => "12",
            "month" => "NOV",
            "title" => "Hostel Asado Night",
            "subtitle" => "TRADITIONAL ARGENTINE BBQ",
            "image" => "https://images.unsplash.com/photo-1555939594-58d7cb561ad1?q=80&w=1000&auto=format&fit=crop"
        ],

        // Template 4
        [
            "id" => "pevt_template_4",
            "day" => "15",
            "month" => "JAN",
            "title" => "Wine Tasting Masterclass",
            "subtitle" => "LOCAL VINEYARDS",
            "image" => "https://images.unsplash.com/photo-1506377247377-2a5b3b417ebb?q=80&w=1000&auto=format&fit=crop"
        ],

        // Template 5
        [
            "id" => "pevt_template_5",
            "day" => "05",
            "month" => "FEB",
            "title" => "Mountain Trekking",
            "subtitle" => "ANDES ADVENTURE",
            "image" => "https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?q=80&w=1000&auto=format&fit=crop"
        ],

        // Template 6
        [
            "id" => "pevt_template_6",
            "day" => "18",
            "month" => "MAR",
            "title" => "Empanada Cooking Class",
            "subtitle" => "LEARN TO COOK LIKE A LOCAL",
            "image" => "https://images.unsplash.com/photo-1628191137573-dee64e727614?q=80&w=1000&auto=format&fit=crop"
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Plaza | Mendoza, Argentina</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

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
                        },
                        booking: '#003580'
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
        /* STRICT OVERFLOW CONTROL */
        html, body {
            overflow-x: hidden;
            width: 100%;
        }

        .glass {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .hero-gradient {
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.7), rgba(15, 23, 42, 0.3));
        }
        .fade-in {
            animation: fadeIn 1s ease-in forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes gentleFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-15px); }
        }
        .animate-float {
            animation: gentleFloat 5s ease-in-out infinite;
        }

        /* FIXED DATE PICKER CSS */
        input[type="date"] {
            position: relative; /* Keeps the absolute child contained */
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .room-card-image-container {
            overflow: hidden;
        }
        .room-card-image-container img {
            transition: transform 0.5s ease;
        }
        .group:hover .room-card-image-container img {
            transform: scale(1.05);
        }

        /* CAROUSEL CSS */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        .event-card { flex: 0 0 100%; }
        @media (min-width: 768px) { .event-card { flex: 0 0 calc(50% - 12px); } }
        @media (min-width: 1024px) { .event-card { flex: 0 0 calc(33.333% - 16px); } }

        /* CSS de nav/lang/google translate vive en header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased overflow-x-hidden">

    <?php $hasHero = true; include __DIR__ . '/header.php'; ?>

    <section id="home" class="relative h-screen min-h-[700px] flex items-center justify-center">
        <div class="absolute inset-0 z-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat md:bg-fixed" style="background-image: url('https://cf.bstatic.com/xdata/images/hotel/max1024x768/1337158401.jpg?k=b28097b21b7c0273&o=');"></div>
            <div class="absolute inset-0 hero-gradient"></div>
        </div>

        <div class="relative z-10 text-center px-6 max-w-4xl fade-in">
            <h1 class="text-4xl md:text-6xl lg:text-7xl text-white mb-6 leading-tight font-serif">
                Your home in the heart of <span class="italic text-teal-300">Mendoza.</span>
            </h1>
            <p class="text-lg md:text-2xl text-white/90 mb-12 font-light tracking-wide">
                Where mountain adventure meets urban charm.
            </p>
        </div>

        <div class="absolute bottom-0 inset-x-0 translate-y-1/2 max-w-5xl mx-auto px-4 md:px-6 z-20">
            <?php
                $hero_today    = date('Y-m-d');
                $hero_tomorrow = date('Y-m-d', strtotime('+1 day'));
            ?>
            <form id="hero_book_form" action="book.php" method="GET" class="bg-[#E5E7EB] rounded-2xl p-5 md:p-8 shadow-2xl grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 items-end border border-slate-200 overflow-hidden">
                <div class="space-y-2 min-w-0">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-teal"></i> Check In</label>
                    <input type="text" name="check_in" id="hero_check_in"
                           placeholder="Check-in date"
                           required readonly
                           class="w-full min-w-0 bg-white border border-slate-200 rounded-lg p-3 text-slate-700 outline-none cursor-pointer" />
                </div>
                <div class="space-y-2 min-w-0">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-teal"></i> Check Out</label>
                    <input type="text" name="check_out" id="hero_check_out"
                           placeholder="Check-out date"
                           required readonly
                           class="w-full min-w-0 bg-white border border-slate-200 rounded-lg p-3 text-slate-700 outline-none cursor-pointer" />
                </div>
                <button type="submit" class="bg-teal-400 text-slate-900 h-[50px] rounded-lg font-bold text-lg hover:bg-teal-300 transition-all shadow-md">Book Now</button>
            </form>
        </div>
    </section>

    <div class="h-40 md:h-20"></div>

    <section id="about" class="py-16 px-6 max-w-7xl mx-auto w-full">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div class="relative animate-float">
                <div class="rounded-3xl overflow-hidden shadow-2xl aspect-square">
                    <img src="https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=" alt="Hostel Plaza Courtyard" class="w-full h-full object-cover" />
                </div>
            </div>
            <div class="space-y-8">
                <span class="text-teal font-bold tracking-widest uppercase text-sm">Who We Are</span>
                <h2 class="text-4xl md:text-5xl leading-tight font-serif">A space designed by travelers, for travelers.</h2>
                <p class="text-slate-600 text-lg leading-relaxed">Located in a beautifully preserved heritage house, we offer a unique blend of historical charm and modern comfort in the true spirit of Mendoza.</p>
                <a href="about" class="inline-flex items-center gap-2 text-teal font-bold hover:gap-4 transition-all group">Discover our story <i data-lucide="chevron-right"></i></a>
            </div>
        </div>
    </section>

    <section id="rooms" class="py-16 px-6 max-w-7xl mx-auto w-full bg-slate-50">
        <div class="flex flex-col md:flex-row justify-between items-end mb-12 gap-6">
            <div class="text-left">
                <span class="text-teal font-bold tracking-widest uppercase text-sm mb-4 block">Rest & Recharge</span>
                <h2 class="text-4xl md:text-5xl mb-4 font-serif">Private & Shared Rooms</h2>
            </div>
            <div class="hidden md:flex gap-3 shrink-0">
                <button onclick="scrollRooms(-1)" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-teal hover:text-white hover:border-teal transition-all shadow-sm"><i data-lucide="chevron-left"></i></button>
                <button onclick="scrollRooms(1)" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-teal hover:text-white hover:border-teal transition-all shadow-sm"><i data-lucide="chevron-right"></i></button>
            </div>
        </div>

        <div id="rooms-carousel" class="flex overflow-x-auto snap-x snap-mandatory gap-6 pb-8 hide-scrollbar scroll-smooth">
            <?php foreach ($rooms as $room): ?>
            <div class="snap-start shrink-0 w-[85vw] md:w-[calc(50%-12px)] lg:w-[calc(33.333%-16px)] group bg-white rounded-[2rem] overflow-hidden shadow-sm hover:shadow-2xl transition-all duration-500 border border-slate-100 flex flex-col relative">
                <div class="h-72 relative room-card-image-container">
                    <img src="<?php echo htmlspecialchars($room['image']); ?>" alt="<?php echo htmlspecialchars($room['name']); ?>" class="w-full h-full object-cover">

                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 via-transparent to-transparent opacity-60 group-hover:opacity-80 transition-opacity"></div>

                    <div class="absolute top-5 right-5 bg-white/95 backdrop-blur-sm px-4 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-widest text-slate-900 shadow-sm border border-white/20">
                        <?php echo htmlspecialchars($room['type']); ?>
                    </div>

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

                    <div class="mt-auto flex gap-2">
                        <a href="room.php?id=<?php echo urlencode($room['id']); ?>" class="flex-1 block text-center py-4 bg-white text-teal rounded-2xl font-bold transition-all shadow-sm border-2 border-teal hover:bg-teal-light group-hover:shadow-md">
                            View
                        </a>
                        <a href="book.php?room_id=<?php echo urlencode($room['id']); ?>" class="flex-1 block text-center py-4 bg-teal text-white rounded-2xl font-bold transition-all shadow-sm border-2 border-teal hover:bg-teal-hover group-hover:shadow-md">
                            Book
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="rooms-dots" class="flex md:hidden justify-center items-center gap-2 mt-2"></div>
    </section>

    <section id="plaza-events" class="py-16 px-6 max-w-7xl mx-auto w-full relative">
        <div class="flex flex-col md:flex-row justify-between items-end mb-12 gap-6">
            <div class="text-left">
                <span class="text-teal font-bold tracking-widest uppercase text-sm mb-4 block">What's Happening</span>
                <h2 class="text-4xl md:text-5xl mb-4 font-serif">Hostel Plaza Events</h2>
                <p class="text-slate-500 max-w-2xl">Join us for unforgettable nights and local culture right here at the hostel.</p>
            </div>
            <div class="hidden md:flex gap-3 shrink-0">
                <button onclick="scrollEvents(-1)" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-teal hover:text-white hover:border-teal transition-all shadow-sm"><i data-lucide="chevron-left"></i></button>
                <button onclick="scrollEvents(1)" class="w-12 h-12 rounded-full border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-teal hover:text-white hover:border-teal transition-all shadow-sm"><i data-lucide="chevron-right"></i></button>
            </div>
        </div>

        <div id="events-carousel" class="flex overflow-x-auto snap-x snap-mandatory gap-6 pb-8 hide-scrollbar scroll-smooth">
            <?php foreach ($plazaEvents as $pevent):
                // Fix: Translate Admin Panel keys to frontend keys
                $title = $pevent['name'] ?? $pevent['title'] ?? 'Special Event';
                $subtitle = $pevent['description'] ?? $pevent['subtitle'] ?? '';

                // Fix: Split the Admin "Date" string (e.g. "20 AGO") into the two badge lines
                $day = $pevent['day'] ?? '';
                $month = $pevent['month'] ?? '';
                if (!empty($pevent['date'])) {
                    $dateParts = explode(' ', trim($pevent['date']));
                    $day = $dateParts[0] ?? '';
                    $month = $dateParts[1] ?? '';
                }
            ?>
                <div class="event-card snap-start shrink-0 bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden group relative flex flex-col h-[400px]">

                    <div class="relative h-[300px] w-full overflow-hidden">
                        <img src="<?php echo htmlspecialchars($pevent['image'] ?? ''); ?>" alt="Event" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" onerror="this.src='https://images.unsplash.com/photo-1504609774528-6d4b83740ed1?q=80&w=800&auto=format&fit=crop'">

                        <div class="absolute top-4 left-4 bg-white/95 backdrop-blur-sm text-slate-900 flex flex-col items-center justify-center w-14 h-16 rounded-xl shadow-lg border border-slate-100/50 z-10">
                            <span class="font-serif font-bold text-2xl leading-none mt-1 text-teal"><?php echo htmlspecialchars($day); ?></span>
                            <span class="text-[10px] font-bold uppercase tracking-widest mt-0.5 text-slate-500"><?php echo htmlspecialchars($month); ?></span>
                        </div>
                    </div>

                    <div class="flex-1 bg-white flex flex-col items-center justify-center p-6 text-center border-t border-slate-100 relative z-20">
                        <h3 class="font-serif font-bold text-xl text-slate-900 leading-tight mb-1 truncate w-full"><?php echo htmlspecialchars($title); ?></h3>
                        <p class="text-[11px] text-teal uppercase tracking-widest font-bold truncate w-full"><?php echo htmlspecialchars($subtitle); ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div id="events-dots" class="flex md:hidden justify-center items-center gap-2 mt-2"></div>
    </section>

    <section class="relative bg-booking py-24 overflow-hidden">
        <div class="absolute inset-0 z-0 opacity-20">
            <img src="https://cf.bstatic.com/xdata/images/hotel/max1024x768/1337158401.jpg?k=b28097b21b7c0273&o=" alt="Hostel Plaza" class="w-full h-full object-cover">
        </div>
        <div class="max-w-7xl mx-auto px-6 relative z-10">
            <div class="flex flex-col md:flex-row justify-between items-end mb-16 gap-6">
                <div>
                    <span class="text-blue-200 font-bold tracking-widest uppercase text-sm mb-4 flex items-center gap-2">
                        <div class="w-6 h-6 bg-white text-booking rounded flex items-center justify-center font-black text-xs">B.</div> Verified Guest Reviews
                    </span>
                    <h2 class="text-4xl md:text-5xl leading-tight font-serif text-white">See what our guests say.</h2>
                </div>
                <div class="flex items-center gap-4 bg-white px-6 py-4 rounded-2xl shadow-xl">
                    <div class="bg-booking text-white font-bold text-2xl px-3 py-2 rounded-t-xl rounded-br-xl rounded-bl-sm">9.0</div>
                    <div><p class="font-bold text-slate-900 leading-none">Superb</p><p class="text-slate-500 text-xs mt-1 font-medium">Based on 276 reviews</p></div>
                </div>
            </div>

            <div id="reviews-grid" class="grid grid-cols-1 md:grid-cols-3 gap-8 [&>div:nth-child(n+2)]:hidden md:[&>div:nth-child(n+2)]:flex">

                <div class="bg-white rounded-3xl p-8 shadow-2xl flex flex-col h-full relative group hover:-translate-y-2 transition-transform">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-bold text-lg">M</div>
                        <div><p class="font-bold text-slate-900">Marco</p><p class="text-xs text-slate-500 uppercase tracking-wider">Italy</p></div>
                    </div>
                    <p class="text-slate-600 leading-relaxed italic flex-1">"The atmosphere was great, very easy to meet people. The staff is super helpful and organized amazing asado nights. Highly recommend for solo travelers!"</p>
                    <div class="mt-6 pt-6 border-t border-slate-50 flex gap-1">
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 text-slate-300"></i>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-8 shadow-2xl flex flex-col h-full relative group hover:-translate-y-2 transition-transform">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-bold text-lg">S</div>
                        <div><p class="font-bold text-slate-900">Sarah</p><p class="text-xs text-slate-500 uppercase tracking-wider">USA</p></div>
                    </div>
                    <p class="text-slate-600 leading-relaxed italic flex-1">"Everything was clean and the location is perfect, right near the main plaza but on a quiet street. The beds are comfortable and the kitchen is well equipped."</p>
                    <div class="mt-6 pt-6 border-t border-slate-50 flex gap-1">
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 text-slate-300"></i>
                    </div>
                </div>

                <div class="bg-white rounded-3xl p-8 shadow-2xl flex flex-col h-full relative group hover:-translate-y-2 transition-transform">
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 font-bold text-lg">L</div>
                        <div><p class="font-bold text-slate-900">Lukas</p><p class="text-xs text-slate-500 uppercase tracking-wider">Germany</p></div>
                    </div>
                    <p class="text-slate-600 leading-relaxed italic flex-1">"Best hostel experience in Mendoza. The owners are local experts and helped us book everything from paragliding to wine tours at the best prices."</p>
                    <div class="mt-6 pt-6 border-t border-slate-50 flex gap-1">
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                        <i data-lucide="star" class="w-4 h-4 fill-booking text-booking"></i>
                    </div>
                </div>
            </div>

            <div class="mt-16 text-center">
                <a href="https://www.booking.com/hotel/ar/hostel-plaza-capital.html#tab-reviews" target="_blank" class="inline-flex items-center gap-3 bg-white text-booking px-10 py-4 rounded-xl font-bold text-lg hover:bg-slate-100 transition-all shadow-xl hover:-translate-y-1">
                    Read all 276 reviews on Booking.com <i data-lucide="external-link" class="w-5 h-5"></i>
                </a>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        lucide.createIcons();

        // --- HERO BOOK FORM: flatpickr date pickers ---
        (function () {
            const addDays = (s, n) => {
                const d = new Date(s + 'T00:00:00');
                d.setDate(d.getDate() + n);
                return d.toISOString().split('T')[0];
            };

            let fpIn, fpOut;

            fpIn = flatpickr('#hero_check_in', {
                dateFormat: 'Y-m-d',
                minDate: 'today',
                onChange: function(selectedDates, dateStr) {
                    if (!dateStr) return;
                    const minOut = addDays(dateStr, 1);
                    fpOut.set('minDate', minOut);
                    if (!fpOut.selectedDates.length || fpOut.selectedDates[0] <= selectedDates[0]) {
                        fpOut.setDate(minOut, false);
                    }
                }
            });

            fpOut = flatpickr('#hero_check_out', {
                dateFormat: 'Y-m-d',
                minDate: addDays(new Date().toISOString().split('T')[0], 1),
            });

            document.getElementById('hero_book_form').addEventListener('submit', function(e) {
                const ci = document.getElementById('hero_check_in');
                const co = document.getElementById('hero_check_out');
                if (!ci.value || !co.value) { e.preventDefault(); return; }
                if (co.value <= ci.value) {
                    e.preventDefault();
                    alert('Check-out must be after check-in.');
                }
            });
        })();

        // --- CAROUSEL AUTO-SCROLL ENGINE ---
        const eventsCarousel = document.getElementById('events-carousel');
        const roomsCarousel = document.getElementById('rooms-carousel');

        function setupEndless(carousel) {
            if (!carousel) return;

            // Duplicamos el contenido para crear el efecto loop.
            const children = Array.from(carousel.children);
            children.forEach(child => {
                const clone = child.cloneNode(true);
                clone.setAttribute('aria-hidden', 'true');
                carousel.appendChild(clone);
            });

            // Flag para evitar reentradas mientras estamos teletransportando.
            let teleporting = false;

            carousel.addEventListener('scroll', () => {
                if (teleporting) return;

                const scrollLeft = carousel.scrollLeft;
                const halfWidth  = carousel.scrollWidth / 2;
                // Margen mínimo para evitar wraps por sub-píxeles de los bordes.
                const edge = 2;

                let target = null;
                if (scrollLeft >= halfWidth) {
                    target = scrollLeft - halfWidth;
                } else if (scrollLeft <= edge) {
                    target = scrollLeft + halfWidth;
                }
                if (target === null) return;

                // CLAVE: el contenedor tiene CSS `scroll-behavior: smooth`
                // (clase scroll-smooth). Si solo asignamos scrollLeft, el
                // navegador ANIMA el salto y se ve como un "rewind" desde
                // el final hasta el principio. Lo forzamos a instantáneo.
                teleporting = true;
                const prevBehavior = carousel.style.scrollBehavior;
                carousel.style.scrollBehavior = 'auto';
                carousel.scrollLeft = target;
                // Restauramos en el siguiente frame para que el scroll
                // disparado por las flechas siga siendo suave.
                requestAnimationFrame(() => {
                    carousel.style.scrollBehavior = prevBehavior;
                    teleporting = false;
                });
            }, { passive: true });
        }

        setupEndless(eventsCarousel);
        setupEndless(roomsCarousel);

        // --- CAROUSEL DOTS ---
        function setupDots(carousel, dotsContainer, originalCount) {
            if (!carousel || !dotsContainer || originalCount === 0) return;

            for (let i = 0; i < originalCount; i++) {
                const dot = document.createElement('span');
                dot.style.cssText = 'display:block;height:8px;border-radius:9999px;transition:all .3s;cursor:pointer;';
                dot.style.width = i === 0 ? '24px' : '8px';
                dot.style.backgroundColor = i === 0 ? '#1c5457' : '#cbd5e1';
                dot.addEventListener('click', () => {
                    const cardWidth = (carousel.children[0]?.offsetWidth ?? 0) + 24;
                    carousel.scrollTo({ left: i * cardWidth, behavior: 'smooth' });
                });
                dotsContainer.appendChild(dot);
            }

            const dots = Array.from(dotsContainer.children);

            function update() {
                const cardWidth = (carousel.children[0]?.offsetWidth ?? 1) + 24;
                const idx = ((Math.round(carousel.scrollLeft / cardWidth) % originalCount) + originalCount) % originalCount;
                dots.forEach((d, i) => {
                    if (i === idx) {
                        d.style.width = '24px';
                        d.style.backgroundColor = '#1c5457';
                    } else {
                        d.style.width = '8px';
                        d.style.backgroundColor = '#cbd5e1';
                    }
                });
            }

            carousel.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            update();
        }

        setupDots(roomsCarousel,  document.getElementById('rooms-dots'),  <?php echo count($rooms); ?>);
        setupDots(eventsCarousel, document.getElementById('events-dots'), <?php echo count($plazaEvents); ?>);

        function scrollEvents(direction) {
            if(!eventsCarousel) return;
            const firstCard = eventsCarousel.querySelector('.event-card');
            const scrollStep = firstCard ? firstCard.offsetWidth + 24 : eventsCarousel.clientWidth / 2;
            eventsCarousel.scrollBy({ left: direction * scrollStep, behavior: 'smooth' });
        }

        function scrollRooms(direction) {
            if(!roomsCarousel) return;
            const firstCard = roomsCarousel.querySelector('.group');
            const scrollStep = firstCard ? firstCard.offsetWidth + 24 : roomsCarousel.clientWidth / 2;
            roomsCarousel.scrollBy({ left: direction * scrollStep, behavior: 'smooth' });
        }

        // Review Randomizer Script
        document.addEventListener("DOMContentLoaded", function() {
            const grid = document.getElementById("reviews-grid");
            if (grid) {
                for (let i = grid.children.length; i >= 0; i--) {
                    grid.appendChild(grid.children[Math.random() * i | 0]);
                }
            }
        });

        function cleanURL(e, id) {
            if (window.location.pathname === '/' || window.location.pathname === '/index') {
                e.preventDefault();
                const element = document.getElementById(id);
                if (element) {
                    window.scrollTo({ top: element.offsetTop - 80, behavior: 'smooth' });
                    history.pushState("", document.title, window.location.pathname + window.location.search);
                }
            }
        }

        // Nav (google-translate, scroll, lang toggle, mobile menu) vive en header.php
    </script>

</body>
</html>
