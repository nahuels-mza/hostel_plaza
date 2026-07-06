<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our History | Hostel Plaza</title>
    
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

        /* CSS de nav/lang/google translate vive en header.php */

        .image-reveal {
            position: relative;
            overflow: hidden;
        }
        .image-reveal img {
            transition: transform 1.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .image-reveal:hover img {
            transform: scale(1.05);
        }

    </style>
</head>
<body class="bg-[#FDFBF7] font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <?php $hasHero = true; include __DIR__ . '/header.php'; ?>

    <section class="relative h-[65vh] min-h-[500px] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="breath.jpg" alt="Vintage Architecture Mendoza" class="w-full h-full object-cover">
            <div class="absolute inset-0 hero-gradient"></div>
        </div>

        <div class="relative z-10 text-center px-6 max-w-4xl fade-in mt-16">
            <span class="text-teal-300 font-bold tracking-widest uppercase text-sm mb-4 block drop-shadow-md">Our Story</span>
            <h1 class="text-5xl md:text-7xl text-white mb-6 leading-tight font-serif drop-shadow-lg">
                Breathing new life into <br><span class="italic text-teal-300">living history.</span>
            </h1>
        </div>
    </section>

    <section class="py-20 px-6 max-w-4xl mx-auto text-center relative">
        <i data-lucide="quote" class="w-16 h-16 text-slate-200 absolute -top-8 left-1/2 transform -translate-x-1/2 z-0"></i>
        <p class="text-2xl md:text-3xl font-serif text-slate-800 leading-relaxed relative z-10">
            Located in the vibrant heart of the city, Hostel Plaza (often referred to as Hostel Plaza Mendoza) is less a corporate entity and more a piece of living history within the city's "New City" (Ciudad Nueva) district.
        </p>
        <div class="w-24 h-1 bg-teal mx-auto mt-10 rounded-full"></div>
    </section>

    <main class="w-full pb-24">
        
        <section class="py-16 px-6 max-w-7xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="order-2 lg:order-1 image-reveal rounded-[2rem] shadow-2xl h-[500px] w-full">
                    <img src="https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=" alt="Heritage House Courtyard" class="w-full h-full object-cover">
                </div>
                <div class="order-1 lg:order-2 space-y-6">
                    <div class="w-14 h-14 bg-teal/10 rounded-2xl flex items-center justify-center text-teal mb-6">
                        <i data-lucide="landmark" class="w-7 h-7"></i>
                    </div>
                    <h2 class="text-4xl font-serif text-slate-900 leading-tight">The Building: A Heritage House</h2>
                    <p class="text-slate-600 text-lg leading-relaxed">
                        While the hostel itself is a modern hospitality business, its history is deeply tied to the heritage architecture of Mendoza and the post-earthquake urban design of the late 19th century. The hostel is housed in a traditional heritage house that dates back to the early 20th century. Like many buildings in this area, it reflects the architectural transition Mendoza underwent after the devastating earthquake of 1861.
                    </p>
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm mt-6">
                        <h4 class="font-bold text-slate-900 mb-2 flex items-center gap-2"><i data-lucide="home" class="w-5 h-5 text-teal"></i> Architecture & Preservation</h4>
                        <p class="text-slate-600 text-sm leading-relaxed">
                            The building features high ceilings, thick walls, and the classic "inner courtyard" (patio) layout typical of aristocratic Mendoza homes of that era. The current owners, a group of friends and travel enthusiasts, took over the property with the specific goal of "breathing new life" into the historic structure while preserving its original character.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="py-16 px-6 max-w-7xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="space-y-6">
                    <div class="w-14 h-14 bg-teal/10 rounded-2xl flex items-center justify-center text-teal mb-6">
                        <i data-lucide="map" class="w-7 h-7"></i>
                    </div>
                    <h2 class="text-4xl font-serif text-slate-900 leading-tight">The "Plaza" Connection</h2>
                    <p class="text-slate-600 text-lg leading-relaxed">
                        The hostel takes its name from its proximity to Plaza Independencia, which is just a two-minute walk away. Designed in 1863 by engineer Julio Balloffet, it was intended as the central "safe zone" of the city, surrounded by four smaller plazas (España, Chile, Italia, and San Martín) to provide open refuge in case of future tremors.
                    </p>
                    <p class="text-slate-600 text-lg leading-relaxed">
                        By staying at Hostel Plaza, travelers are essentially residing in what has been the social and political epicenter of Mendoza for over 160 years.
                    </p>
                </div>
                <div class="image-reveal rounded-[2rem] shadow-2xl h-[500px] w-full">
                    <img src="https://i.ibb.co/qFh23pVM/image.jpg" alt="Vintage Hotel Plaza Mendoza" class="w-full h-full object-cover">
                </div>
            </div>
        </section>

        <section class="py-16 px-6 max-w-7xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                <div class="order-2 lg:order-1 image-reveal rounded-[2rem] shadow-2xl h-[500px] w-full">
                    <img src="https://images.unsplash.com/photo-1506377247377-2a5b3b417ebb?q=80&w=1000&auto=format&fit=crop" alt="Wine Tasting in Mendoza" class="w-full h-full object-cover">
                </div>
                <div class="order-1 lg:order-2 space-y-6">
                    <div class="w-14 h-14 bg-teal/10 rounded-2xl flex items-center justify-center text-teal mb-6">
                        <i data-lucide="wine" class="w-7 h-7"></i>
                    </div>
                    <h2 class="text-4xl font-serif text-slate-900 leading-tight">Evolution of Hospitality</h2>
                    <p class="text-slate-600 text-lg leading-relaxed">
                        The history of the hostel is part of a broader trend in Mendoza that began in the early 2000s. Following the Argentine economic crisis of 2002, Mendoza saw a massive surge in international tourism. This led to the conversion of many historic family homes into "Boutique Hostels" and Bed & Breakfasts.
                    </p>
                    <p class="text-slate-600 text-lg leading-relaxed">
                        Today, Hostel Plaza functions as a social hub. It has moved away from the "pension" (boarding house) style of the past to a community-focused model, offering communal dinners, wine tastings, and cultural exchanges that mirror the welcoming spirit of the Italian and Spanish immigrants who originally built the neighborhood.
                    </p>
                </div>
            </div>
        </section>

        <section class="py-10 px-6 max-w-5xl mx-auto">
            <div class="bg-teal text-white rounded-3xl p-8 md:p-12 shadow-2xl relative overflow-hidden">
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
                        <path fill="currentColor" d="M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z"/>
                    </svg>
                </div>
                
                <div class="relative z-10 flex flex-col md:flex-row items-center gap-8">
                    <div class="w-20 h-20 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="search" class="w-10 h-10 text-white"></i>
                    </div>
                    <div>
                        <h4 class="text-2xl font-serif font-bold mb-2">Historical "Easter Eggs"</h4>
                        <p class="text-white/80 text-lg leading-relaxed">
                            Because it is a heritage building, you can still see the original floor tiles and woodwork perfectly preserved in several of our common areas—small details left behind for the history buffs staying with us.
                        </p>
                    </div>
                </div>
            </div>
        </section>

    </main>

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
                        We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies. <a href="privacy.php" class="text-teal hover:underline font-bold">Read more</a>.
                    </p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <button onclick="acceptCookies('essential')" class="flex-1 px-4 py-3 border border-slate-200 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-50 transition-colors">
                    Essential Only
                </button>
                <button onclick="acceptCookies('all')" class="flex-1 px-4 py-3 bg-teal text-white rounded-xl text-sm font-bold hover:bg-teal-hover transition-colors shadow-md">
                    Accept All
                </button>
            </div>
        </div>
    </div>

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

        // --- COOKIE BANNER LOGIC ---
        document.addEventListener('DOMContentLoaded', function() {
            const cookieBanner = document.getElementById('cookie-banner');
            
            if (!localStorage.getItem('hostel_plaza_cookies')) {
                cookieBanner.classList.remove('hidden');
                setTimeout(() => {
                    cookieBanner.classList.remove('translate-y-full');
                }, 800); 
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