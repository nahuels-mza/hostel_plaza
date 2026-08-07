<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Virtual Tour | Hostel Plaza Mendoza</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    
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

        /* CSS de nav/lang/google translate vive en header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <?php $hasHero = true; include __DIR__ . '/header.php'; ?>

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
    </script>

</body>
</html>