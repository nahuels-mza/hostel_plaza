<?php
// --- PULL LIVE WEEKLY EVENTS (LINKUP) FROM ADMIN DATABASE ---
$eventsFile = 'events.json';
$adminEvents = [];

if (file_exists($eventsFile)) {
    $jsonContent = file_get_contents($eventsFile);
    $decoded = json_decode($jsonContent, true);
    if (is_array($decoded) && !empty($decoded)) {
        $adminEvents = $decoded;
    }
}


// --- DYNAMIC CHECKBOX SCHEDULE LOOP (14 DAYS) ---
$events = [];
$daysToShowWindow = 14;

for ($dayOffset = 0; $dayOffset < $daysToShowWindow; $dayOffset++) {
    $targetDate = strtotime("+$dayOffset days");
    $dayStr = date('d', $targetDate);
    $monthStr = strtoupper(date('M', $targetDate));
    $dayOfWeek = date('D', $targetDate);

    foreach ($adminEvents as $adminEvent) {
        $eventDays = $adminEvent['days'] ?? ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];

        if (in_array($dayOfWeek, $eventDays)) {
            $displayEvent = $adminEvent;
            $displayEvent['day'] = $dayStr;
            $displayEvent['month'] = $monthStr;
            $events[] = $displayEvent;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events | Hostel Plaza</title>

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
        /* CSS de nav/lang/google translate vive en header.php */
        .hero-gradient {
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.4));
        }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        /* --- FIXED EVENT HOVER LOGIC --- */
        .event-card {
            position: relative;
            overflow: hidden;
        }
        .event-card .hover-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #ffffff;
            transform: translateY(100%);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 30;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 2rem;
            text-align: center;
        }
        .event-card:hover .hover-overlay {
            transform: translateY(0);
        }
        .event-card .default-content {
            transition: opacity 0.3s ease-in-out, transform 0.4s ease-in-out;
        }
        .event-card:hover .default-content {
            opacity: 0;
            transform: translateY(20px);
        }
    </style>
