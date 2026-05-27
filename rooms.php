<?php
// --- EXCHANGE RATE SETTING ---
$exchangeRateARS = 1370; // EDIT THIS TO UPDATE THE USD TO ARS CONVERSION RATE

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
//             "price" => "From $37", // Converted from 38,911 ARS
//             "price_ars" => "AR$ 38,911",
//             "description" => "Spacious private room with a queen-size bed, perfect for couples seeking comfort and privacy.",
//             "amenities" => ["Air Conditioning", "Shared Bathroom", "Lockers", "Wi-Fi"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495911.jpg?k=2d05efc0c85d55bc46117c2fac077f173fc192703ecc6f42a6b1d03f69f2397b&o="
//         ],
//         [
//             "id" => 2,
//             "name" => "Family Room",
//             "type" => "Superior Private",
//             "capacity" => 4,
//             "price" => "From $33", // Converted from 35,024 ARS
//             "price_ars" => "AR$ 35,024",
//             "description" => "Spacious private room with a queen-size bed, perfect for couples seeking comfort and privacy.",
//             "amenities" => ["Air Conditioning", "Shared Bathroom", "Bunk Bed", "Wi-Fi"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495898.jpg?k=35f2d062726868da5ba53f68a2d7f964da6e6839de4e4476b483ec6b909ee07c&o="
//         ],
//         [

//             "id" => 4,
//             "name" => "4-Bed Female Dorm",
//             "type" => "Female Only",
//             "capacity" => 4,
//             "price" => "From $17", // Converted from 17,883 ARS
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
//             "price_ars" => "",
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
//             "price_ars" => "",
//             "description" => "Our most economical option, perfect for meeting fellow travelers.",
//             "amenities" => ["Lockers", "Shared Bathroom", "Wi-Fi"],
//             "image" => "https://cf.bstatic.com/xdata/images/hotel/max1024x768/729495893.jpg?k=651b859b6581c92f8fcdfd7b0deb8ebecd90d1aa688208f85ae81e2b3032c466&o="
//         ]
//     ];
// }

