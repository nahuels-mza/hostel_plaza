<?php
$seo = [
    'title'       => 'About Us | Hostel Plaza Mendoza — Our Story',
    'description' => 'Hostel Plaza is a boutique hostel in a historic building in the heart of Mendoza. Learn about our story, our team, and what makes us the best base for exploring Mendoza.',
    'url'         => 'https://hostelplaza.com.ar/about',
    'image'       => 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=',
    'schema'      => [
        '@context'    => 'https://schema.org',
        '@type'       => 'AboutPage',
        'name'        => 'About Hostel Plaza Mendoza',
        'url'         => 'https://hostelplaza.com.ar/about',
        'description' => 'Boutique hostel in a historic building in the heart of Mendoza, Argentina.',
        'publisher'   => [
            '@type'     => 'Organization',
            'name'      => 'Hostel Plaza Mendoza',
            'url'       => 'https://hostelplaza.com.ar',
            'telephone' => '+5492615372767',
            'email'     => 'reservas@hostelplaza.com.ar',
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
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }
        .hero-gradient {
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.2));
        }
        .fade-in {
            animation: fadeIn 1s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* --- UNIQUE MOVING PHOTOS ANIMATION --- */
        @keyframes scroll-left {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        @keyframes scroll-right {
            0% { transform: translateX(-50%); }
            100% { transform: translateX(0); }
        }
        .marquee-wrapper {
            display: flex;
            width: 200%; 
        }
        .marquee-left {
            animation: scroll-left 40s linear infinite;
        }
        .marquee-right {
            animation: scroll-right 40s linear infinite;
        }
        .marquee-wrapper:hover {
            animation-play-state: paused;
        }
        .marquee-item {
            width: 350px;
            height: 450px;
            flex-shrink: 0;
            border-radius: 1.5rem;
            margin: 0 1rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }
        .marquee-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        .marquee-item:hover img {
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
        .glass .lang-toggle-container { background-color: rgba(0, 0, 0, 0.05); border-color: rgba(0, 0, 0, 0.1); }
        .glass .lang-btn { color: #64748b; }
        .glass .lang-btn.active { background-color: #1c5457; color: #fff; }

        /* Dynamic Menu Hovers */
        #mainNav.bg-transparent .nav-link:hover { color: #5eead4; }
        #mainNav.glass .nav-link:hover { color: #1c5457; }
    </style>
</head>
<body class="bg-[#FDFBF7] font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <div id="google_translate_element"></div>

    <nav id="mainNav" class="fixed top-0 w-full z-50 transition-all duration-300 bg-transparent py-5">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
            <a href="/" class="transition-opacity hover:opacity-80 block">
                <img id="logoTop" src="hostel.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="block">
                <img id="logoScrolled" src="H.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="hidden">
            </a>
            
            <div id="desktopMenu" class="hidden md:flex items-center space-x-6 font-medium text-white transition-colors">
                <a href="/" class="nav-link transition-colors">Home</a>
                <a href="about" id="aboutDesktopLink" onclick="cleanURL(event, 'about')" class="text-teal-300 font-bold transition-colors border-b-2 border-teal-300 pb-1">About Us</a>
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
            <a href="/" class="text-left text-lg font-medium block hover:text-teal">Home</a>
            <a href="/#about" onclick="cleanURL(event, 'about')" class="text-left text-lg font-bold text-teal block">About Us</a>
            <a href="rooms" class="text-left text-lg font-medium block hover:text-teal">Rooms</a>
            <a href="tourist-events" class="text-left text-lg font-medium block hover:text-teal">Tourist Events</a>
            <a href="history" class="text-left text-lg font-medium block hover:text-teal">History</a>
            
            <div class="notranslate flex items-center justify-center bg-slate-100 rounded-full p-1 border border-slate-200 text-xs font-bold tracking-wider mt-4">
                <button class="lang-btn-mob flex-1 active bg-teal text-white px-3 py-2 rounded-full transition-all" onclick="changeLanguage('en', this, true)">EN</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('es', this, true)">ES</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('pt', this, true)">PT</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('fr', this, true)">FR</button>
                <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all" onclick="changeLanguage('de', this, true)">DE</button>
            </div>

            <a href="book" class="bg-teal-400 text-slate-900 hover:bg-teal-300 transition-all w-full py-3 rounded-xl font-bold text-center block mt-2">Book Now</a>
        </div>
    </nav>

    <section class="relative h-[60vh] min-h-[450px] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="/hero-img?p=about" alt="Hostel Plaza Front" class="w-full h-full object-cover">
            <div class="absolute inset-0 hero-gradient"></div>
        </div>

        <div class="relative z-10 text-center px-6 max-w-4xl fade-in mt-16">
            <span class="text-teal-300 font-bold tracking-widest uppercase text-sm mb-4 block drop-shadow-md">More than a hostel</span>
            <h1 class="text-5xl md:text-7xl text-white mb-6 leading-tight font-serif drop-shadow-lg">
                Discover the magic of a <br><span class="italic text-teal-300">historic house.</span>
            </h1>
        </div>
    </section>

    <section class="py-24 px-6 max-w-4xl mx-auto w-full">
        <div class="space-y-10 text-lg md:text-xl text-slate-700 leading-relaxed font-light">
            <p>
                Our hostel began as an exciting project: to breathe new life into a beautiful heritage house in the heart of Mendoza. Every corner has a story to tell, and we’re constantly transforming this space to make it even more special. We invite all our guests to be part of this journey, experiencing the ongoing improvements to the house and leaving their own mark.
            </p>

            <p>
                Behind this project are us: three friends passionate about tourism, travel, and sharing our culture. With years of experience in hostels, hospitality, and adventures around the world, we’ve created this space to offer you much more than just a place to sleep. 
            </p>
            
            <div class="bg-white p-8 md:p-12 rounded-[2rem] border border-slate-100 shadow-xl my-12 relative overflow-hidden">
                <i data-lucide="sparkles" class="w-24 h-24 text-teal/5 absolute -top-4 -right-4"></i>
                <p class="text-2xl md:text-3xl font-serif text-slate-900 leading-snug relative z-10">
                    "Enjoy a vibrant social atmosphere, daily events full of food, drinks, and laughter, and the best tips to explore Mendoza like a local."
                </p>
            </div>

            <p>
                This is not just a hostel; it’s a home where connections, stories, and experiences come together to make your journey unforgettable. <strong class="text-teal font-serif text-2xl ml-2">Come and live the transformation with us!</strong>
            </p>
        </div>
    </section>

    <section class="py-20 bg-slate-900 overflow-hidden relative">
        <div class="text-center mb-16 px-6 relative z-10">
            <h2 class="text-4xl md:text-5xl font-serif text-white mb-4">Life at Hostel Plaza</h2>
            <p class="text-white/60 text-lg">A glimpse into our spaces, our people, and our vibe.</p>
        </div>

        <div class="relative w-full overflow-hidden mb-8">
            <div class="marquee-wrapper marquee-left">
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/vpkap6lznc68qeuwhxb6.jpg" alt="Hostel Plaza Mendoza — colonial courtyard and common area"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/h86dbtrmqn2wcyzoqa4a.jpg" alt="Hostel Plaza Mendoza — shared lounge and social space"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/ljouglftlorl2ctxdrrm.jpg" alt="Hostel Plaza Mendoza — guest bedroom interior"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/wd6ibppmjuawq400wesh.jpg" alt="Hostel Plaza Mendoza — kitchen and dining area"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/epjzxjrgtsltlug1ayzs.jpg" alt="Hostel Plaza Mendoza — patio and outdoor seating"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/vpkap6lznc68qeuwhxb6.jpg" alt="Hostel Plaza Mendoza — colonial courtyard and common area"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/h86dbtrmqn2wcyzoqa4a.jpg" alt="Hostel Plaza Mendoza — shared lounge and social space"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/ljouglftlorl2ctxdrrm.jpg" alt="Hostel Plaza Mendoza — guest bedroom interior"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/wd6ibppmjuawq400wesh.jpg" alt="Hostel Plaza Mendoza — kitchen and dining area"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/epjzxjrgtsltlug1ayzs.jpg" alt="Hostel Plaza Mendoza — patio and outdoor seating"></div>
            </div>
        </div>

        <div class="relative w-full overflow-hidden">
            <div class="marquee-wrapper marquee-right">
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/yteclttkdzbmeeg1wm3z.jpg" alt="Hostel Plaza Mendoza — rooftop or terrace area"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/kee5luywhspvy47ezcxy.jpg" alt="Hostel Plaza Mendoza — dormitory bunk beds with lockers"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/iwuku4ivnru1tdctzg2w.jpg" alt="Hostel Plaza Mendoza — reception and entrance hall"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/qahgejvhql6hydpotjob.jpg" alt="Hostel Plaza Mendoza — private room with natural light"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/fw84qo7amtxdt3x44jfd.jpg" alt="Hostel Plaza Mendoza — heritage building facade in Mendoza"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/yteclttkdzbmeeg1wm3z.jpg" alt="Hostel Plaza Mendoza — rooftop or terrace area"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/kee5luywhspvy47ezcxy.jpg" alt="Hostel Plaza Mendoza — dormitory bunk beds with lockers"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/iwuku4ivnru1tdctzg2w.jpg" alt="Gallery 8"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/qahgejvhql6hydpotjob.jpg" alt="Gallery 9"></div>
                <div class="marquee-item"><img src="https://a.hwstatic.com/image/upload/f_auto,q_auto,w_1024,c_limit,e_sharpen,e_improve,e_vibrance:60/propertyimages/3/326554/fw84qo7amtxdt3x44jfd.jpg" alt="Gallery 10"></div>
            </div>
        </div>
    </section>

    <?php include 'footer.php'; ?>

    <div id="cookie-banner" class="fixed bottom-0 left-0 right-0 md:bottom-6 md:left-6 md:right-auto md:max-w-sm bg-white rounded-t-3xl md:rounded-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.1)] md:shadow-2xl border border-slate-100 z-[100] transform translate-y-full transition-transform duration-500 flex flex-col hidden">
        <div class="p-6">
            <div class="flex items-start gap-4 mb-5">
                <div class="w-10 h-10 bg-teal/10 rounded-full flex items-center justify-center flex-shrink-0 text-teal">
                    <i data-lucide="cookie" class="w-5 h-5"></i>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900 text-lg font-serif mb-1">We value your privacy</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">
                        We use cookies to enhance your browsing experience and analyze our traffic. <a href="privacy" class="text-teal hover:underline font-bold">Read more</a>.
                    </p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <button onclick="acceptCookies('essential')" class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">Essential Only</button>
                <button onclick="acceptCookies('all')" class="flex-1 px-4 py-3 bg-teal text-white rounded-xl text-sm font-bold hover:bg-teal-hover transition-colors shadow-md">Accept All</button>
            </div>
        </div>
    </div>

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

        // --- URL CLEANER LOGIC ---
        function cleanURL(e, id) {
            if (window.location.pathname === '/' || window.location.pathname === '/index') {
                e.preventDefault();
                const element = document.getElementById(id);
                if (element) {
                    window.scrollTo({
                        top: element.offsetTop - 80, 
                        behavior: 'smooth'
                    });
                    history.pushState("", document.title, window.location.pathname + window.location.search);
                }
            }
        }

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

        // --- UI SCRIPTS ---
        const nav = document.getElementById('mainNav');
        const menuBtn = document.getElementById('mobileMenuBtn');
        const desktopMenu = document.getElementById('desktopMenu');
        const logoTop = document.getElementById('logoTop');
        const logoScrolled = document.getElementById('logoScrolled');
        const aboutDesktopLink = document.getElementById('aboutDesktopLink');

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
                if(aboutDesktopLink) {
                    aboutDesktopLink.classList.remove('text-teal-300', 'border-teal-300');
                    aboutDesktopLink.classList.add('text-teal', 'border-teal');
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
                if(aboutDesktopLink) {
                    aboutDesktopLink.classList.add('text-teal-300', 'border-teal-300');
                    aboutDesktopLink.classList.remove('text-teal', 'border-teal');
                }
                
                // Keep translator text clean on top
                document.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
                    el.style.color = 'white'; 
                });
            }
        });

        document.getElementById('mobileMenuBtn').addEventListener('click', () => {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
            menu.classList.toggle('flex');
        });

        // --- COOKIE BANNER LOGIC ---
        document.addEventListener('DOMContentLoaded', function() {
            const cookieBanner = document.getElementById('cookie-banner');
            if (!localStorage.getItem('hostel_plaza_cookies')) {
                cookieBanner.classList.remove('hidden');
                setTimeout(() => {
                    cookieBanner.classList.remove('translate-y-full');
                }, 800); 
            }
            // Auto-clean hash on load
            if (window.location.hash) {
                setTimeout(function() {
                    history.pushState("", document.title, window.location.pathname + window.location.search);
                }, 100);
            }
        });

        function acceptCookies(type) {
            const cookieBanner = document.getElementById('cookie-banner');
            localStorage.setItem('hostel_plaza_cookies', type);
            cookieBanner.classList.add('translate-y-full');
            setTimeout(() => {
                cookieBanner.classList.add('hidden');
            }, 500); 
        }
    </script>
</body>
</html>