</head>
<body class="bg-slate-900 font-sans text-white min-h-screen flex flex-col antialiased">

    <?php $hasHero = true; include __DIR__ . '/header.php'; ?>

    <section class="relative h-[60vh] min-h-[450px] flex items-center justify-center overflow-hidden">
        <div class="absolute inset-0 z-0">
            <img src="https://images.unsplash.com/photo-1596394516093-501ba68a0ba6?q=80&w=2000&auto=format&fit=crop" alt="Paragliding in Mendoza" class="w-full h-full object-cover">
            <div class="absolute inset-0 hero-gradient"></div>
        </div>

        <div class="relative z-10 text-center px-6 max-w-4xl mt-16">
            <span class="text-teal-300 font-bold tracking-widest uppercase text-sm mb-4 block drop-shadow-md">Connect & Explore</span>
            <h1 class="text-5xl md:text-7xl text-white mb-6 leading-tight font-serif drop-shadow-lg">Linkup Events</h1>
            <p class="text-xl text-white/90 max-w-2xl mx-auto font-light">Get ready for unforgettable parties, tasting sessions, and local culture over your dates.</p>
        </div>
    </section>

    <main class="flex-1 w-full bg-slate-900">
        <section class="py-20 overflow-hidden relative">
            <div class="max-w-7xl mx-auto px-6">

                <div class="flex justify-end mb-6">
                    <div class="hidden md:flex items-center gap-3">
                        <button onclick="scrollCarousel(-1)" class="w-12 h-12 rounded-full border border-white/20 flex items-center justify-center hover:bg-teal hover:border-teal transition-all text-white">
                            <i data-lucide="chevron-left" class="w-6 h-6"></i>
                        </button>
                        <button onclick="scrollCarousel(1)" class="w-12 h-12 rounded-full border border-white/20 flex items-center justify-center hover:bg-teal hover:border-teal transition-all text-white">
                            <i data-lucide="chevron-right" class="w-6 h-6"></i>
                        </button>
                    </div>
                </div>

                <?php if(empty($events)): ?>
                    <div class="text-center py-24 border border-dashed border-white/20 rounded-3xl">
                        <i data-lucide="calendar-x" class="w-16 h-16 text-white/30 mx-auto mb-4"></i>
                        <h3 class="text-2xl font-serif text-white/70">No upcoming events right now.</h3>
                        <p class="text-white/50 mt-2">Check back soon for the latest schedule!</p>
                    </div>
                <?php else: ?>
                    <div class="relative -mx-6 px-6">
                        <div id="eventsCarousel" class="flex overflow-x-auto snap-x snap-mandatory gap-6 pb-12 pt-4 hide-scrollbar scroll-smooth">
                            <?php foreach ($events as $event): ?>

                            <div class="w-[85vw] md:w-[calc(50%-1rem)] lg:w-[calc(33.333%-1rem)] flex-none snap-start">

                                <div class="event-card w-full h-[500px] md:h-[550px] rounded-[1.5rem] shadow-2xl border border-white/10 cursor-pointer bg-slate-800">

                                    <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>" class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 hover:scale-105 z-0">
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-900/40 to-slate-900/10 z-10 pointer-events-none"></div>

                                    <div class="absolute top-4 left-4 bg-[#2a5d5a]/95 backdrop-blur-sm px-3 py-2 rounded-xl text-center shadow-lg z-20 min-w-[3.5rem]">
                                        <span class="block text-[9px] font-bold tracking-widest text-white uppercase mb-0.5"><?php echo htmlspecialchars($event['month']); ?></span>
                                        <span class="block text-xl font-bold text-white leading-none"><?php echo htmlspecialchars($event['day']); ?></span>
                                    </div>

                                    <div class="absolute top-4 right-4 bg-white px-3 py-1.5 rounded-full shadow-lg z-20">
                                        <span class="text-[10px] font-bold text-slate-900 tracking-wider uppercase">
                                            <?php echo htmlspecialchars($event['price'] ?? 'Free'); ?>
                                        </span>
                                    </div>

                                    <div class="default-content absolute inset-x-0 bottom-0 p-8 z-20 pointer-events-none">
                                        <h3 class="text-3xl font-serif leading-tight text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                                            <?php echo html_entity_decode(htmlspecialchars($event['title'])); ?>
                                        </h3>
                                    </div>

                                    <div class="hover-overlay">
                                        <div class="overflow-y-auto hide-scrollbar flex flex-col items-center justify-center h-full w-full">
                                            <div class="px-2">
                                                <h3 class="text-3xl font-serif font-bold leading-tight text-slate-900 mb-4">
                                                    <?php echo html_entity_decode(htmlspecialchars($event['title'])); ?>
                                                </h3>
                                                <p class="text-slate-700 font-medium text-sm leading-relaxed mb-6">
                                                    <?php echo html_entity_decode(htmlspecialchars($event['season'])); ?>
                                                </p>
                                                <?php
                                                    $waMsg = urlencode('Hola! Me interesa la actividad "' . $event['title'] . '". ¿Pueden darme más información y disponibilidad?');
                                                ?>
                                                <a href="https://api.whatsapp.com/send/?phone=5492615372767&text=<?php echo $waMsg; ?>"
                                                   target="_blank" rel="noopener"
                                                   class="inline-flex items-center justify-center gap-2 bg-[#25D366] hover:bg-[#1ebe5d] text-white font-bold text-sm px-5 py-3 rounded-xl transition-colors shadow-md">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                                                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.136.564 4.14 1.543 5.878L.057 23.515a.75.75 0 0 0 .928.928l5.637-1.486A11.945 11.945 0 0 0 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75a9.715 9.715 0 0 1-4.964-1.364l-.356-.213-3.684.97.986-3.598-.233-.37A9.716 9.716 0 0 1 2.25 12C2.25 6.615 6.615 2.25 12 2.25S21.75 6.615 21.75 12 17.385 21.75 12 21.75z"/>
                                                    </svg>
                                                    Ask on WhatsApp
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Nav (google-translate, scroll, lang toggle, mobile menu) vive en header.php
        lucide.createIcons();

        // --- CAROUSEL JS ---
        function scrollCarousel(direction) {
            const carousel = document.getElementById('eventsCarousel');
            if (!carousel) return;
            const scrollAmount = carousel.clientWidth > 768 ? carousel.clientWidth / 3 : carousel.clientWidth;
            carousel.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
        }
    </script>
</body>
</html>