<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Tour | Hostel Plaza Mendoza</title>
    
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
        .glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .hero-gradient {
            background: linear-gradient(to bottom, rgba(28, 84, 87, 0.9), rgba(15, 23, 42, 0.7));
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
            <a href="tours" class="text-left text-lg font-medium block hover:text-teal">Tours & Activities</a>
            <a href="tourist-events" class="text-left text-lg font-medium block hover:text-teal">Tourist Events</a>
            
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

    <header class="relative pt-40 pb-24 lg:pt-48 lg:pb-32 bg-slate-900 overflow-hidden">
        <div class="absolute inset-0 z-0 opacity-40">
            <img src="https://images.unsplash.com/photo-1555939594-58d7cb561ad1?q=80&w=2000&auto=format&fit=crop" class="w-full h-full object-cover blur-sm scale-105">
            <div class="absolute inset-0 hero-gradient"></div>
        </div>
        <div class="relative z-10 max-w-7xl mx-auto px-6 text-center fade-in">
            <span class="text-teal-300 font-bold tracking-widest uppercase text-sm mb-4 block drop-shadow-md">Step Inside</span>
            <h1 class="text-5xl md:text-7xl text-white font-serif mb-6 drop-shadow-lg">Hostel Plaza Virtual Tour</h1>
            <p class="text-xl text-slate-200 max-w-2xl mx-auto font-light leading-relaxed">
                Take a look around your future home in Mendoza. Discover our spaces before you even pack your bags.
            </p>
        </div>
    </header>

    <section class="py-16 px-6 bg-slate-50 -mt-10 relative z-20">
        <div class="max-w-6xl mx-auto">
            <div class="bg-white p-2 md:p-4 rounded-[2rem] shadow-2xl border border-slate-200" style="animation: fadeIn 1.5s ease-in-out;">
                <div class="relative w-full aspect-video rounded-3xl overflow-hidden bg-black shadow-inner">
                    <video 
                        controls 
                        autoplay 
                        loop 
                        muted 
                        playsinline 
                        class="absolute top-0 left-0 w-full h-full object-cover transition-opacity duration-700"
                        preload="auto">
                        <source src="final.mp4" type="video/mp4">
                        <source src="/final.mp4" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </section>

    <section class="py-24 px-6 max-w-7xl mx-auto w-full bg-white">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <div class="space-y-8">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-teal/10 text-teal mb-2">
                    <i data-lucide="map-pin" class="w-6 h-6"></i>
                </div>
                <h2 class="text-4xl md:text-5xl leading-tight font-serif text-slate-900">The Perfect Basecamp for Your Mendoza Adventure.</h2>
                <p class="text-slate-600 text-lg leading-relaxed">
                    Nestled right in the vibrant heart of the city, Hostel Plaza puts the very best of Mendoza at your fingertips. Step outside and stroll through our famous tree-lined streets (the historic <em>acequias</em>) to reach the stunning <strong>Plaza Independencia</strong>, or take a short, scenic walk to the breathtaking gates of <strong>Parque General San Martín</strong>.
                </p>
                <p class="text-slate-600 text-lg leading-relaxed">
                    Need to stock up before heading into the Andes? You are surrounded by convenience. Major supermarkets, local bakeries, ATMs, and world-class cafes are all just around the corner from our front door.
                </p>
            </div>
            
            <div class="bg-slate-50 rounded-3xl p-8 md:p-12 border border-slate-100 shadow-lg relative">
                <div class="absolute -top-6 -right-6 text-teal opacity-10">
                    <i data-lucide="quote" class="w-32 h-32"></i>
                </div>
                <div class="relative z-10 space-y-6">
                    <h3 class="text-2xl font-serif font-bold text-slate-900">More than a hostel, a community.</h3>
                    <p class="text-slate-600 leading-relaxed text-lg italic">
                        "At Hostel Plaza, you aren't just booking a bed; you're joining a family. Our staff takes immense pride in creating a warm, incredibly friendly environment."
                    </p>
                    <div class="space-y-4 pt-6 border-t border-slate-200">
                        <div class="flex items-center gap-4">
                            <i data-lucide="heart" class="w-5 h-5 text-rose-500 shrink-0"></i>
                            <span class="text-slate-700 font-medium">Incredibly friendly, multilingual staff ready to help 24/7.</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <i data-lucide="flame" class="w-5 h-5 text-orange-500 shrink-0"></i>
                            <span class="text-slate-700 font-medium">Legendary weekly Argentine Asados to connect with travelers.</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <i data-lucide="grape" class="w-5 h-5 text-malbec shrink-0"></i>
                            <span class="text-slate-700 font-medium">Expert advice on local wine tours and mountain excursions.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-teal py-20 border-t border-teal-hover">
        <div class="max-w-4xl mx-auto px-6 text-center space-y-8">
            <h2 class="text-4xl font-serif text-white mb-4 drop-shadow-md">Ready to experience it in person?</h2>
            <p class="text-teal-100 text-lg mb-8">Secure your bed today and get ready for an unforgettable stay in Argentina's wine capital.</p>
            <a href="book.php" class="inline-flex items-center justify-center bg-white text-teal px-10 py-4 rounded-xl font-bold text-lg hover:bg-slate-100 hover:scale-105 transition-all shadow-xl">
                Check Availability
            </a>
        </div>
    </section>

    <?php 
    if(file_exists('footer.php')) {
        include 'footer.php'; 
    }
    ?>

    <script>
        // Initialize Lucide Icons
        lucide.createIcons();

        // Nav Scroll Effect
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('mainNav');
            const logoTop = document.getElementById('logoTop');
            const logoScrolled = document.getElementById('logoScrolled');
            
            if (window.scrollY > 50) {
                nav.classList.add('glass', 'py-3'); 
                nav.classList.remove('bg-transparent', 'py-5');
                logoTop.classList.add('hidden'); 
                logoScrolled.classList.remove('hidden');
                
                document.querySelectorAll('.nav-link').forEach(el => {
                    el.classList.remove('text-white');
                    el.classList.add('text-slate-900');
                });
                document.getElementById('mobileMenuBtn').classList.remove('text-white');
                document.getElementById('mobileMenuBtn').classList.add('text-slate-900');
                
                // Keep translator text clean on scroll
                document.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
                    el.style.color = '#64748b'; 
                });
            } else {
                nav.classList.add('bg-transparent', 'py-5'); 
                nav.classList.remove('glass', 'py-3');
                logoTop.classList.remove('hidden'); 
                logoScrolled.classList.add('hidden');
                
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

        // Mobile Menu Toggle
        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden'); 
            menu.classList.toggle('flex');
        });

        // Language Translation Script
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

        // Kills the annoying Google Translate top bar & padding
        setInterval(function() {
            if (document.body.style.top !== '0px') { document.body.style.top = '0px'; }
            if (document.documentElement.style.top !== '0px') { document.documentElement.style.top = '0px'; }
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