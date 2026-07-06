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

        /* CSS de nav/lang/google translate vive en header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <?php $hasHero = true; include __DIR__ . '/header.php'; ?>

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
    </script>

</body>
</html>