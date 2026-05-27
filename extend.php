<?php
$successMsg = false;
$exchangeRateARS = 1370; // Fallback rate only used if ARS price is left blank in admin

// --- LOAD ROOMS DATABASE (SYNCED WITH ADMIN PANEL) ---
$roomsFile = 'rooms.json';
$rooms = [];
if (file_exists($roomsFile)) {
    $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
}

// // Fallback just in case JSON is empty or missing
// if (empty($rooms)) {
//     $rooms = [
//         [ "id" => "1", "name" => "Double Room with Shared Bathroom", "price" => "35", "price_ars" => "38911" ],
//         [ "id" => "2", "name" => "Family Room", "price" => "35", "price_ars" => "35024" ],
//         [ "id" => "4", "name" => "4-Bed Female Dorm", "price" => "18", "price_ars" => "17883" ],
//         [ "id" => "5", "name" => "4-Bed Mixed Dorm", "price" => "18", "price_ars" => "" ],
//         [ "id" => "6", "name" => "8-Bed Mixed Dorm", "price" => "15", "price_ars" => "" ]
//     ];
// }

// Ensure price_ars is calculated based on price and exchangeRateARS
foreach ($rooms as &$room) {
    $raw_price = (float) preg_replace('/[^0-9.]/', '', $room['price']);
    $room['price_ars'] = (string)($raw_price * $exchangeRateARS);
}
unset($room);

$bookingsFile = 'bookings.json';

// Handle the form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['extend_stay'])) {
    $bookings = file_exists($bookingsFile) ? json_decode(file_get_contents($bookingsFile), true) : [];

    // Grab the exact Room ID the guest selected
    $roomId = htmlspecialchars($_POST['room_id']);

    $newBooking = [
        "id" => uniqid('b_'),
        "roomId" => $roomId,
        "checkIn" => htmlspecialchars($_POST['check_in']),
        "checkOut" => htmlspecialchars($_POST['check_out']),
        "guestsCount" => 1,
        "guestName" => htmlspecialchars($_POST['guest_name']),
        "age" => '', "gender" => '', "nationality" => '', "idType" => '', "idNumber" => '',
        "phone" => htmlspecialchars($_POST['whatsapp']),
        "email" => htmlspecialchars($_POST['email']),
        "notes" => "VIP Extension Request (20% Discount Expected).",
        "totalPrice" => 0, // Left at 0 so admin can apply the final discounted total in the dashboard
        "amountPaid" => 0,
        "paymentMethod" => "Pending",
        "source" => "Direct Extension",
        "status" => "pending" // Drops into admin dashboard as pending
    ];

    array_unshift($bookings, $newBooking);
    file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
    $successMsg = true;
}

// --- CALCULATE BOOKED DATES FOR CALENDAR GRAY-OUT ---
$blockedDatesByRoom = [];
foreach ($rooms as $r) { $blockedDatesByRoom[$r['id']] = []; }

