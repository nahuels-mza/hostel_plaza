<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms & Conditions | Hostel Plaza</title>
    
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .hero-gradient {
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.4));
        }
        .fade-in {
            animation: fadeIn 1s ease-in forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- GOOGLE TRANSLATE KILLER CSS --- */
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
        
        .lang-btn { transition: all 0.3s ease; }
        .lang-btn.active { background-color: rgba(255, 255, 255, 0.2); color: #fff; }
        .glass .lang-toggle-container { background-color: rgba(0, 0, 0, 0.05); border-color: rgba(0, 0, 0, 0.1); }
        .glass .lang-btn { color: #64748b; }
        .glass .lang-btn.active { background-color: #1c5457; color: #fff; }
        
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
            
            <div id="desktopMenu" class="hidden md:flex items-center space-x-8 font-medium text-white transition-colors">
                <a href="/" class="nav-link transition-colors">Home</a>
                <a href="about" class="nav-link transition-colors">About Us</a>
                <a href="rooms" class="nav-link transition-colors">Rooms</a>
                <a href="tourist-events" class="nav-link transition-colors">Tourist Events</a>
                
                <div class="notranslate lang-toggle-container flex items-center bg-white/10 backdrop-blur-sm rounded-full p-1 border border-white/20 text-[11px] font-bold tracking-wider ml-2 transition-all">
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

        <div id="mobileMenu" class="hidden absolute top-full left-0 w-full glass p-6 flex-col space-y-4 shadow-xl text-slate-900">
            <a href="/" class="text-left text-lg font-medium block hover:text-teal">Home</a>
            <a href="about" class="text-left text-lg font-medium block hover:text-teal">About Us</a>
            <a href="rooms" class="text-left text-lg font-medium block hover:text-teal">Rooms</a>
            <a href="tourist-events" class="text-left text-lg font-medium block hover:text-teal">Tourist Events</a>
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

    <section class="relative h-[40vh] min-h-[350px] flex items-center justify-center">
        <div class="absolute inset-0 z-0 overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat bg-fixed" style="background-image: url('https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=');"></div>
            <div class="absolute inset-0 hero-gradient"></div>
        </div>

        <div class="relative z-10 text-center px-6 max-w-4xl fade-in mt-12">
            <h1 class="text-4xl md:text-6xl text-white mb-4 leading-tight font-serif font-bold">
                Terms & <span class="italic text-teal-300">Conditions</span>
            </h1>
            <p class="text-lg md:text-xl text-white/80 font-light">
                Please review our policies to ensure a perfect stay.
            </p>
        </div>
    </section>

    <main class="flex-1 max-w-4xl mx-auto px-8 md:px-16 py-16 bg-white rounded-3xl shadow-xl -mt-16 relative z-30 mb-24 w-[90%] md:w-full">
        
        <div class="prose prose-slate max-w-none">
            <p class="text-sm text-slate-500 uppercase tracking-widest font-bold mb-8">Last Updated: <?php echo date("F Y"); ?></p>

            <p class="text-slate-600 leading-relaxed mb-8">
                Welcome to Hostel Plaza. By booking a stay with us, you agree to comply with and be bound by the following terms and conditions. Please read them carefully to ensure you have a wonderful and seamless experience in Mendoza.
            </p>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">1. Booking and Reservations</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li>All bookings will need to be confirmed.</li>
                <li>Guests must be at least 18 years old to book a shared dormitory. Minors are only permitted in private rooms when accompanied by a parent or legal guardian.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">2. Check-In & Check-Out</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li><strong>Check-in:</strong> Begins at 14:00 (2:00 PM).</li>
                <li><strong>Check-out:</strong> Strictly by 10:00 AM.</li>
                <li>Early check-in and late check-out are subject to availability and may incur additional charges. If you arrive early, you are welcome to store your luggage in our secure room free of charge.</li>
                <li>A valid government-issued photo ID (passport or National ID) is required upon check-in. And if Argentinian, a DNI card.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">3. Payment & Cancellations</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li>Payment for the balance of your stay is due upon check-in. We accept cash (ARS), credit/debit cards, and bank transfers.</li>
                <li><strong>Standard Cancellation:</strong> Cancellations must be made at least 48 hours prior to your scheduled arrival date.</li>
                <li>Non-refundable rates (if selected at booking) cannot be modified or refunded under any circumstances.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">4. House Rules</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li><strong>Quiet Hours:</strong> We ask all guests to respect quiet hours between 23:00 (11:00 PM) and 07:00 AM to ensure everyone gets a good night's rest.</li>
                <li><strong>Smoking:</strong> Hostel Plaza is a strictly non-smoking indoor facility. Smoking is only permitted in designated outdoor areas (such as the courtyard).</li>
                <li><strong>Outside Guests:</strong> For security reasons, non-registered guests are not permitted in the dormitories or private rooms. They may visit common areas during daytime hours only, provided they sign in at reception.</li>
                <li><strong>Kitchen Use:</strong> Guests are welcome to use the shared kitchen. We ask that you clean your dishes, pots, and pans immediately after use and clearly label any food placed in the communal fridge.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">5. Liability & Security</h2>
            <ul class="list-disc pl-5 text-slate-600 space-y-2 mb-8 leading-relaxed">
                <li>We provide free security lockers in all shared dormitories. Guests are responsible for providing their own padlocks (or purchasing one at reception).</li>
                <li>Hostel Plaza is not liable for the loss, theft, or damage to personal belongings, luggage, or valuables left unattended in common areas, rooms, or lockers.</li>
                <li>Any damage to hostel property (including linens, furniture, and fixtures) caused by a guest will be charged to the guest's registered credit card.</li>
            </ul>

            <h2 class="text-2xl font-serif font-bold text-slate-900 mt-10 mb-4 border-b border-slate-100 pb-2">6. Right of Admission</h2>
            <p class="text-slate-600 leading-relaxed mb-8">
                We reserve the right to refuse admission or end a stay prematurely, without refund, if a guest behaves in a manner that compromises the safety, comfort, or well-being of other guests or our staff.
            </p>

            <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 mt-12">
                <h3 class="font-bold text-slate-900 mb-2">Questions about these terms?</h3>
                <p class="text-slate-600 text-sm mb-0">If you have any questions or require clarification before booking, please do not hesitate to contact our team at <a href="mailto:info@hostelplaza.com" class="text-teal font-medium hover:underline">info@hostelplaza.com</a>.</p>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        lucide.createIcons();

        // Handle Scroll Effect for Navbar
        const nav = document.getElementById('mainNav');
        const menuBtn = document.getElementById('mobileMenuBtn');
        const desktopMenu = document.getElementById('desktopMenu');
        const logoTop = document.getElementById('logoTop');
        const logoScrolled = document.getElementById('logoScrolled');

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
                
                // Keep translator text clean on top
                document.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
                    el.style.color = 'white'; 
                });
            }
        });

        // Mobile menu
        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
            menu.classList.toggle('flex');
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
                includedLanguages: 'en,es,pt,fr,de', 
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

</body>
</html>