// Ensure price_ars is calculated based on price and exchangeRateARS
foreach ($rooms as &$room) {
    $raw_price = (float) preg_replace('/[^0-9.]/', '', $room['price']);
    $room['price_ars'] = "AR$ " . number_format($raw_price * $exchangeRateARS, 0, ',', '.');
}
unset($room);
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
        .glass {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .room-card-image-container {
            overflow: hidden;
        }
        .room-card-image-container img {
            transition: transform 0.7s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .group:hover .room-card-image-container img {
            transform: scale(1.05);
        }

        /* --- THE ULTIMATE GOOGLE TRANSLATE KILLER CSS --- */
        #google_translate_element,
        .goog-te-banner-frame,
        .skiptranslate,
        .goog-te-gadget-icon,
        .goog-tooltip,
        .goog-tooltip:hover,
        #goog-gt-tt { display: none !important; opacity: 0 !important; visibility: hidden !important; }
        body { top: 0px !important; position: static !important; }
        html { height: auto !important; top: 0px !important; }
        html.translated-ltr, html.translated-rtl { margin-top: 0 !important; padding-top: 0 !important; }
        .goog-text-highlight { background-color: transparent !important; box-shadow: none !important; }

        .lang-btn { transition: all 0.3s ease; }
        .lang-btn.active { background-color: rgba(255, 255, 255, 0.2); color: #fff; }
        .lang-toggle-container { background-color: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); }

        /* Dynamic Menu Hovers */
        #mainNav.bg-transparent .nav-link:hover { color: #5eead4; }
        #mainNav.glass .nav-link:hover { color: #1c5457; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <div id="google_translate_element"></div>

    <nav id="mainNav" class="fixed top-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
            <a href="index.php" class="transition-opacity hover:opacity-80 block">
                <img id="logoTop" src="hostel.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="block">
                <img id="logoScrolled" src="H.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="hidden">
            </a>

            <div id="desktopMenu" class="hidden md:flex items-center space-x-6 font-medium text-white transition-colors">
                <a href="/" class="nav-link transition-colors">Home</a>
                <a href="about" class="nav-link transition-colors">About Us</a>
                <a href="rooms" id="roomsDesktopLink" class="text-teal-300 font-bold transition-colors border-b-2 border-teal-300 pb-1">Rooms</a>
                <a href="tourist-events" class="nav-link transition-colors">Tourist Events</a>

                <div class="notranslate lang-toggle-container flex items-center backdrop-blur-sm rounded-full p-1 border text-[11px] font-bold tracking-wider ml-2 transition-all">
                    <button class="lang-btn active px-3 py-1.5 rounded-full" onclick="changeLanguage('en', this)">EN</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('es', this)">ES</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('pt', this)">PT</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('fr', this)">FR</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('de', this)">DE</button>
                </div>

                <a href="book.php" class="bg-teal-400 text-slate-900 font-bold px-6 py-2.5 rounded-full hover:bg-teal-300 transition-all shadow-lg ml-2 border-none">
                    Book Now
                </a>
            </div>

            <button id="mobileMenuBtn" class="md:hidden p-2 text-white transition-colors">
                <i data-lucide="menu"></i>
            </button>
        </div>

        <div id="mobileMenu" class="hidden absolute top-full left-0 w-full glass p-6 flex-col space-y-4 shadow-xl text-white border-t border-white/10">
            <a href="/" class="text-left text-lg font-medium block hover:text-teal-300">Home</a>
            <a href="about" class="text-left text-lg font-medium block hover:text-teal-300">About Us</a>
            <a href="rooms" class="text-left text-lg font-bold text-teal-300 block">Rooms</a>
            <a href="tourist-events" class="text-left text-lg font-medium block hover:text-teal-300">Tourist Events</a>

            <div class="notranslate flex items-center justify-center bg-slate-800 rounded-full p-1 border border-slate-700 text-xs font-bold tracking-wider mt-4">
                <button class="lang-btn-mob flex-1 active bg-teal text-white px-3 py-2 rounded-full transition-all" onclick="changeLanguage('en', this, true)">EN</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('es', this, true)">ES</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('pt', this, true)">PT</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('fr', this, true)">FR</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('de', this, true)">DE</button>
            </div>

            <a href="book.php" class="bg-teal-400 text-slate-900 hover:bg-teal-300 transition-all w-full py-3 rounded-xl font-bold text-center block mt-2">Book Now</a>
        </div>
    </nav>

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

        </div>
    </main>

    <?php include 'footer.php'; ?>

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

    <script>
        lucide.createIcons();

        // --- GOOGLE TRANSLATE KILLER LOOP ---
        setInterval(function() {
            if (document.body.style.top !== '0px') {
                document.body.style.top = '0px';
            }
            if (document.documentElement.style.top !== '0px') {
                document.documentElement.style.top = '0px';
            }
        }, 50);

        function changeLanguage(langCode, btnElement, isMobile = false) {
            var selectField = document.querySelector("#google_translate_element select");
            if(selectField) {
                selectField.value = langCode;
                selectField.dispatchEvent(new Event('change'));
            }

            var btnClass = isMobile ? '.lang-btn-mob' : '.lang-btn';
            document.querySelectorAll(btnClass).forEach(function(btn) {
                if(isMobile) {
                    btn.classList.remove('bg-teal', 'text-white');
                    btn.classList.add('text-slate-400');
                } else {
                    btn.classList.remove('active');
                }
            });

            if(isMobile) {
                btnElement.classList.add('bg-teal', 'text-white');
                btnElement.classList.remove('text-slate-400');
            } else {
                btnElement.classList.add('active');
            }
        }

        // --- NAV SCROLL EFFECT ---
        const nav = document.getElementById('mainNav');
        const menuBtn = document.getElementById('mobileMenuBtn');
        const logoTop = document.getElementById('logoTop');
        const logoScrolled = document.getElementById('logoScrolled');
        const roomsDesktopLink = document.getElementById('roomsDesktopLink');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                nav.classList.add('glass', 'py-3');
                nav.classList.remove('bg-transparent', 'py-5');

                document.querySelectorAll('.nav-link').forEach(el => {
                    el.classList.remove('text-white');
                    el.classList.add('text-slate-900');
                });

                menuBtn.classList.add('text-slate-900');
                menuBtn.classList.remove('text-white');

                if(logoTop && logoScrolled) {
                    logoTop.classList.add('hidden');
                    logoTop.classList.remove('block');
                    logoScrolled.classList.add('block');
                    logoScrolled.classList.remove('hidden');
                }

                if(roomsDesktopLink) {
                    roomsDesktopLink.classList.remove('text-teal-300', 'border-teal-300');
                    roomsDesktopLink.classList.add('text-teal', 'border-teal');
                }

                // Keep translator text clean on scroll
                document.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
                    el.style.color = '#64748b';
                });
            } else {
                nav.classList.add('bg-transparent', 'py-5');
                nav.classList.remove('glass', 'py-3');

                document.querySelectorAll('.nav-link').forEach(el => {
                    el.classList.add('text-white');
                    el.classList.remove('text-slate-900');
                });

                menuBtn.classList.add('text-white');
                menuBtn.classList.remove('text-slate-900');

                if(logoTop && logoScrolled) {
                    logoTop.classList.add('block');
                    logoTop.classList.remove('hidden');
                    logoScrolled.classList.add('hidden');
                    logoScrolled.classList.remove('block');
                }

                if(roomsDesktopLink) {
                    roomsDesktopLink.classList.add('text-teal-300', 'border-teal-300');
                    roomsDesktopLink.classList.remove('text-teal', 'border-teal');
                }

                // Keep translator text clean on top
                document.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
                    el.style.color = 'white';
                });
            }
        });

        // --- MOBILE MENU TOGGLE ---
        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
            menu.classList.toggle('flex');
        });
    </script>
</body>
</html>