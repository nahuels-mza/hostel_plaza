<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cookie Policy | Hostel Plaza</title>
    
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

        /* --- GOOGLE TRANSLATE KILLER --- */
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
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('es', this)">ES</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('pt', this)">PT</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('fr', this)">FR</button>
                    <button class="lang-btn px-3 py-1.5 rounded-full" onclick="changeLanguage('de', this)">DE</button>
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

    <header class="py-32 bg-slate-900 text-center relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-6 relative z-10 fade-in mt-12">
            <span class="text-teal-300 font-bold tracking-widest uppercase text-sm mb-4 block drop-shadow-md">Transparency</span>
            <h1 class="text-5xl md:text-6xl font-serif font-bold text-white mb-6 drop-shadow-lg">Cookie Policy</h1>
            <p class="text-white/80 text-lg max-w-2xl mx-auto font-light leading-relaxed">
                We believe in being clear and open about how we collect and use data related to you. This policy provides detailed information about how and when we use cookies.
            </p>
        </div>
        <div class="absolute inset-0 opacity-10 pointer-events-none z-0">
            <svg class="w-full h-full" fill="currentColor"><rect width="100%" height="100%" fill="url(#grid)" /></svg>
            <defs><pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse"><path d="M 40 0 L 0 0 0 40" fill="none" stroke="white" stroke-width="1"/></pattern></defs>
        </div>
    </header>

    <main class="flex-1 max-w-4xl mx-auto px-8 md:px-16 py-16 bg-white rounded-3xl shadow-xl -mt-16 relative z-30 mb-24 w-[90%] md:w-full">
        <div class="prose prose-slate max-w-none space-y-12 text-slate-700 leading-relaxed">
            
            <section>
                <h2 class="text-3xl font-serif font-bold text-slate-900 mb-6 flex items-center gap-3">
                    <i data-lucide="info" class="text-teal"></i> What are cookies?
                </h2>
                <p>
                    Cookies are small text files sent by us to your computer or mobile device. They are unique to your account or your browser. They help our website recognize you and remember important information that will make your use of the site more convenient (for example, by remembering your preferred language).
                </p>
            </section>

            <section>
                <h2 class="text-3xl font-serif font-bold text-slate-900 mb-6">How we use them</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
                        <h4 class="font-bold text-slate-900 mb-2 flex items-center gap-2 text-teal">
                            <i data-lucide="shield-check" class="w-5 h-5"></i> Essential Cookies
                        </h4>
                        <p class="text-sm text-slate-500">Necessary for the website to function correctly. These allow you to browse our rooms, check availability, and use secure areas of the site.</p>
                    </div>
                    <div class="bg-white p-8 rounded-3xl border border-slate-100 shadow-sm">
                        <h4 class="font-bold text-slate-900 mb-2 flex items-center gap-2 text-teal">
                            <i data-lucide="bar-chart-3" class="w-5 h-5"></i> Analytics Cookies
                        </h4>
                        <p class="text-sm text-slate-500">Help us understand how visitors interact with the site, discover errors, and provide a better overall user experience.</p>
                    </div>
                </div>
            </section>

            <section class="bg-teal/5 p-8 md:p-12 rounded-[2rem] border border-teal/10">
                <h2 class="text-2xl font-serif font-bold text-slate-900 mb-4">Managing your preferences</h2>
                <p class="mb-8">
                    You have the right to decide whether to accept or reject cookies. You can exercise your cookie preferences by clicking the button below to reset your choice and see the cookie banner again.
                </p>
                <button onclick="resetCookieConsent()" class="bg-teal text-white px-8 py-4 rounded-xl font-bold hover:bg-teal-hover transition-all shadow-md inline-flex items-center gap-2">
                    <i data-lucide="refresh-cw" class="w-5 h-5"></i> Reset Cookie Settings
                </button>
            </section>

        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        lucide.createIcons();

        function resetCookieConsent() {
            localStorage.removeItem('hostel_plaza_cookies');
            window.location.reload();
        }

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