<?php
/**
 * Header / nav compartido de Hostel Plaza.
 *
 * Uso:
 *   <?php
 *     $hasHero = true;  // página con imagen de hero → nav transparente arriba
 *     include 'header.php';
 *   ?>
 *
 * `$hasHero = true`  → arranca transparente + texto blanco, pasa a "glass" al scrollear.
 * `$hasHero = false` → arranca "glass" + texto oscuro desde el primer pixel
 *                       (para páginas sin hero, como /book, /rooms).
 *
 * Incluye:
 *   - CSS del nav, lang-toggle, y "matadores" de la barra de Google Translate
 *   - el HTML del nav (desktop + mobile) y mobile menu
 *   - JS de scroll-to-glass, toggle mobile, language switcher
 *   - el script de Google Translate
 *
 * Requisitos en la página que lo incluye:
 *   - Tailwind cargado (cdn.tailwindcss.com)
 *   - Lucide cargado y `lucide.createIcons()` invocado en el script principal
 *     (este header crea elementos `<i data-lucide>` que necesitan ese render)
 */

$hasHero = isset($hasHero) ? (bool)$hasHero : false;
?>

<style>
    /* Lang toggle */
    .lang-btn { transition: all 0.3s ease; }
    .lang-btn.active {
        background-color: rgba(255, 255, 255, 0.2);
        color: #fff;
    }
    .glass .lang-toggle-container {
        background-color: rgba(0, 0, 0, 0.05);
        border-color: rgba(0, 0, 0, 0.1);
    }
    .glass .lang-btn { color: #64748b; }
    .glass .lang-btn.active {
        background-color: #1c5457;
        color: #fff;
    }
    /* Default lang toggle (no glass) */
    #mainNav:not(.glass) .lang-toggle-container {
        background-color: rgba(255, 255, 255, 0.10);
        border-color: rgba(255, 255, 255, 0.20);
    }

    /* Nav link hovers */
    #mainNav.bg-transparent .nav-link:hover { color: #5eead4; }
    #mainNav.glass .nav-link:hover         { color: #1c5457; }

    /* Glass effect */
    .glass {
        background-color: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    /* ===== Google Translate killer (la barra arriba + tooltips) ===== */
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
    body { top: 0px !important; position: static !important; }
    html { height: auto !important; top: 0px !important; }
    html.translated-ltr, html.translated-rtl {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .goog-text-highlight {
        background-color: transparent !important;
        box-shadow: none !important;
    }
</style>

<div id="google_translate_element"></div>

<nav id="mainNav"
     data-has-hero="<?php echo $hasHero ? '1' : '0'; ?>"
     class="fixed top-0 w-full z-50 transition-all duration-300 <?php echo $hasHero ? 'bg-transparent py-5' : 'glass py-3'; ?>">
    <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">

        <a href="/" class="transition-opacity hover:opacity-80 block">
            <img id="logoTop"      src="hostel.png" alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="<?php echo $hasHero ? 'block' : 'hidden'; ?>">
            <img id="logoScrolled" src="H.png"      alt="Hostel Plaza Logo" style="height: 70px; width: auto; object-fit: contain;" class="<?php echo $hasHero ? 'hidden' : 'block'; ?>">
        </a>

        <div id="desktopMenu" class="hidden md:flex items-center space-x-6 font-medium <?php echo $hasHero ? 'text-white' : 'text-slate-900'; ?> transition-colors">
            <a href="/"               class="nav-link notranslate transition-colors" data-nav-home>Home</a>
            <a href="about"           class="nav-link transition-colors">About Us</a>
            <a href="rooms"           class="nav-link transition-colors">Rooms</a>
            <a href="tourist-events"  class="nav-link transition-colors">Tourist Events</a>

            <div class="notranslate lang-toggle-container flex items-center backdrop-blur-sm rounded-full p-1 border text-[11px] font-bold tracking-wider ml-2 transition-all">
                <button class="lang-btn active px-3 py-1.5 rounded-full" onclick="changeLanguage('en', this)">EN</button>
                <button class="lang-btn px-3 py-1.5 rounded-full"        onclick="changeLanguage('es', this)">ES</button>
                <button class="lang-btn px-3 py-1.5 rounded-full"        onclick="changeLanguage('pt', this)">PT</button>
                <button class="lang-btn px-3 py-1.5 rounded-full"        onclick="changeLanguage('fr', this)">FR</button>
                <button class="lang-btn px-3 py-1.5 rounded-full"        onclick="changeLanguage('de', this)">DE</button>
            </div>

            <a href="book.php" class="bg-[#1c5457] text-white font-bold px-6 py-2.5 rounded-full hover:bg-[#144042] transition-all shadow-lg ml-2 border-none">
                Book Now
            </a>
        </div>

        <button id="mobileMenuBtn" class="md:hidden p-2 <?php echo $hasHero ? 'text-white' : 'text-slate-900'; ?> transition-colors">
            <i data-lucide="menu"></i>
        </button>
    </div>

    <div id="mobileMenu" class="hidden absolute top-full left-0 w-full glass p-6 flex-col space-y-4 shadow-xl text-slate-900">
        <a href="index.php"          class="notranslate text-left text-lg font-medium block hover:text-teal" data-nav-home>Home</a>
        <a href="about"              class="text-left text-lg font-medium block hover:text-teal">About Us</a>
        <a href="rooms.php"          class="text-left text-lg font-medium block hover:text-teal">Rooms</a>
        <a href="tourist-events.php" class="text-left text-lg font-medium block hover:text-teal">Tourist Events</a>
        <a href="history.php"        class="text-left text-lg font-medium block hover:text-teal">History</a>

        <div class="notranslate flex items-center justify-center bg-slate-100 rounded-full p-1 border border-slate-200 text-xs font-bold tracking-wider mt-4">
            <button class="lang-btn-mob flex-1 active bg-teal text-white px-3 py-2 rounded-full transition-all" onclick="changeLanguage('en', this, true)">EN</button>
            <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all"            onclick="changeLanguage('es', this, true)">ES</button>
            <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all"            onclick="changeLanguage('pt', this, true)">PT</button>
            <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all"            onclick="changeLanguage('fr', this, true)">FR</button>
            <button class="lang-btn-mob flex-1 text-slate-500 px-3 py-2 rounded-full transition-all"            onclick="changeLanguage('de', this, true)">DE</button>
        </div>

        <a href="book.php" class="bg-[#1c5457] text-white hover:bg-[#144042] transition-all w-full py-3 rounded-xl font-bold text-center block mt-2">Book Now</a>
    </div>
</nav>

<script>
(function () {
    const nav = document.getElementById('mainNav');
    if (!nav) return;
    const hasHero      = nav.dataset.hasHero === '1';
    const logoTop      = document.getElementById('logoTop');
    const logoScrolled = document.getElementById('logoScrolled');
    const mobileBtn    = document.getElementById('mobileMenuBtn');

    function toGlass() {
        nav.classList.add('glass', 'py-3');
        nav.classList.remove('bg-transparent', 'py-5');
        if (logoTop)      logoTop.classList.add('hidden');
        if (logoScrolled) logoScrolled.classList.remove('hidden');
        nav.querySelectorAll('.nav-link').forEach(el => {
            el.classList.remove('text-white');
            el.classList.add('text-slate-900');
        });
        if (mobileBtn) {
            mobileBtn.classList.remove('text-white');
            mobileBtn.classList.add('text-slate-900');
        }
        nav.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
            el.style.color = '#64748b';
        });
    }

    function toTransparent() {
        nav.classList.add('bg-transparent', 'py-5');
        nav.classList.remove('glass', 'py-3');
        if (logoTop)      logoTop.classList.remove('hidden');
        if (logoScrolled) logoScrolled.classList.add('hidden');
        nav.querySelectorAll('.nav-link').forEach(el => {
            el.classList.add('text-white');
            el.classList.remove('text-slate-900');
        });
        if (mobileBtn) {
            mobileBtn.classList.add('text-white');
            mobileBtn.classList.remove('text-slate-900');
        }
        nav.querySelectorAll('.lang-toggle-container .lang-btn:not(.active)').forEach(el => {
            el.style.color = 'white';
        });
    }

    if (!hasHero) {
        // Páginas sin hero: nav siempre en estado "glass"
        toGlass();
    } else {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) toGlass();
            else toTransparent();
        }, { passive: true });
    }

    // Mobile menu toggle
    if (mobileBtn) {
        mobileBtn.addEventListener('click', () => {
            const menu = document.getElementById('mobileMenu');
            if (menu) {
                menu.classList.toggle('hidden');
                menu.classList.toggle('flex');
            }
        });
    }
})();

