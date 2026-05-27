<?php
// --- LOAD CONFIGURATION ---
$config = json_decode(file_get_contents('config.json'), true);
$exchangeRateARS = $config['exchangeRateARS'] ?? 1370;

// --- LOAD ROOMS DATABASE (SYNCED WITH ADMIN PANEL) ---
$roomsFile = 'rooms.json';
$rooms = [];
if (file_exists($roomsFile)) {
    $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
}

// Fallback just in case JSON is empty or missing
// if (empty($rooms)) {
//     $rooms = [
//         [
//             "id" => 1,
//             "name" => "Double Room with Shared Bathroom",
//             "type" => "Superior Private",
//             "capacity" => 3,
//             "price" => "From $35",
//             "price_ars" => "AR$ 38,911",
//             "description" => "Spacious private room with a queen-size bed, perfect for couples seeking comfort and privacy.",
//             "amenities" => ["Air Conditioning", "Private Bathroom", "TV", "Wi-Fi"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495911.jpg?k=2d05efc0c85d55bc46117c2fac077f173fc192703ecc6f42a6b1d03f69f2397b&o="
//         ],
//         [
//             "id" => 2,
//             "name" => "Family Room",
//             "type" => "Superior Private",
//             "capacity" => 4,
//             "price" => "From $35",
//             "price_ars" => "AR$ 35,024",
//             "description" => "Spacious private room with a queen-size bed, perfect for couples seeking comfort and privacy.",
//             "amenities" => ["Air Conditioning", "Private Bathroom", "TV", "Wi-Fi"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495898.jpg?k=35f2d062726868da5ba53f68a2d7f964da6e6839de4e4476b483ec6b909ee07c&o="
//         ],
//         [
//             "id" => 4,
//             "name" => "4-Bed Female Dorm",
//             "type" => "Female Only",
//             "capacity" => 4,
//             "price" => "From $18",
//             "price_ars" => "AR$ 17,883",
//             "description" => "A safe and comfortable space exclusively for female travelers.",
//             "amenities" => ["Lockers", "Shared Bathroom", "Wi-Fi", "Mirror"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495909.jpg?k=83cb891bba2e912e65b133b0a7523947600befddbccdeee68ff26ce30b5e37b3&o="
//         ],
//         [
//             "id" => 5,
//             "name" => "4-Bed Mixed Dorm",
//             "type" => "Shared Dormitory",
//             "capacity" => 4,
//             "price" => "From $18",
//             "description" => "Private room with one double bed and one single bed, great for small families or groups.",
//             "amenities" => ["Air Conditioning", "Private Bathroom", "Wi-Fi"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495915.jpg?k=96bca737f1985971884b70e028507f92633ac7a28f2a52a37683029a75b158e9&o="
//         ],
//         [
//             "id" => 6,
//             "name" => "8-Bed Mixed Dorm",
//             "type" => "Shared Dormitory",
//             "capacity" => 8,
//             "price" => "From $15",
//             "description" => "Our most economical option, perfect for meeting fellow travelers.",
//             "amenities" => ["Lockers", "Shared Bathroom", "Wi-Fi"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495893.jpg?k=651b859b6581c92f8fcdfd7b0deb8ebecd90d1aa688208f85ae81e2b3032c466&o="
//         ],


//         [
//             "id" => 7,
//             "name"=> "Double Room with Private Bathroom",
//             "type" => "Private Dormitory",
//             "capacity" => 2,
//             "price" => "From $25",
//             "price_ars" => "",
//             "description" => "Private room for two people with small bathroom, bunk bed, window overlooking the patio and fan. Sheets, towels, hand soap, and toilet paper included.",
//             "image" => "https:\/\/hostelplaza.com.ar\/static\/media\/habitacion6.f2157d48adedd5a6164d.jpg",

//         ],
//         [
//             "id" => 8,
//             "name"=> "Family Room with Private Bedroom",
//             "type" => "Private Dormitory",
//             "capacity" => 2,
//             "price" => "From $35",
//             "price_ars" => "",
//             "image"=> "https=>\/\/hostelplaza.com.ar\/static\/media\/habitacion7.4b84d789a7efa00b87d1.jpeg",
//             "description"=> "Private room for two people with a double bed and private bathroom, window with city view, fan, linens, towels, hand soap, and toilet paper included.",
//         ]

//     ];
//

