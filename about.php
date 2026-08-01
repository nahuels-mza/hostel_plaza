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
            'telephone' => '+5492612592729',
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

        /* CSS de nav/lang/google translate vive en header.php */
    </style>
</head>
<body class="bg-[#FDFBF7] font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <?php $hasHero = true; include __DIR__ . '/header.php'; ?>

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

    <script>
        lucide.createIcons();

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