if (file_exists($bookingsFile)) {
    $allBookings = json_decode(file_get_contents($bookingsFile), true) ?: [];
    $occupancy = [];

    foreach ($allBookings as $b) {
        if (strtolower($b['status'] ?? '') === 'cancelled' || strtolower($b['status'] ?? '') === 'checked out') continue;
        $rId = $b['roomId'] ?? '';
        if ($rId === 'unassigned' || empty($rId)) continue;
        if (empty($b['checkIn']) || empty($b['checkOut'])) continue;

        try {
            $start = new DateTime($b['checkIn']);
            $end = new DateTime($b['checkOut']);
            if ($start >= $end) continue;

            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            foreach ($period as $date) {
                $dStr = $date->format('Y-m-d');
                if (!isset($occupancy[$rId][$dStr])) $occupancy[$rId][$dStr] = 0;
                $occupancy[$rId][$dStr] += 1; // 1 booking = 1 bed
            }
        } catch (Exception $e) {
            // Ignore invalid dates quietly
        }
    }

    foreach ($rooms as $r) {
        $rId = $r['id'];
        $name = strtolower($r['name']);

        // Capacity logic based on room names
        if (strpos($name, '4-bed') !== false) $cap = 4;
        elseif (strpos($name, '8-bed') !== false) $cap = 8;
        else $cap = 1; // Doubles and Family rooms block entire room

        if (isset($occupancy[$rId])) {
            foreach ($occupancy[$rId] as $dStr => $count) {
                if ($count >= $cap) {
                    $blockedDatesByRoom[$rId][] = $dStr;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extend Your Stay | Hostel Plaza</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased overflow-x-hidden">

    <div id="google_translate_element"></div>

    <nav id="mainNav" class="fixed top-0 w-full z-50 transition-all duration-300 glass py-3">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
            <a href="/" class="transition-opacity hover:opacity-80 block shrink-0">
                <img src="hostel.png" alt="Hostel Plaza Logo" style="height: 60px; width: auto; object-fit: contain; filter: brightness(0) invert(1);" class="block" onerror="this.style.display='none'">
            </a>

            <div id="desktopMenu" class="hidden lg:flex items-center gap-10 text-sm font-semibold text-white">
                <nav class="flex items-center gap-8 uppercase tracking-[0.15em] text-[13px] font-bold">
                    <a href="/" class="hover:text-teal-300 transition-colors">Home</a>
                    <a href="about" class="hover:text-teal-300 transition-colors">About Us</a>
                    <a href="rooms" class="hover:text-teal-300 transition-colors">Rooms</a>
                    <a href="tourist-events" class="hover:text-teal-300 transition-colors">Tourist Events</a>
                </nav>

                <div class="flex items-center gap-6 pl-4">
                    <div class="notranslate lang-toggle-container flex items-center backdrop-blur-sm rounded-full p-1 border text-[11px] font-bold tracking-wider transition-all">
                        <button class="lang-btn active px-3 py-1.5 rounded-full" onclick="changeLanguage('en', this)">EN</button>
                        <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('es', this)">ES</button>
                        <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('pt', this)">PT</button>
                        <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('fr', this)">FR</button>
                        <button class="lang-btn px-3 py-1.5 rounded-full text-white/70 hover:text-white" onclick="changeLanguage('de', this)">DE</button>
                    </div>
                </div>
            </div>

            <button id="mobileMenuBtn" class="lg:hidden p-2 text-white transition-colors">
                <i data-lucide="menu"></i>
            </button>
        </div>

        <div id="mobileMenu" class="hidden absolute top-full left-0 w-full glass p-6 flex-col space-y-4 shadow-xl text-white border-t border-white/10">
            <a href="/" class="text-left text-lg font-medium block hover:text-teal-300">Home</a>
            <a href="about" class="text-left text-lg font-medium block hover:text-teal-300">About Us</a>
            <a href="rooms" class="text-left text-lg font-medium block hover:text-teal-300">Rooms</a>
            <a href="events" class="text-left text-lg font-medium block hover:text-teal-300">Events</a>

            <div class="notranslate flex items-center justify-center bg-slate-800 rounded-full p-1 border border-slate-700 text-xs font-bold tracking-wider mt-4">
                <button class="lang-btn-mob flex-1 active bg-teal text-white px-3 py-2 rounded-full transition-all" onclick="changeLanguage('en', this, true)">EN</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('es', this, true)">ES</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('pt', this, true)">PT</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('fr', this, true)">FR</button>
                <button class="lang-btn-mob flex-1 text-slate-400 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('de', this, true)">DE</button>
            </div>
        </div>
    </nav>

    <div class="w-full pt-40 pb-16 bg-slate-900 border-b border-white/10">
        <div class="max-w-3xl mx-auto px-6 text-center">
            <div class="w-16 h-16 bg-teal/20 text-teal-400 rounded-2xl flex items-center justify-center mx-auto mb-6 shadow-inner border border-teal-400/20">
                <i data-lucide="calendar-plus" class="w-8 h-8"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-serif font-bold text-white mb-4 nav-label notranslate" data-en="Extend Your Stay" data-es="Extiende tu Estancia">Extend Your Stay</h1>
            <p class="text-lg text-slate-400 font-medium nav-label notranslate" data-en="Skip the booking platforms. Fill out this quick form and we'll WhatsApp you our special discounted guest rate." data-es="Evita las plataformas. Completa este formulario y te enviaremos por WhatsApp nuestra tarifa especial con descuento.">Skip the booking platforms. Fill out this quick form and we'll WhatsApp you our special discounted guest rate.</p>
        </div>
    </div>

    <main class="flex-1 w-full bg-[#FDFBF7] py-16">
        <div class="max-w-2xl mx-auto px-6">

            <?php if($successMsg): ?>
                <div class="bg-emerald-50 border border-emerald-200 rounded-3xl p-10 text-center shadow-lg transform transition-all duration-500 scale-100">
                    <div class="w-20 h-20 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6 shadow-inner">
                        <i data-lucide="check" class="w-10 h-10"></i>
                    </div>
                    <h2 class="text-3xl font-serif font-bold text-slate-900 mb-3 nav-label notranslate" data-en="Request Received!" data-es="¡Solicitud Recibida!">Request Received!</h2>
                    <p class="text-slate-600 text-lg nav-label notranslate" data-en="Our reception team has been notified. We will message you on WhatsApp shortly with your discounted rate and room confirmation." data-es="Nuestro equipo de recepción ha sido notificado. Te enviaremos un mensaje por WhatsApp en breve con tu tarifa con descuento y la confirmación de la habitación.">Our reception team has been notified. We will message you on WhatsApp shortly with your discounted rate and room confirmation.</p>
                    <a href="/" class="mt-8 inline-block bg-slate-900 text-white px-8 py-3 rounded-xl font-bold hover:bg-slate-800 transition-colors shadow-md nav-label notranslate" data-en="Back to Home" data-es="Volver al Inicio">Back to Home</a>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-3xl shadow-2xl border border-slate-200/60 p-8 md:p-10 relative overflow-hidden">
                    <form method="POST" action="" class="space-y-8 relative z-10">
                        <input type="hidden" name="extend_stay" value="1">

                        <div class="space-y-6">
                            <h3 class="text-[11px] font-bold uppercase tracking-widest text-teal border-b border-slate-100 pb-2 nav-label notranslate" data-en="Your Details" data-es="Tus Detalles">Your Details</h3>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2 nav-label notranslate" data-en="Full Name *" data-es="Nombre Completo *">Full Name *</label>
                                <input type="text" name="guest_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-teal/30 shadow-inner transition-all">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2 nav-label notranslate" data-en="WhatsApp Number *" data-es="Número de WhatsApp *">WhatsApp Number *</label>
                                    <input type="tel" name="whatsapp" required placeholder="+54 9..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-teal/30 shadow-inner transition-all">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2 nav-label notranslate" data-en="Email Address" data-es="Correo Electrónico">Email Address</label>
                                    <input type="email" name="email" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-teal/30 shadow-inner transition-all">
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6 pt-4">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                                <h3 class="text-[11px] font-bold uppercase tracking-widest text-teal nav-label notranslate" data-en="Extension Details" data-es="Detalles de Extensión">Extension Details</h3>
                                <span class="bg-amber-100 text-amber-700 text-[10px] font-bold px-2 py-1 rounded-md uppercase tracking-widest">20% VIP Discount Applied</span>
                            </div>

                            <div>
                                <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2 nav-label notranslate" data-en="Room Preference *" data-es="Preferencia de Habitación *">Room Preference *</label>
                                <select name="room_id" id="room_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-teal/30 shadow-inner transition-all cursor-pointer">
                                    <option value="">-- Choose a Room --</option>
                                    <?php
                                    foreach($rooms as $room):
                                        $raw_price = (float) preg_replace('/[^0-9.]/', '', $room['price']);
                                        $raw_ars = (float) $room['price_ars'];

                                        $discounted_usd = $raw_price * 0.8;
                                        $discounted_ars = $raw_ars * 0.8;
                                    ?>
                                        <option value="<?php echo $room['id']; ?>">
                                            <?php echo htmlspecialchars($room['name']); ?> - (VIP: $<?php echo number_format($discounted_usd, 2); ?> / AR$ <?php echo number_format($discounted_ars, 0, ',', '.'); ?> per night)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2 nav-label notranslate" data-en="Check In (Today) *" data-es="Entrada (Hoy) *">Check In (Today) *</label>
                                    <input type="text" name="check_in" id="check_in" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-teal/30 shadow-inner transition-all cursor-pointer">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold uppercase tracking-widest text-slate-500 mb-2 nav-label notranslate" data-en="New Check Out *" data-es="Nueva Salida *">New Check Out *</label>
                                    <input type="text" name="check_out" id="check_out" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm font-bold text-slate-800 outline-none focus:bg-white focus:ring-2 focus:ring-teal/30 shadow-inner transition-all cursor-pointer">
                                </div>
                            </div>
                        </div>

                        <div class="pt-6">
                            <button type="submit" class="w-full py-4 bg-teal text-white rounded-2xl font-bold text-lg hover:bg-teal-hover hover:-translate-y-0.5 transition-all shadow-lg nav-label notranslate" data-en="Request VIP Extension" data-es="Solicitar Extensión VIP">
                                Request VIP Extension
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php
    if(file_exists('footer.php')) {
        include 'footer.php';
    }
    ?>

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

            // Manual translation fallback for dynamically swapped labels
            document.querySelectorAll('.nav-label').forEach(label => {
                label.innerText = label.getAttribute('data-' + langCode) || label.getAttribute('data-en');
            });

            localStorage.setItem('site_lang', langCode);
        }

        window.addEventListener('load', function() {
            setTimeout(function() {
                let savedLang = localStorage.getItem('site_lang') || 'en';
                let btns = document.querySelectorAll('.lang-btn');

                let targetBtn = null;
                if(savedLang === 'en') targetBtn = btns[0];
                if(savedLang === 'es') targetBtn = btns[1];
                if(savedLang === 'pt') targetBtn = btns[2];
                if(savedLang === 'fr') targetBtn = btns[3];
                if(savedLang === 'de') targetBtn = btns[4];

                if(targetBtn && savedLang !== 'en') {
                    changeLanguage(savedLang, targetBtn);
                } else if (targetBtn && savedLang === 'en') {
                    targetBtn.classList.add('bg-white/20', 'text-white');
                    targetBtn.classList.remove('text-white/70');
                }
            }, 1000);
        });

        // --- MOBILE MENU TOGGLE ---
        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
            menu.classList.toggle('flex');
        });

        // --- FLATPICKR CALENDAR BLOCKED DATES ENGINE ---
        const blockedDates = <?php echo json_encode($blockedDatesByRoom); ?>;
        let fpIn, fpOut;
        const roomSelect = document.getElementById('room_id');
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');

        function initCalendars() {
            if(!checkInInput || !checkOutInput) return;

            const selectedRoom = roomSelect ? roomSelect.value : "";
            const disabledDates = blockedDates[selectedRoom] || [];

            if(fpIn) fpIn.destroy();
            if(fpOut) fpOut.destroy();

            // Set defaults if inputs are empty
            if (!checkInInput.value) {
                let today = new Date();
                checkInInput.value = today.toISOString().split('T')[0];
            }
            let minDateOut = checkInInput.value ? new Date(checkInInput.value).fp_incr(1) : "today";

            fpIn = flatpickr("#check_in", {
                minDate: "today",
                disable: disabledDates,
                dateFormat: "Y-m-d",
                onChange: function(selectedDates, dateStr) {
                    let nextDisabled = disabledDates.find(d => new Date(d) > selectedDates[0]);

                    fpOut = flatpickr("#check_out", {
                        minDate: new Date(selectedDates[0]).fp_incr(1),
                        maxDate: nextDisabled ? new Date(nextDisabled) : null,
                        dateFormat: "Y-m-d",
                        disable: disabledDates
                    });

                    if (checkOutInput.value && dateStr >= checkOutInput.value) {
                         fpOut.setDate(new Date(selectedDates[0]).fp_incr(1), true);
                    }
                }
            });

            fpOut = flatpickr("#check_out", {
                minDate: minDateOut,
                disable: disabledDates,
                dateFormat: "Y-m-d"
            });

            // Re-validate checkout max date on init in case check-in is already filled
            if (checkInInput.value) {
                let checkInD = new Date(checkInInput.value);
                let nextDisabled = disabledDates.find(d => new Date(d) > checkInD);
                if (nextDisabled) {
                    fpOut.set("maxDate", new Date(nextDisabled));
                }
            }
        }

        if (roomSelect) {
            roomSelect.addEventListener('change', () => {
                checkInInput.value = '';
                checkOutInput.value = '';
                initCalendars();
            });
            initCalendars();
        }
    </script>
</body>
</html>