// Dispara la traducción de Google Translate y VERIFICA que realmente se haya
// aplicado (mirando la clase que Google le agrega a <html>), reintentando si
// no. Antes solo chequeábamos que el <select> existiera y disparábamos una
// vez — pero el <select> puede existir en el DOM antes de que el widget
// termine de enganchar su propio listener interno, y el evento sintético se
// perdía en silencio (pasaba sobre todo en el "español por default" de la
// primera carga, nunca en un click manual porque para ese entonces el widget
// ya llevaba rato inicializado).
function hpApplyTranslation(lang, attemptsLeft) {
    if (attemptsLeft === undefined) attemptsLeft = 15;
    var sel = document.querySelector('#google_translate_element select')
           || document.querySelector('.goog-te-combo');
    if (!sel) {
        if (attemptsLeft > 0) setTimeout(function () { hpApplyTranslation(lang, attemptsLeft - 1); }, 300);
        return;
    }
    sel.value = lang;
    sel.dispatchEvent(new Event('change'));
    setTimeout(function () {
        var isTranslated = document.documentElement.classList.contains('translated-ltr')
                         || document.documentElement.classList.contains('translated-rtl');
        var applied = (lang === 'en') ? !isTranslated : isTranslated;
        if (!applied && attemptsLeft > 0) {
            hpApplyTranslation(lang, attemptsLeft - 1);
        }
    }, 500);
}

