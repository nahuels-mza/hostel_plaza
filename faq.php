<?php
$seo = [
    'title'       => 'FAQ | Hostel Plaza Mendoza — Everything You Need to Know',
    'description' => 'Frequently asked questions about Hostel Plaza Mendoza: check-in times, amenities, location, breakfast, booking, cancellation policy and more.',
    'url'         => 'https://hostelplaza.com.ar/faq',
    'image'       => 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=',
];

// Cargar FAQ desde JSON (misma fuente que usa el bot de WhatsApp)
$faqPath = __DIR__ . '/hostel_faq.json';
$faqData = is_file($faqPath) ? json_decode(file_get_contents($faqPath), true) : ['categories' => []];
$faqCategories = $faqData['categories'] ?? [];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '_seo.php'; ?>

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

        /* CSS de nav/lang/google translate vive en header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col selection:bg-teal selection:text-white">

    <?php $hasHero = false; include __DIR__ . '/header.php'; ?>

    <div class="bg-[#0f172a] pt-20 pb-24 px-6 text-center border-b border-white/5">
        <h4 class="text-teal-400 font-bold tracking-widest uppercase text-xs mb-4 flex items-center justify-center gap-2"><i data-lucide="help-circle" class="w-4 h-4"></i> Support & Information</h4>
        <h1 class="text-5xl md:text-6xl font-serif font-bold text-white mb-6 tracking-tight leading-tight">Frequently Asked Questions</h1>
        <p class="text-lg text-slate-400 font-medium max-w-2xl mx-auto">Everything you need to know about your stay at Hostel Plaza Mendoza.</p>
    </div>

    <main class="flex-1 py-20 px-6 sm:px-8 lg:px-12 max-w-7xl mx-auto w-full">
        <div class="space-y-24">

            <?php foreach ($faqCategories as $cat): ?>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-16">
                    <div class="lg:col-span-4">
                        <div class="sticky top-10">
                            <div class="w-14 h-14 bg-teal/10 text-teal rounded-2xl flex items-center justify-center mb-6 shadow-inner">
                                <i data-lucide="<?php echo htmlspecialchars($cat['icon'] ?? 'help-circle'); ?>" class="w-7 h-7"></i>
                            </div>
                            <h2 class="text-2xl font-serif font-bold text-slate-900"><?php echo htmlspecialchars($cat['title']); ?></h2>
                            <p class="text-sm text-slate-500 mt-3 pr-4"><?php echo htmlspecialchars($cat['description'] ?? ''); ?></p>
                        </div>
                    </div>
                    <div class="lg:col-span-8 space-y-4">
                        <?php foreach ($cat['items'] as $item): ?>
                            <div class="faq-item bg-white rounded-2xl border border-slate-200/60 shadow-sm transition-all hover:shadow-md cursor-pointer" onclick="toggleFaq(this)">
                                <div class="w-full px-8 py-6 flex justify-between items-center select-none">
                                    <span class="font-bold text-slate-800 pr-4 text-lg"><?php echo htmlspecialchars($item['q']); ?></span>
                                    <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center flex-shrink-0 border border-slate-100">
                                        <i data-lucide="chevron-down" class="w-5 h-5 text-teal faq-icon transition-transform duration-300"></i>
                                    </div>
                                </div>
                                <div class="faq-answer px-8 border-t border-slate-100 text-slate-600 leading-relaxed pt-0 pb-0 text-base">
                                    <?php echo nl2br(htmlspecialchars($item['a'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

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

    </script>

</body>
</html>