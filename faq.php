<?php
// faq.php
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frequently Asked Questions | Hostel Plaza</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { teal: { DEFAULT: '#1c5457', hover: '#144042', light: '#e8f0f0' } },
                    fontFamily: { serif: ['"Playfair Display"', 'serif'], sans: ['"Inter"', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        /* Smooth Accordion Transitions */
        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease-in-out, opacity 0.3s ease-in-out, padding 0.3s ease-in-out;
            opacity: 0;
        }
        .faq-item.active .faq-answer {
            max-height: 500px;
            opacity: 1;
            padding-top: 1.25rem;
            padding-bottom: 1.5rem;
        }
        .faq-item.active .faq-icon {
            transform: rotate(180deg);
        }
        .faq-item.active {
            border-color: #1c5457;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        /* Hide Default Google Translate Widget */
        #google_translate_element, .goog-te-banner-frame, .skiptranslate, .goog-tooltip, #goog-gt-tt { display: none !important; opacity: 0 !important; visibility: hidden !important; }
        body { top: 0px !important; position: static !important; }
        html { height: auto !important; top: 0px !important; }
        html.translated-ltr, html.translated-rtl { margin-top: 0 !important; padding-top: 0 !important; }
        .goog-text-highlight { background-color: transparent !important; box-shadow: none !important; }
        .lang-btn { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col selection:bg-teal selection:text-white">

    <div id="google_translate_element"></div>

    <header class="w-full bg-[#0f172a] border-b border-white/10 relative z-50">
        <div class="max-w-7xl mx-auto px-6 h-24 flex items-center justify-between">
            
            <a href="/" class="flex items-center text-white shrink-0">
                <img src="hostel.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="block">
            </a>
            
            <div class="hidden lg:flex items-center gap-10">
                
                <nav class="flex items-center gap-8 text-[13px] font-bold text-white uppercase tracking-[0.15em]">
                    <a href="/" class="hover:text-teal-400 transition-colors">Home</a>
                    <a href="about" class="hover:text-teal-400 transition-colors">About Us</a>
                    <a href="rooms" class="hover:text-teal-400 transition-colors">Rooms</a>
                    <a href="events" class="hover:text-teal-400 transition-colors">Events</a>
                </nav>
                
                <div class="flex items-center gap-6 pl-4">
                    <div class="flex bg-white/5 p-1 rounded-full border border-white/10">
                        <button class="lang-btn bg-white/20 text-white rounded-full px-3 py-1.5 text-xs font-bold transition-all" onclick="changeLanguage('en', this)">EN</button>
                        <button class="lang-btn text-white/70 hover:text-white rounded-full px-3 py-1.5 text-xs font-bold transition-all" onclick="changeLanguage('es', this)">ES</button>
                        <button class="lang-btn text-white/70 hover:text-white rounded-full px-3 py-1.5 text-xs font-bold transition-all" onclick="changeLanguage('pt', this)">PT</button>
                        <button class="lang-btn text-white/70 hover:text-white rounded-full px-3 py-1.5 text-xs font-bold transition-all" onclick="changeLanguage('fr', this)">FR</button>
                        <button class="lang-btn text-white/70 hover:text-white rounded-full px-3 py-1.5 text-xs font-bold transition-all" onclick="changeLanguage('de', this)">DE</button>
                    </div>
                    <a href="book" class="bg-teal-400 hover:bg-teal-300 text-slate-900 px-7 py-3 rounded-full text-sm font-bold shadow-lg transition-all">Book Now</a>
                </div>
            </div>

            <button class="lg:hidden text-white p-2">
                <i data-lucide="menu" class="w-7 h-7"></i>
            </button>
        </div>
    </header>

    <div class="bg-[#0f172a] pt-20 pb-24 px-6 text-center border-b border-white/5">
        <h4 class="text-teal-400 font-bold tracking-widest uppercase text-xs mb-4 flex items-center justify-center gap-2"><i data-lucide="help-circle" class="w-4 h-4"></i> Support & Information</h4>
        <h1 class="text-5xl md:text-6xl font-serif font-bold text-white mb-6 tracking-tight leading-tight">Frequently Asked Questions</h1>
        <p class="text-lg text-slate-400 font-medium max-w-2xl mx-auto">Everything you need to know about your stay at Hostel Plaza Mendoza.</p>
    </div>

    <main class="flex-1 py-20 px-6 sm:px-8 lg:px-12 max-w-7xl mx-auto w-full">
        <div class="space-y-24">

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-16">
                <div class="lg:col-span-4">
                    <div class="sticky top-10">
                        <div class="w-14 h-14 bg-teal/10 text-teal rounded-2xl flex items-center justify-center mb-6 shadow-inner">
                            <i data-lucide="calendar-check" class="w-7 h-7"></i>
                        </div>
                        <h2 class="text-2xl font-serif font-bold text-slate-900">Bookings & Check-In</h2>
                        <p class="text-sm text-slate-500 mt-3 pr-4">Details regarding your arrival, departure, and location.</p>
                    </div>
                </div>
                <div class="lg:col-span-8 space-y-4">
                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">What are the check-in and check-out times?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Check-in is from 2:00 PM to 11:30 PM. Check-out is from 7:00 AM to 10:00 AM. Late check-outs may incur additional charges. We also offer free luggage storage for our guests if you arrive early or leave late.
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Where exactly is the hostel located?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            We are located at 1237 Avenida Bartolomé Mitre, Mendoza, Argentina. We are right in the heart of the city, just a 2-minute walk from Independencia Square.
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Do you offer an airport shuttle?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            No, we don’t offer an airport shuttle. However, if you don’t have a way to get to Hostel Plaza, we can arrange a ride for you.
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-16">
                <div class="lg:col-span-4">
                    <div class="sticky top-10">
                        <div class="w-14 h-14 bg-teal/10 text-teal rounded-2xl flex items-center justify-center mb-6 shadow-inner">
                            <i data-lucide="credit-card" class="w-7 h-7"></i>
                        </div>
                        <h2 class="text-2xl font-serif font-bold text-slate-900">Payments & Taxes</h2>
                        <p class="text-sm text-slate-500 mt-3 pr-4">Information on accepted methods, VAT rules, and cancellations.</p>
                    </div>
                </div>
                <div class="lg:col-span-8 space-y-4">
                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">What payment methods do you accept?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Payment can be made in Argentine pesos via cash.
                        </div>
                    </div>

                        

                    
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-16">
                <div class="lg:col-span-4">
                    <div class="sticky top-10">
                        <div class="w-14 h-14 bg-teal/10 text-teal rounded-2xl flex items-center justify-center mb-6 shadow-inner">
                            <i data-lucide="coffee" class="w-7 h-7"></i>
                        </div>
                        <h2 class="text-2xl font-serif font-bold text-slate-900">Facilities & Amenities</h2>
                        <p class="text-sm text-slate-500 mt-3 pr-4">Everything you need for a comfortable stay.</p>
                    </div>
                </div>
                <div class="lg:col-span-8 space-y-4">
                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Is breakfast included?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Yes, we offer a highly-rated complimentary breakfast for all our guests to start the day right.
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Do you have a kitchen and common areas?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Yes! We are located in a beautiful historic house featuring a fully equipped shared kitchen, a lovely courtyard with a parilla (BBQ) space, and a comfortable shared lounge to relax and meet others.
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Is there Wi-Fi available?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Yes, fast and free Wi-Fi is available in all public areas and throughout the entire hostel.
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-16">
                <div class="lg:col-span-4">
                    <div class="sticky top-10">
                        <div class="w-14 h-14 bg-teal/10 text-teal rounded-2xl flex items-center justify-center mb-6 shadow-inner">
                            <i data-lucide="clipboard-list" class="w-7 h-7"></i>
                        </div>
                        <h2 class="text-2xl font-serif font-bold text-slate-900">Rules & Policies</h2>
                        <p class="text-sm text-slate-500 mt-3 pr-4">Important guidelines to ensure everyone has a great time.</p>
                    </div>
                </div>
                <div class="lg:col-span-8 space-y-4">
                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Is there an age restriction?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Yes, to maintain our atmosphere, check-in is only possible for guests between 18 and 45 years old. Guests under the age of 18 can only check in if accompanied by a parent or official guardian in a private room.
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Are pets allowed?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Pets are allowed on request in private rooms, though additional charges may apply. Please contact us before booking to confirm.
                        </div>
                    </div>

                    <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                        <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                            <span class="font-bold text-slate-800 pr-4 text-lg">Does the hostel organize social events?</span>
                            <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100"><i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i></div>
                        </div>
                        <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                            Absolutely! We pride ourselves on our community. We host daily events including communal dinners, wine tours, paragliding, and rafting on the Mendoza River to help you connect with other travelers.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

      <?php include 'footer.php'; ?>

    <script>
        lucide.createIcons();

        function toggleFaq(button) {
            const item = button.closest('.faq-item');
            if (!item) return;

            const allItems = document.querySelectorAll('.faq-item');
            
            if (item.classList.contains('active')) {
                item.classList.remove('active');
                return;
            }

            allItems.forEach(i => {
                i.classList.remove('active');
            });
            item.classList.add('active');
        }

        // Cleaned up language function - completely relies on Google Translate for text replacement
        function changeLanguage(langCode, btnElement) {
            var selectField = document.querySelector("#google_translate_element select") || document.querySelector(".goog-te-combo");
            if(selectField) {
                selectField.value = langCode;
                selectField.dispatchEvent(new Event('change'));
            }

            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.remove('bg-white/20', 'text-white');
                btn.classList.add('text-white/70');
            });
            btnElement.classList.add('bg-white/20', 'text-white');
            btnElement.classList.remove('text-white/70');
            
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

        // Hide Google Translate Top Bar
        setInterval(function() {
            if (document.body.style.top !== '0px') document.body.style.top = '0px';
            if (document.documentElement.style.top !== '0px') document.documentElement.style.top = '0px';
        }, 50);
    </script>
    
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'en', includedLanguages: 'en,es,pt,fr,de', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

</body>
</html>