// Google Translate, al traducir palabras sueltas sin contexto de frase,
// produce traducciones literales que no tienen sentido en la UI:
//   "Home"      (link de navegación) → "Hogar" (la casa)   en vez de "Inicio"
//   "Check In"  (label del form)     → "Registrarse"       en vez de "Check-in" / fecha de llegada
//   "Check Out" (label del form)     → "Verificar"         en vez de "Check-out" / fecha de salida
// Para estos casos puntuales se traduce a mano (marcados con [data-hp-i18n]
// + "notranslate" en el HTML) en vez de dejarlo en manos de Google.
var HP_I18N_LABELS = {
    navHome:  { en: 'Home',      es: 'Inicio',       pt: 'Início',     fr: 'Accueil',       de: 'Startseite' },
    checkIn:  { en: 'Check In',  es: 'Check-in',     pt: 'Check-in',   fr: 'Arrivée',       de: 'Anreise' },
    checkOut: { en: 'Check Out', es: 'Check-out',    pt: 'Check-out',  fr: 'Départ',        de: 'Abreise' },
};
function hpApplyI18nLabels(langCode) {
    Object.keys(HP_I18N_LABELS).forEach(function (key) {
        var dict  = HP_I18N_LABELS[key];
        var label = dict[langCode] || dict.en;
        document.querySelectorAll('[data-hp-i18n="' + key + '"]').forEach(function (el) {
            el.textContent = label;
        });
    });
    // Retrocompat: el link de "Home" del nav usaba data-nav-home antes de
    // sumarse al diccionario genérico de arriba.
    var homeLabel = HP_I18N_LABELS.navHome[langCode] || HP_I18N_LABELS.navHome.en;
    document.querySelectorAll('[data-nav-home]').forEach(function (el) {
        el.textContent = homeLabel;
    });
}

function changeLanguage(langCode, btnElement, isMobile) {
    isMobile = isMobile || false;
    hpApplyTranslation(langCode);
    hpApplyI18nLabels(langCode);
    var btnClass = isMobile ? '.lang-btn-mob' : '.lang-btn';
    document.querySelectorAll(btnClass).forEach(function (btn) {
        if (isMobile) {
            btn.classList.remove('bg-teal', 'text-white');
            btn.classList.add('text-slate-500');
        } else {
            btn.classList.remove('active');
        }
    });
    if (isMobile) {
        btnElement.classList.add('bg-teal', 'text-white');
        btnElement.classList.remove('text-slate-500');
    } else {
        btnElement.classList.add('active');
    }
    // Se guarda para siempre (no solo esta sesión) — si el usuario ya eligió
    // un idioma, no le volvemos a preguntar en la próxima visita.
    try { localStorage.setItem('hp_lang', langCode); } catch(e) {}
    hpSetLangCookie(langCode);
}

// Cookie espejo de localStorage: localStorage no es visible desde PHP, y
// algunas páginas (ej. la confirmación de reserva) necesitan saber en qué
// idioma está el sitio para elegir su propio copy en vez de dejarlo en manos
// de Google Translate (que se corta con "notranslate" en contenido dinámico
// como el código de reserva).
function hpSetLangCookie(langCode) {
    try {
        document.cookie = 'hp_lang=' + langCode + ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
    } catch (e) {}
}

// On load: español por default SOLO si el usuario nunca eligió nada antes.
// Si ya eligió un idioma alguna vez, se respeta esa elección para siempre.
(function () {
    var lang = '';

    try { lang = localStorage.getItem('hp_lang') || ''; } catch(e) {}

    if (!lang) {
        lang = 'es';
        try { localStorage.setItem('hp_lang', lang); } catch(e) {}
    }
    hpSetLangCookie(lang);
    hpApplyI18nLabels(lang);

    // OJO: no cortamos acá aunque lang === 'en'. Google Translate guarda su
    // propia cookie y puede re-traducir la página al inglés-base por su
    // cuenta en el próximo load (ej: si el usuario había elegido inglés
    // antes) — hpApplyTranslation('en') fuerza a deshacerlo si hace falta;
    // si la página ya está en inglés, dispatchear 'en' de nuevo no hace nada.

    // Actualizar botones (desktop + mobile)
    document.querySelectorAll('.lang-btn').forEach(function (btn) { btn.classList.remove('active'); });
    var d = document.querySelector('.lang-btn[onclick*="\'' + lang + '\'"]');
    if (d) d.classList.add('active');
    document.querySelectorAll('.lang-btn-mob').forEach(function (btn) {
        btn.classList.remove('bg-teal', 'text-white');
        btn.classList.add('text-slate-500');
    });
    var m = document.querySelector('.lang-btn-mob[onclick*="\'' + lang + '\'"]');
    if (m) { m.classList.remove('text-slate-500'); m.classList.add('bg-teal', 'text-white'); }

    hpApplyTranslation(lang);
})();

// Kill the Google Translate top bar (it tries to shift the document down)
setInterval(function () {
    if (document.body.style.top !== '0px') document.body.style.top = '0px';
    if (document.documentElement.style.top !== '0px') document.documentElement.style.top = '0px';
}, 50);
</script>

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