// Ensure price_ars is calculated based on price and exchangeRateARS
foreach ($rooms as &$room) {
    $raw_price = (float) preg_replace('/[^0-9.]/', '', $room['price']);
    $room['price_ars'] = "AR$ " . number_format($raw_price * $exchangeRateARS, 0, ',', '.');
}
unset($room);

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

        /* --- THE ULTIMATE GOOGLE TRANSLATE KILLER CSS --- */
        #google_translate_element,
        .goog-te-banner-frame,
        .skiptranslate,
        .goog-te-gadget-icon,
        .goog-tooltip,
        .goog-tooltip:hover,
        #goog-gt-tt {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
        body {
            top: 0px !important;
            position: static !important;
        }
        html {
            height: auto !important;
            top: 0px !important;
        }
        html.translated-ltr, html.translated-rtl {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
        .goog-text-highlight {
            background-color: transparent !important;
            box-shadow: none !important;
        }

        .lang-btn {
            transition: all 0.3s ease;
        }
        .lang-btn.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
        }
        .glass .lang-toggle-container {
            background-color: rgba(0, 0, 0, 0.05);
            border-color: rgba(0, 0, 0, 0.1);
        }
        .glass .lang-btn {
            color: #64748b;
        }
        .glass .lang-btn.active {
            background-color: #1c5457;
            color: #fff;
        }

        /* Dynamic Menu Hovers */
        #mainNav.bg-transparent .nav-link:hover { color: #5eead4; }
        #mainNav.glass .nav-link:hover { color: #1c5457; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased overflow-x-hidden">

    <div id="google_translate_element"></div>

   <nav id="mainNav" class="fixed top-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
            <a href="/" class="transition-opacity hover:opacity-80 block">
                <img id="logoTop" src="hostel.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="block">
                <img id="logoScrolled" src="H.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="hidden">
            </a>

            <div id="desktopMenu" class="hidden md:flex items-center space-x-6 font-medium text-white transition-colors">
                <a href="/" class="nav-link transition-colors">Home</a>
                <a href="about" class="nav-link transition-colors">About Us</a>
                <a href="rooms" class="nav-link transition-colors">Rooms</a>
                <a href="tourist-events" class="nav-link transition-colors">Tourist Events</a>

                <div class="notranslate lang-toggle-container flex items-center bg-white/10 backdrop-blur-sm rounded-full p-1 border border-white/20 text-[11px] font-bold tracking-wider ml-2 transition-all">
                    <button class="lang-btn active px-3 py-1.5 rounded-full" onclick="changeLanguage('en', this)">EN</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('es', this)">ES</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('pt', this)">PT</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('fr', this)">FR</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('de', this)">DE</button>
                </div>

                <a href="book" class="bg-teal-400 text-slate-900 font-bold px-6 py-2.5 rounded-full hover:bg-teal-300 transition-all shadow-lg ml-2 border-none">
                    Book Now
                </a>
            </div>

            <button id="mobileMenuBtn" class="md:hidden p-2 text-white transition-colors">
                <i data-lucide="menu"></i>
            </button>
        </div>

        <div id="mobileMenu" class="hidden absolute top-full left-0 w-full glass p-6 flex-col space-y-4 shadow-xl text-slate-900">
            <a href="index.php" class="text-left text-lg font-medium block hover:text-teal">Home</a>
            <a href="about" class="text-left text-lg font-medium block hover:text-teal">About Us</a>
            <a href="rooms.php" class="text-left text-lg font-medium block hover:text-teal">Rooms</a>
            <a href="tourist-events.php" class="text-left text-lg font-medium block hover:text-teal">Tourist Events</a>
            <a href="history.php" class="text-left text-lg font-medium block hover:text-teal">History</a>

            <div class="notranslate flex items-center justify-center bg-slate-100 rounded-full p-1 border border-slate-200 text-xs font-bold tracking-wider mt-4">
                <button class="lang-btn-mob flex-1 active bg-teal text-white px-3 py-2 rounded-full transition-all" onclick="changeLanguage('en', this, true)">EN</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('es', this, true)">ES</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('pt', this, true)">PT</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('fr', this, true)">FR</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('de', this, true)">DE</button>
            </div>

            <a href="book.php" class="bg-teal-400 text-slate-900 hover:bg-teal-300 transition-all w-full py-3 rounded-xl font-bold text-center block mt-2">Book Now</a>
        </div>
    </nav>

    <section id="home" class="relative h-screen min-h-[700px] flex items-center justify-center">
        <div class="absolute inset-0 z-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat md:bg-fixed" style="background-image: url('https://hostelplaza.com.ar/static/media/home2.44870ef3b7a6358c6e4f.jpg');"></div>
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

        <div class="absolute bottom-0 left-1/2 -translate-x-1/2 translate-y-1/2 w-full max-w-5xl px-6 z-20">
            <form action="book" method="GET" class="bg-[#E5E7EB] rounded-2xl p-6 md:p-8 shadow-2xl grid grid-cols-1 md:grid-cols-3 gap-6 items-end border border-slate-200">
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-teal"></i> Check In</label>
                    <input type="date" name="checkIn" required class="w-full bg-white border border-slate-200 rounded-lg p-3 text-slate-700 outline-none" />
                </div>
                <div class="space-y-2">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2"><i data-lucide="calendar" class="w-4 h-4 text-teal"></i> Check Out</label>
                    <input type="date" name="checkOut" required class="w-full bg-white border border-slate-200 rounded-lg p-3 text-slate-700 outline-none" />
                </div>
                <button type="submit" class="bg-teal-400 text-slate-900 h-[50px] rounded-lg font-bold text-lg hover:bg-teal-300 transition-all shadow-md">Book Now</button>
            </form>
        </div>
    </section>

    <div class="h-48 md:h-24"></div>

    <section id="about" class="py-24 px-6 max-w-7xl mx-auto w-full">
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

    <section id="rooms" class="py-24 px-6 max-w-7xl mx-auto w-full bg-slate-50">
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

                    <div class="absolute bottom-5 left-5 flex items-center gap-2">
                        <div class="bg-slate-900/90 backdrop-blur-sm px-4 py-2 rounded-xl text-white font-bold shadow-lg border border-white/10 flex flex-col justify-center h-full">
                            <span class="text-lg leading-none"><?php echo htmlspecialchars($room['price']); ?></span>
                        </div>
                        <?php if(!empty($room['price_ars'])): ?>
                            <div class="bg-white/95 backdrop-blur-sm px-3 py-2 rounded-xl text-teal-800 font-bold shadow-lg border border-white/50 text-sm flex items-center h-full">
                                <?php echo htmlspecialchars($room['price_ars']); ?>
                            </div>
                        <?php endif; ?>
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

                    <div class="mt-auto">
                        <a href="room.php?id=<?php echo urlencode($room['id']); ?>" class="block text-center w-full py-4 bg-white text-teal rounded-2xl font-bold transition-all shadow-sm border-2 border-teal hover:bg-teal hover:text-white group-hover:shadow-md">
                            Check Availability
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section id="plaza-events" class="py-24 px-6 max-w-7xl mx-auto w-full relative">
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
                        <img src="<?php echo htmlspecialchars($pevent['image'] ?? ''); ?>" alt="Event" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">

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

    <script>
        lucide.createIcons();

        // --- CAROUSEL AUTO-SCROLL ENGINE ---
        const eventsCarousel = document.getElementById('events-carousel');
        const roomsCarousel = document.getElementById('rooms-carousel');

        function setupEndless(carousel) {
            if (!carousel) return;
            const children = Array.from(carousel.children);
            children.forEach(child => {
                const clone = child.cloneNode(true);
                carousel.appendChild(clone);
            });

            carousel.addEventListener('scroll', () => {
                const scrollLeft = carousel.scrollLeft;
                const halfWidth = carousel.scrollWidth / 2;

                if (scrollLeft >= halfWidth) {
                    carousel.scrollLeft = scrollLeft - halfWidth;
                } else if (scrollLeft <= 0) {
                    carousel.scrollLeft = scrollLeft + halfWidth;
                }
            });
        }

        setupEndless(eventsCarousel);
        setupEndless(roomsCarousel);

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

        window.addEventListener('scroll', () => {
            const nav = document.getElementById('mainNav');
            const logoTop = document.getElementById('logoTop');
            const logoScrolled = document.getElementById('logoScrolled');
            if (window.scrollY > 50) {
                nav.classList.add('glass', 'py-3'); nav.classList.remove('bg-transparent', 'py-5');
                logoTop.classList.add('hidden'); logoScrolled.classList.remove('hidden');

                document.querySelectorAll('.nav-link').forEach(el => {
                    el.classList.remove('text-white');
                    el.classList.add('text-slate-900');
                });
                document.getElementById('mobileMenuBtn').classList.remove('text-white');
                document.getElementById('mobileMenuBtn').classList.add('text-slate-900');

                // Keep translator text clean on scroll
                document.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
                    el.style.color = '#64748b'; // slate-500
                });
            } else {
                nav.classList.add('bg-transparent', 'py-5'); nav.classList.remove('glass', 'py-3');
                logoTop.classList.remove('hidden'); logoScrolled.classList.add('hidden');

                document.querySelectorAll('.nav-link').forEach(el => {
                    el.classList.add('text-white');
                    el.classList.remove('text-slate-900');
                });
                document.getElementById('mobileMenuBtn').classList.add('text-white');
                document.getElementById('mobileMenuBtn').classList.remove('text-slate-900');

                // Keep translator text clean on top
                document.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
                    el.style.color = 'white';
                });
            }
        });

        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden'); menu.classList.toggle('flex');
        });

        // --- RESTORED LANGUAGE TRANSLATION SCRIPT ---
        function changeLanguage(langCode, btnElement, isMobile = false) {
            var selectField = document.querySelector("#google_translate_element select") || document.querySelector(".goog-te-combo");
            if(selectField) {
                selectField.value = langCode;
                selectField.dispatchEvent(new Event('change'));
            }

            var btnClass = isMobile ? '.lang-btn-mob' : '.lang-btn';
            document.querySelectorAll(btnClass).forEach(function(btn) {
                if(isMobile) {
                    btn.classList.remove('bg-teal', 'text-white');
                    btn.classList.add('text-slate-500');
                } else {
                    btn.classList.remove('active');
                }
            });

            if(isMobile) {
                btnElement.classList.add('bg-teal', 'text-white');
                btnElement.classList.remove('text-slate-500');
            } else {
                btnElement.classList.add('active');
            }
        }

        // Kills the annoying Google Translate top bar
        setInterval(function() {
            if (document.body.style.top !== '0px') {
                document.body.style.top = '0px';
            }
            if (document.documentElement.style.top !== '0px') {
                document.documentElement.style.top = '0px';
            }
        }, 50);
    </script>

    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,es,fr,de,pt',
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

</body>
</html>
