<?php
require_once __DIR__ . '/_areas.php';

$areas = hp_load_areas(__DIR__ . '/areas.json');

$requestedSlug = $_GET['name'] ?? '';
$currentArea = null;
foreach ($areas as $a) {
    if ($a['slug'] === $requestedSlug) {
        $currentArea = $a;
        break;
    }
}

// Área inexistente → volver al listado en el home
if (!$currentArea) {
    header('Location: /#areas');
    exit;
}

$otherAreas = array_values(array_filter($areas, fn($a) => $a['slug'] !== $currentArea['slug']));

// Galería: si hay una sola foto (o ninguna) la sección no se muestra
$gallery = hp_area_gallery($currentArea, __DIR__ . '/cache');
$galleryCount = count($gallery);
if ($galleryCount === 5 || $galleryCount === 8) {
    $galleryLayout = 'featured'; // 1 foto grande + el resto, en 3 columnas
} elseif ($galleryCount % 3 === 0) {
    $galleryLayout = 'three';
} else {
    $galleryLayout = 'two';
}

$heroSrc = $currentArea['image'] !== '' ? hp_area_photo_src($currentArea['image'], 2000) : '';

// --- SEO ---
$seo = [
    'title'       => $currentArea['title'] . ' | Hostel Plaza Mendoza',
    'description' => mb_strimwidth(strip_tags($currentArea['text']), 0, 155, '…'),
    'url'         => 'https://hostelplaza.com.ar/area/' . $currentArea['slug'],
    'image'       => $heroSrc ?: 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=',
];

?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '_seo.php'; ?>

    <base href="/">

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
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* CSS de nav/lang/google translate vive en componentes/header.php */
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased overflow-x-hidden">

    <?php $hasHero = true; include __DIR__ . '/componentes/header.php'; ?>

    <!-- Hero: mismo tamaño que room.php (página de detalle) -->
    <div class="w-full h-[50vh] md:h-[65vh] relative bg-slate-900">
        <?php if ($heroSrc): ?>
        <img src="<?php echo htmlspecialchars($heroSrc); ?>" alt="<?php echo htmlspecialchars($currentArea['title']); ?>" class="w-full h-full object-cover opacity-80">
        <?php endif; ?>
        <div class="absolute inset-0 bg-gradient-to-t from-slate-900 via-slate-900/40 to-transparent"></div>

        <div class="absolute bottom-0 left-0 w-full p-8 md:p-12 pb-16 max-w-7xl mx-auto">
            <span class="text-teal-300 font-bold uppercase tracking-widest text-xs mb-4 block">Common Area</span>
            <h1 class="text-4xl md:text-6xl font-serif font-bold text-white"><?php echo htmlspecialchars($currentArea['title']); ?></h1>
        </div>
    </div>

    <main class="flex-1 w-full bg-[#FDFBF7] py-16">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-1 lg:grid-cols-3 gap-12 lg:gap-20">

            <div class="lg:col-span-2 space-y-14">

                <section>
                    <h2 class="text-[26px] font-serif font-bold text-[#0f172a] mb-6">About this space</h2>
                    <p class="text-lg text-[#475569] leading-relaxed">
                        <?php echo nl2br(htmlspecialchars($currentArea['text'])); ?>
                    </p>
                    <?php if ($currentArea['long_text'] !== ''): ?>
                    <p class="text-lg text-[#475569] leading-relaxed mt-4">
                        <?php echo nl2br(htmlspecialchars($currentArea['long_text'])); ?>
                    </p>
                    <?php endif; ?>
                </section>

                <?php if ($galleryCount > 1): ?>
                <hr class="border-slate-200">

                <section>
                    <h2 class="text-[26px] font-serif font-bold text-[#0f172a] mb-8">Photo Gallery</h2>
                    <div class="grid gap-4 <?php echo $galleryLayout === 'two' ? 'grid-cols-2' : 'grid-cols-2 md:grid-cols-3'; ?>">
                        <?php foreach ($gallery as $i => $photoUrl):
                            $isFeatured = ($galleryLayout === 'featured' && $i === 0);
                        ?>
                        <div class="<?php echo $isFeatured ? 'col-span-2 h-56 md:h-80' : 'h-40 md:h-64'; ?> overflow-hidden rounded-2xl shadow-md bg-slate-100">
                            <img src="<?php echo htmlspecialchars(hp_area_photo_src($photoUrl, 1000)); ?>"
                                 alt="<?php echo htmlspecialchars($currentArea['title']); ?> photo <?php echo $i + 1; ?>"
                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-500"
                                 loading="lazy">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="sticky top-32 bg-white rounded-3xl p-8 shadow-xl border border-slate-100">
                    <h3 class="text-xl font-serif font-bold text-slate-900 mb-3">Free for all our guests</h3>
                    <p class="text-[15px] text-[#475569] leading-relaxed mb-8">
                        Every common area is included in your stay, whichever room you book.
                    </p>
                    <a href="rooms" onclick="gtag('event','ver_habitaciones',{location:'area_page',area:<?php echo htmlspecialchars(json_encode($currentArea['slug']), ENT_QUOTES); ?>})" class="block text-center w-full py-3.5 bg-[#1c5457] text-white rounded-xl font-bold text-[17px] hover:bg-[#144042] transition-all shadow-md">
                        See rooms &amp; prices
                    </a>
                </div>
            </div>

        </div>
    </main>

    <?php if ($otherAreas): ?>
    <section class="w-full bg-white border-t border-slate-100 py-16">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-[26px] font-serif font-bold text-[#0f172a] mb-8">Other spaces</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php foreach ($otherAreas as $o): ?>
                <a href="area/<?php echo htmlspecialchars(rawurlencode($o['slug'])); ?>" onclick="gtag('event','area_click',{location:'area_related',area:<?php echo htmlspecialchars(json_encode($o['slug']), ENT_QUOTES); ?>})" class="group block bg-slate-50 rounded-2xl overflow-hidden border border-slate-100 hover:shadow-lg transition-all">
                    <div class="h-32 md:h-40 overflow-hidden bg-slate-200">
                        <?php if ($o['image'] !== ''): ?>
                        <img src="<?php echo htmlspecialchars(hp_area_photo_src($o['image'], 600)); ?>" alt="<?php echo htmlspecialchars($o['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy">
                        <?php endif; ?>
                    </div>
                    <div class="px-4 py-3 font-serif font-bold text-lg text-slate-900"><?php echo htmlspecialchars($o['title']); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php include __DIR__ . '/componentes/footer.php'; ?>

    <script>
        // Nav (google-translate, scroll, lang toggle, mobile menu) vive en componentes/header.php
        lucide.createIcons();
    </script>
</body>
</html>
