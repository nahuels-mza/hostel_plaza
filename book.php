<?php
// =============================================================================
// HOSTEL PLAZA · BOOKING WIZARD (3 steps)
// =============================================================================
// step 1 → fechas
// step 2 → elegir habitación (disponibilidad + precio en vivo desde BananaDesk)
// step 3 → datos del huésped + confirmar
// step success → confirmación post-POST
// =============================================================================

// --- 0. Config + rooms ---
$config         = is_file('config.json') ? json_decode(file_get_contents('config.json'), true) : [];
$exchangeRateARS = $config['exchangeRateARS'] ?? 1370; // fallback para total en email si BananaDesk no responde

$roomsFile = 'rooms.json';
$rooms = is_file($roomsFile) ? (json_decode(file_get_contents($roomsFile), true) ?: []) : [];

$mapPath    = __DIR__ . '/room_mapping.json';
$roomMap    = is_file($mapPath) ? (json_decode(file_get_contents($mapPath), true) ?: []) : [];

$countries  = is_file(__DIR__ . '/countries.php') ? require __DIR__ . '/countries.php' : [];

// --- 1. POST handler (mantiene exactamente la lógica vieja) ---
$bookingSuccess   = false;
$newReservationId = '';
$mailError        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $bookingsFile = 'bookings.json';

    $bookings = [];
    if (file_exists($bookingsFile)) {
        $bookings = json_decode(file_get_contents($bookingsFile), true);
        if (!is_array($bookings)) $bookings = [];
    }

    $newReservationId = 'HP-' . date('ym') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));

    // Metadatos de la habitación reservada (capacity, bookingUnit) — se reutiliza
    // más abajo para el lookup de nombre del email y para la sync con BananaDesk.
    $postedRoomId = htmlspecialchars($_POST['room_id'] ?? '');
    $bookedRoomMeta = null;
    foreach ($rooms as $r) {
        if ((string)$r['id'] === $postedRoomId) { $bookedRoomMeta = $r; break; }
    }
    $roomCapacity = (int)($bookedRoomMeta['capacity'] ?? 1);
    // Clamp de defensa: valida contra la capacidad de la habitación, no vuelve a
    // chequear disponibilidad en vivo (eso ya pasó client-side en step 3).
    $validatedGuestsCount = min(max(1, (int)($_POST['guests_count'] ?? 1)), max(1, $roomCapacity));

    $newBooking = [
        "id"          => $newReservationId,
        "roomId"      => $postedRoomId,
        "checkIn"     => htmlspecialchars($_POST['check_in'] ?? ''),
        "checkOut"    => htmlspecialchars($_POST['check_out'] ?? ''),
        "guestsCount" => (string)$validatedGuestsCount,
        "guestName"   => htmlspecialchars($_POST['guest_name'] ?? ''),
        "age"         => "",
        "gender"      => "",
        "nationality" => htmlspecialchars($_POST['nationality'] ?? ''),
        "idType"      => htmlspecialchars($_POST['id_type'] ?? ''),
        "idNumber"    => htmlspecialchars($_POST['id_number'] ?? ''),
        "phone"       => htmlspecialchars($_POST['phone'] ?? ''),
        "email"       => htmlspecialchars($_POST['email'] ?? ''),
        "eta"         => htmlspecialchars($_POST['eta'] ?? ''),
        "notes"       => htmlspecialchars($_POST['notes'] ?? ''),
        "totalPrice"  => (float)($_POST['total_price'] ?? 0),
        "amountPaid"  => 0,
        "source"      => "Website",
        "status"      => "pending",
    ];

    $totalPriceARS = (float)($_POST['total_price_ars'] ?? ($newBooking['totalPrice'] * $exchangeRateARS));
    $formattedARS  = number_format($totalPriceARS, 0, ',', '.');

    array_unshift($bookings, $newBooking);
    file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));

    // Room name for the email (ya resuelto arriba junto con $bookedRoomMeta)
    $bookedRoomName = $bookedRoomMeta['name'] ?? '';

    // Calcular noches para el template de mail
    $mailNights = max(1, (int)round(
        (strtotime($newBooking['checkOut']) - strtotime($newBooking['checkIn'])) / 86400
    ));

    // Un solo mail para todos los huéspedes, sin datos de pago — se paga en el
    // check-in. Idioma según el browser, no según nacionalidad.
    require_once __DIR__ . '/send_mail.php';
    require_once __DIR__ . '/mail_booking.php';
    $guestLang = hp_detect_lang();
    [$mailSubject, $mailBody, $mailAlt] = hp_mail_booking(
        $newBooking, $bookedRoomName, $totalPriceARS, $mailNights, $guestLang
    );
    $mailResult = hp_send_mail(
        $newBooking['email'], $newBooking['guestName'],
        $mailSubject, $mailBody, $mailAlt,
        "booking={$newReservationId}"
    );
    if (!$mailResult['ok']) $mailError = $mailResult['error'];

    // --- Auto-crear reserva en BananaDesk ---
    require_once __DIR__ . '/bananadesk_reserve.php';
    $bdRoomTypeId  = (int)($roomMap[$newBooking['roomId']] ?? 0);
    $bdBookingUnit = $bookedRoomMeta['bookingUnit'] ?? 'room';
    if ($bdRoomTypeId > 0) {
        $bdResult = hp_bananadesk_reserve(
            $newBooking['checkIn'],
            $newBooking['checkOut'],
            $bdRoomTypeId,
            $newBooking['guestName'],
            $newBooking['email'],
            $newBooking['phone'],
            (int)$newBooking['guestsCount'],
            $bdBookingUnit
        );

        // Guardar el resultado en bookings.json para trazabilidad
        $bookingsNow = json_decode(file_get_contents($bookingsFile), true) ?: [];
        foreach ($bookingsNow as &$b) {
            if ($b['id'] === $newReservationId) {
                $b['bananadesk'] = $bdResult['ok']
                    ? ['synced' => true,  'response' => $bdResult['response']]
                    : ['synced' => false, 'error'    => $bdResult['error']];
                break;
            }
        }
        unset($b);
        file_put_contents($bookingsFile, json_encode($bookingsNow, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $bdStatus = $bdResult['ok'] ? 'OK' : 'ERROR';
        $bdLog    = "[" . date('Y-m-d H:i:s') . "] BANANADESK {$bdStatus}\n";
        $bdLog   .= "Booking:   {$newReservationId}\n";
        $bdLog   .= "RoomType:  {$bdRoomTypeId} | Unit: {$bdBookingUnit} | Qty: {$newBooking['guestsCount']}\n";
        if (!$bdResult['ok']) $bdLog .= "Error:     " . ($bdResult['error'] ?? 'unknown error') . "\n";
        $bdLog   .= str_repeat('=', 60) . "\n\n";
        file_put_contents(__DIR__ . '/logs/mail.log', $bdLog, FILE_APPEND);
    }

    $bookingSuccess = true;
}

// --- 2. URL params (and backward-compat with old ?room=NAME) ---
$getCheckIn     = trim((string)($_GET['check_in']  ?? $_GET['checkIn']  ?? ''));
$getCheckOut    = trim((string)($_GET['check_out'] ?? $_GET['checkOut'] ?? ''));
$getRoomId      = trim((string)($_GET['room_id']   ?? ''));
$getRoomName    = trim((string)($_GET['room']      ?? ''));
$getGuestsCount = max(1, (int)($_GET['guests_count'] ?? 1));

// Resolver ?room=NAME (links viejos) → room_id
if ($getRoomName !== '' && $getRoomId === '') {
    $needle = strtolower(str_replace(['+', '%20'], ' ', $getRoomName));
    foreach ($rooms as $r) {
        if (strtolower($r['name']) === $needle) {
            $getRoomId = (string)$r['id'];
            break;
        }
    }
}

// --- 3. Determine current step ---
function valid_date($s) {
    if (!$s) return false;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s;
}

if ($bookingSuccess) {
    $step = 'success';
} elseif ($getRoomId !== '' && valid_date($getCheckIn) && valid_date($getCheckOut) && $getCheckIn < $getCheckOut) {
    $step = 3;
} elseif (valid_date($getCheckIn) && valid_date($getCheckOut) && $getCheckIn < $getCheckOut) {
    $step = 2;
} else {
    $step = 1;
}

// Encontrar la habitación seleccionada para step 3
$selectedRoom = null;
if ($step === 3) {
    foreach ($rooms as $r) {
        if ((string)$r['id'] === $getRoomId) { $selectedRoom = $r; break; }
    }
    if (!$selectedRoom) { $step = 2; } // room_id inválido → volver a step 2
}

$nightsCount = 0;
if (valid_date($getCheckIn) && valid_date($getCheckOut)) {
    $nightsCount = max(1, (int)((strtotime($getCheckOut) - strtotime($getCheckIn)) / 86400));
}

// Datos del huésped que vienen prellenados (en caso de cambiar de habitación con sessionStorage)
function gv($key, $default = '') { return htmlspecialchars((string)($_GET[$key] ?? $default)); }

// --- SEO ---
$seo = [
    'title'       => 'Book Your Stay | Hostel Plaza Mendoza',
    'description' => 'Reserve your room at Hostel Plaza Mendoza. Fast and easy booking — no payment required upfront. Pay at check-in. Private rooms and shared dorms available.',
    'url'         => 'https://hostelplaza.com.ar/book',
    'image'       => 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/633284365.jpg?k=fc4866488d6a9f7bb753b918edac964136059bbde98f4e13f80bb63fae7c1d81&o=',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include '_seo.php'; ?>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans:  ['Inter', 'system-ui', 'sans-serif'],
                        serif: ['"Playfair Display"', 'serif'],
                    },
                    colors: {
                        teal:        '#1c5457',
                        'teal-hover':'#144042',
                        'teal-light':'#e6f0f0',
                        booking:     '#003580',
                    },
                },
            },
        };
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1,h2,h3,h4 { font-family: 'Playfair Display', serif; }
        .glass { background-color: rgba(255,255,255,0.85); backdrop-filter: blur(12px); }
        .step-indicator-dot { transition: all 0.3s ease; }
        .step-bar { transition: background-color 0.4s ease; }
        .room-thumb { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .room-thumb:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -10px rgba(0,0,0,.18); }
        .room-thumb.is-current { border-color: #1c5457; box-shadow: 0 0 0 3px rgba(28,84,87,0.15); }
        /* Hide scrollbar on the other-rooms strip */
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col antialiased">

    <?php $hasHero = false; include __DIR__ . '/header.php'; ?>

    <main class="flex-1 py-12 md:py-16 mt-[80px]">
        <div class="max-w-6xl mx-auto px-6">

            <!-- ========== HEADER + STEP INDICATOR ========== -->
            <div class="text-center mb-10">
                <h1 class="text-4xl md:text-5xl font-bold text-slate-900 mb-3">
                    <?php if ($step === 'success'): ?><?php echo ($guestLang ?? 'en') === 'es' ? '¡Gracias por tu Reserva!' : 'Thank You for Your Reservation!'; ?>
                    <?php elseif ($step === 1):   ?>When are you coming?
                    <?php elseif ($step === 2):   ?>Choose Your Room
                    <?php else:                   ?>Last Step — Your Details
                    <?php endif; ?>
                </h1>
                <p class="text-slate-500 text-lg">
                    <?php if ($step === 'success'): ?><?php echo ($guestLang ?? 'en') === 'es' ? 'Te enviamos un mail con la confirmación y los detalles de tu estadía.' : "We've sent you a confirmation email with all your stay details."; ?>
                    <?php elseif ($step === 1):   ?>Pick your check-in &amp; check-out dates to see live availability.
                    <?php elseif ($step === 2):   ?>Real-time availability for <?php echo htmlspecialchars($getCheckIn); ?> → <?php echo htmlspecialchars($getCheckOut); ?> · <?php echo $nightsCount; ?> night<?php echo $nightsCount > 1 ? 's' : ''; ?>
                    <?php else:                   ?>You're booking <strong class="text-teal"><?php echo htmlspecialchars($selectedRoom['name'] ?? ''); ?></strong>.
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($step !== 'success'): ?>
            <!-- step indicator -->
            <div class="flex items-center justify-center gap-3 mb-12 max-w-md mx-auto">
                <?php
                $steps_meta = [
                    1 => 'Dates',
                    2 => 'Room',
                    3 => 'Confirm',
                ];
                foreach ($steps_meta as $sn => $label):
                    $isCurrent = ($sn === $step);
                    $isDone    = ($sn < $step);
                    $color     = $isCurrent ? 'bg-teal text-white' : ($isDone ? 'bg-teal-light text-teal' : 'bg-slate-200 text-slate-400');
                    $textColor = $isCurrent ? 'text-teal font-bold' : ($isDone ? 'text-slate-700' : 'text-slate-400');
                ?>
                    <div class="flex items-center gap-2">
                        <div class="step-indicator-dot w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold <?php echo $color; ?>">
                            <?php if ($isDone): ?><i data-lucide="check" class="w-4 h-4"></i><?php else: echo $sn; endif; ?>
                        </div>
                        <span class="text-sm <?php echo $textColor; ?> hidden sm:inline"><?php echo $label; ?></span>
                    </div>
                    <?php if ($sn < 3): ?>
                        <div class="step-bar flex-1 h-0.5 <?php echo $isDone ? 'bg-teal-light' : 'bg-slate-200'; ?>"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- ============================ STEP 1: DATES ============================ -->
            <?php if ($step === 1): ?>
                <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 p-8 md:p-10">
                    <form method="GET" action="book.php" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-[1.4fr_1.4fr_0.8fr] gap-6">
                            <?php
                                $sf_variant   = 'card';
                                $sf_check_in  = $getCheckIn;
                                $sf_check_out = $getCheckOut;
                                $sf_guests    = $getGuestsCount;
                                include __DIR__ . '/_search_fields.php';
                            ?>
                        </div>

                        <?php /* room_id NO se preserva acá: queremos que el usuario
                                  siempre pase por step 2 para ver disponibilidad real,
                                  incluso si llegó con ?room_id=X desde el index. */ ?>

                        <button type="submit"
                                class="w-full bg-teal text-white font-bold py-4 rounded-2xl hover:bg-teal-hover transition-all shadow-md flex items-center justify-center gap-2 text-base">
                            Find available rooms <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ============================ STEP 2: ROOMS GRID ============================ -->
            <?php if ($step === 2): ?>
                <div class="flex justify-between items-center mb-6">
                    <a href="book.php" class="text-slate-500 hover:text-teal flex items-center gap-2 text-sm font-medium">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i> Change dates
                    </a>
                    <p id="rooms_status" class="text-sm text-slate-500">Loading availability…</p>
                </div>

                <div id="rooms_grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($rooms as $r): ?>
                        <article class="room-card bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden flex flex-col"
                                 data-room-id="<?php echo htmlspecialchars((string)$r['id']); ?>">
                            <div class="relative h-56 overflow-hidden bg-slate-100">
                                <img src="<?php echo htmlspecialchars($r['image'] ?? ''); ?>" alt="<?php echo htmlspecialchars($r['name']); ?>"
                                     class="w-full h-full object-cover">
                                <div class="room-badge absolute top-3 right-3 bg-slate-300 text-slate-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                                    Checking…
                                </div>
                            </div>
                            <div class="p-5 flex-1 flex flex-col">
                                <p class="text-[11px] text-teal uppercase tracking-widest font-bold mb-1"><?php echo htmlspecialchars($r['type'] ?? ''); ?></p>
                                <h3 class="text-lg font-bold text-slate-900 mb-2"><?php echo htmlspecialchars($r['name']); ?></h3>
                                <p class="text-sm text-slate-500 mb-4 line-clamp-2"><?php echo htmlspecialchars($r['description'] ?? ''); ?></p>
                                <div class="flex items-center gap-3 text-xs text-slate-500 mb-4">
                                    <span class="flex items-center gap-1"><i data-lucide="users" class="w-4 h-4"></i> Up to <?php echo (int)($r['capacity'] ?? 1); ?></span>
                                </div>
                                <div class="mt-auto flex items-end justify-between">
                                    <div>
                                        <p class="text-xs text-slate-400 uppercase tracking-wider font-bold">Per night</p>
                                        <p class="room-price text-2xl font-bold text-slate-900">—</p>
                                        <p class="room-total text-xs text-slate-500">—</p>
                                    </div>
                                    <a class="room-cta opacity-50 pointer-events-none bg-teal text-white font-bold px-5 py-3 rounded-xl text-sm transition-all"
                                       href="#">Select</a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- ============================ STEP 3: GUEST DETAILS ============================ -->
            <?php if ($step === 3 && $selectedRoom): ?>
                <?php
                    $roomLocalId = (string)$selectedRoom['id'];
                    $bananaTypeId = isset($roomMap[$roomLocalId]) ? $roomMap[$roomLocalId] : null;
                ?>
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    <!-- LEFT: selected room card + guest form -->
                    <div class="lg:col-span-8 space-y-6">

                        <!-- Warning banner cuando la habitación elegida no está disponible -->
                        <div id="room_unavailable_banner" class="hidden bg-amber-50 border border-amber-200 rounded-2xl p-5 flex items-start gap-3">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-600 mt-0.5 shrink-0"></i>
                            <div class="text-sm">
                                <p class="font-bold text-amber-800">This room is not available for your dates.</p>
                                <p class="text-amber-700 mt-1" id="room_unavailable_reason">Please pick another room from the list, or change your dates.</p>
                            </div>
                        </div>

                        <!-- Selected room card -->
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                            <div class="grid grid-cols-1 sm:grid-cols-5">
                                <div class="sm:col-span-2 h-48 sm:h-auto bg-slate-100">
                                    <img src="<?php echo htmlspecialchars($selectedRoom['image'] ?? ''); ?>"
                                         alt="<?php echo htmlspecialchars($selectedRoom['name']); ?>"
                                         class="w-full h-full object-cover">
                                </div>
                                <div class="sm:col-span-3 p-6 flex flex-col">
                                    <p class="text-[11px] text-teal uppercase tracking-widest font-bold mb-1"><?php echo htmlspecialchars($selectedRoom['type'] ?? ''); ?></p>
                                    <h3 class="text-xl font-bold text-slate-900 mb-1"><?php echo htmlspecialchars($selectedRoom['name']); ?></h3>
                                    <p class="text-sm text-slate-500 mb-3 line-clamp-2"><?php echo htmlspecialchars($selectedRoom['description'] ?? ''); ?></p>
                                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                        <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> <?php echo htmlspecialchars($getCheckIn); ?> → <?php echo htmlspecialchars($getCheckOut); ?></span>
                                        <span class="flex items-center gap-1"><i data-lucide="moon" class="w-3.5 h-3.5"></i> <?php echo $nightsCount; ?> night<?php echo $nightsCount > 1 ? 's' : ''; ?></span>
                                        <span class="flex items-center gap-1"><i data-lucide="users" class="w-3.5 h-3.5"></i> Up to <?php echo (int)($selectedRoom['capacity'] ?? 1); ?></span>
                                    </div>
                                    <div class="mt-auto pt-4 flex items-end justify-between">
                                        <div class="flex items-center gap-2">
                                            <a href="book.php" class="text-xs font-semibold text-slate-600 border border-slate-300 rounded-full px-3 py-1.5 hover:border-teal hover:text-teal transition-all flex items-center gap-1 whitespace-nowrap">
                                                <i data-lucide="calendar" class="w-3.5 h-3.5"></i> Change dates
                                            </a>
                                            <a id="change_room_link" href="book.php?check_in=<?php echo urlencode($getCheckIn); ?>&check_out=<?php echo urlencode($getCheckOut); ?>&guests_count=<?php echo urlencode((string)$getGuestsCount); ?>"
                                               class="text-xs font-semibold text-slate-600 border border-slate-300 rounded-full px-3 py-1.5 hover:border-teal hover:text-teal transition-all flex items-center gap-1 whitespace-nowrap">
                                                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Change room
                                            </a>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xs text-slate-400 uppercase tracking-wider font-bold">Total</p>
                                            <p id="display_total_ars" class="text-2xl font-bold text-teal">AR$ —</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Guest form -->
                        <form method="POST" action="/book" id="booking_form" class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6 md:p-8 space-y-6">
                            <input type="hidden" name="submit_booking" value="1">
                            <input type="hidden" name="room_id"   value="<?php echo htmlspecialchars($roomLocalId); ?>">
                            <input type="hidden" name="check_in"  value="<?php echo htmlspecialchars($getCheckIn); ?>">
                            <input type="hidden" name="check_out" value="<?php echo htmlspecialchars($getCheckOut); ?>">
                            <input type="hidden" name="total_price"      id="hidden_total"     value="0">
                            <input type="hidden" name="total_price_ars"  id="hidden_total_ars" value="0">

                            <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3">Your information</h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Number of Guests</label>
                                    <input type="text" name="guests_count" id="guests_count_input" readonly
                                           value="<?php echo max(1, (int)$getGuestsCount); ?>"
                                           class="w-full bg-slate-100 border border-slate-200 rounded-xl p-4 text-slate-700 font-medium outline-none cursor-not-allowed">
                                    <p class="text-xs text-slate-400 mt-1.5">Up to <?php echo (int)($selectedRoom['capacity'] ?? 1); ?> guests for this room. To change it, use "Change dates" above.</p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Full Legal Name *</label>
                                    <input type="text" name="guest_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal" placeholder="As it appears on your ID">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Email *</label>
                                    <input type="email" name="email" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal" placeholder="you@example.com">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Phone *</label>
                                    <div class="flex items-stretch">
                                        <input type="text" id="phone_code_prefix" readonly maxlength="6"
                                               class="w-20 text-center bg-slate-100 border border-r-0 border-slate-200 rounded-l-xl px-2 text-slate-700 font-mono text-sm font-bold outline-none focus:ring-2 focus:ring-teal"
                                               value="+54" placeholder="+??">
                                        <input type="tel" id="phone_local_input" required
                                               class="flex-1 bg-slate-50 border border-slate-200 rounded-r-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal"
                                               placeholder="11 1234567">
                                        <input type="hidden" name="phone" id="phone_hidden">
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1.5">Without country code — e.g. 11 1234567</p>
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Country *</label>
                                    <select name="nationality" id="nationality_select" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal">
                                        <option value="">Select country…</option>
                                        <?php foreach ($countries as $c): ?>
                                        <option value="<?= htmlspecialchars($c['name']) ?>"<?= $c['name'] === 'Argentina' ? ' selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">ID Type *</label>
                                    <select name="id_type" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal">
                                        <option value="">Select…</option>
                                        <option value="Passport">Passport</option>
                                        <option value="DNI">DNI</option>
                                        <option value="National ID">National ID</option>
                                        <option value="Driver License">Driver License</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">ID Number *</label>
                                    <input type="text" name="id_number" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Estimated Arrival</label>
                                    <select name="eta" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal">
                                        <option value="">Not sure</option>
                                        <option value="Morning (08:00-12:00)">Morning (08:00-12:00)</option>
                                        <option value="Afternoon (12:00-18:00)">Afternoon (12:00-18:00)</option>
                                        <option value="Evening (18:00-22:00)">Evening (18:00-22:00)</option>
                                        <option value="Late Night (22:00+)">Late Night (22:00+)</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Notes (optional)</label>
                                    <textarea name="notes" rows="3" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal" placeholder="Anything we should know? Dietary, accessibility, group travel…"></textarea>
                                </div>
                            </div>

                            <div class="pt-2 flex flex-col sm:flex-row gap-3 items-center justify-between">
                                <p class="text-xs text-slate-500">Payment is collected upon arrival in Mendoza.</p>
                                <button type="submit" id="submit_btn"
                                        class="w-full sm:w-auto bg-teal text-white font-bold px-8 py-4 rounded-2xl hover:bg-teal-hover transition-all shadow-md flex items-center justify-center gap-2">
                                    Confirm Prebooking <i data-lucide="check-circle" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- RIGHT: room thumbnails strip + summary -->
                    <aside class="lg:col-span-4 space-y-6">
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-6">
                            <h4 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                                <i data-lucide="bed" class="w-4 h-4 text-teal"></i>
                                Or pick another room
                            </h4>
                            <div class="grid grid-cols-2 gap-3" id="other_rooms_strip">
                                <?php foreach ($rooms as $r):
                                    $isCurrent = ((string)$r['id'] === $roomLocalId);
                                ?>
                                    <a class="room-thumb block rounded-xl overflow-hidden border-2 <?php echo $isCurrent ? 'is-current border-teal' : 'border-slate-200'; ?> bg-white"
                                       href="book.php?check_in=<?php echo urlencode($getCheckIn); ?>&check_out=<?php echo urlencode($getCheckOut); ?>&guests_count=<?php echo urlencode((string)$getGuestsCount); ?>&room_id=<?php echo urlencode((string)$r['id']); ?>"
                                       data-target-room-id="<?php echo htmlspecialchars((string)$r['id']); ?>">
                                        <div class="h-20 bg-slate-100">
                                            <img src="<?php echo htmlspecialchars($r['image'] ?? ''); ?>" alt="<?php echo htmlspecialchars($r['name']); ?>" class="w-full h-full object-cover">
                                        </div>
                                        <div class="p-2">
                                            <p class="text-[11px] font-bold text-slate-900 line-clamp-1"><?php echo htmlspecialchars($r['name']); ?></p>
                                            <p class="text-[10px] text-slate-500">Cap. <?php echo (int)($r['capacity'] ?? 1); ?></p>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-3">Your form data will be preserved when switching.</p>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>

            <!-- ============================ SUCCESS ============================ -->
            <?php if ($step === 'success'):
                $successCopy = ($guestLang ?? 'en') === 'es' ? [
                    'title'    => '¡Gracias por tu reserva!',
                    'subtitle' => 'Ya la tenemos registrada y nuestro equipo va a estar esperándote. Te mandamos una copia de los detalles por mail — el pago se hace directamente en el check-in.',
                    'mailErr'  => 'No se pudo enviar el email de confirmación.',
                    'mailOk'   => '✓ Email de confirmación enviado a',
                    'backHome' => 'Volver al inicio',
                    'chat'     => 'Escribinos',
                    'code'     => 'Código de reserva',
                ] : [
                    'title'    => 'Thank You for Your Reservation!',
                    'subtitle' => "We've got it on file and our team will be ready for you. We've emailed you a copy of the details — payment is made directly at check-in.",
                    'mailErr'  => 'No se pudo enviar el email de confirmación.',
                    'mailOk'   => '✓ Confirmation email sent to',
                    'code'     => 'Booking code',
                    'backHome' => 'Back to home',
                    'chat'     => 'Chat with us',
                ];
            ?>
                <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 p-10 text-center">
                    <div class="w-16 h-16 mx-auto bg-teal-light text-teal rounded-full flex items-center justify-center mb-6">
                        <i data-lucide="check" class="w-8 h-8"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-3"><?php echo htmlspecialchars($successCopy['title']); ?></h2>
                    <p class="text-slate-500 mb-4"><?php echo htmlspecialchars($successCopy['subtitle']); ?></p>

                    <div class="inline-flex items-center gap-2 bg-teal-light text-teal rounded-full px-4 py-2 mb-6 font-mono text-sm font-bold">
                        <span class="font-sans font-medium text-xs uppercase tracking-wide opacity-70"><?php echo htmlspecialchars($successCopy['code']); ?>:</span>
                        <?php echo htmlspecialchars($newReservationId); ?>
                    </div>

                    <?php if ($mailError): ?>
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-left">
                            <p class="text-sm font-bold text-amber-800 mb-1"><?php echo htmlspecialchars($successCopy['mailErr']); ?></p>
                            <p class="text-xs text-amber-700 font-mono break-all"><?php echo htmlspecialchars($mailError); ?></p>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-green-600 mb-4"><?php echo htmlspecialchars($successCopy['mailOk']); ?> <?php echo htmlspecialchars($newBooking['email']); ?></p>
                    <?php endif; ?>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="/" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all"><?php echo htmlspecialchars($successCopy['backHome']); ?></a>
                        <a href="https://api.whatsapp.com/send/?phone=5492612592729" target="_blank" class="px-6 py-3 bg-teal text-white rounded-xl font-bold hover:bg-teal-hover transition-all flex items-center justify-center gap-2">
                            <i data-lucide="message-circle" class="w-4 h-4"></i> <?php echo htmlspecialchars($successCopy['chat']); ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <?php include __DIR__ . '/footer.php'; ?>

    <script>
        lucide.createIcons();

        // ============================================================
        // STEP 1: flatpickr para fechas
        // ============================================================
        <?php if ($step === 1): ?>
            const today = new Date();
            const ciInput = document.getElementById('check_in_input');
            const coInput = document.getElementById('check_out_input');
            let fpIn, fpOut;
            fpIn = flatpickr(ciInput, {
                minDate: 'today',
                dateFormat: 'Y-m-d',
                disableMobile: true,
                onChange: function(sd) {
                    if (fpOut) fpOut.destroy();
                    fpOut = flatpickr(coInput, {
                        minDate: new Date(sd[0]).fp_incr(1),
                        dateFormat: 'Y-m-d',
                        disableMobile: true,
                    });
                    if (coInput.value && coInput.value <= ciInput.value) {
                        fpOut.setDate(new Date(sd[0]).fp_incr(1), true);
                    }
                },
            });
            fpOut = flatpickr(coInput, {
                minDate: ciInput.value ? new Date(ciInput.value).fp_incr(1) : new Date().fp_incr(1),
                dateFormat: 'Y-m-d',
                disableMobile: true,
            });
        <?php endif; ?>

        // ============================================================
        // STEP 2: cargar disponibilidad real desde rooms_for_dates.php
        // ============================================================
        <?php if ($step === 2): ?>
            const checkIn     = <?php echo json_encode($getCheckIn); ?>;
            const checkOut    = <?php echo json_encode($getCheckOut); ?>;
            const guestsCount = <?php echo (int)$getGuestsCount; ?>;

            async function loadAvailability() {
                const statusEl = document.getElementById('rooms_status');
                try {
                    const r = await fetch(`rooms_for_dates.php?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}&guests_count=${encodeURIComponent(guestsCount)}`, { cache: 'no-store' });
                    const data = await r.json();
                    if (!data.ok) throw new Error(data.error || 'No data');

                    statusEl.textContent = data.rooms.filter(x => x.available).length + ' rooms available · ' + data.nights + ' night' + (data.nights > 1 ? 's' : '');

                    document.querySelectorAll('.room-card').forEach(card => {
                        const id = card.getAttribute('data-room-id');
                        const info = data.rooms.find(x => String(x.id) === String(id));
                        if (!info) return;

                        const badge = card.querySelector('.room-badge');
                        const price = card.querySelector('.room-price');
                        const total = card.querySelector('.room-total');
                        const cta   = card.querySelector('.room-cta');

                        const ppn = Number(info.price_per_night_ars || 0);
                        const tot = Number(info.total_ars || 0);

                        // Mostrar SIEMPRE el precio cuando lo tenemos
                        // (ya sea desde BananaDesk o desde rooms.json como fallback).
                        if (ppn > 0) {
                            price.textContent = 'AR$ ' + ppn.toLocaleString('es-AR');
                            total.textContent = 'Total ' + data.nights + 'n: AR$ ' + tot.toLocaleString('es-AR');
                        } else {
                            price.textContent = '—';
                            total.textContent = '';
                        }

                        if (info.available) {
                            badge.textContent = 'Available';
                            badge.className = 'room-badge absolute top-3 right-3 bg-teal-light text-teal text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider';
                            cta.classList.remove('opacity-50', 'pointer-events-none');
                            cta.textContent = 'Select';
                            cta.href = `book.php?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}&guests_count=${encodeURIComponent(guestsCount)}&room_id=${encodeURIComponent(info.id)}`;
                        } else {
                            let badgeMsg = 'Not available';
                            if (info.reason === 'min_stay')            badgeMsg = `Min stay ${info.min_stay} nights`;
                            else if (info.reason === 'not_enough_beds') badgeMsg = `Not enough beds (${info.availability_count} left)`;
                            else if (info.reason === 'over_capacity')   badgeMsg = `Fits up to ${info.capacity}`;
                            badge.textContent = badgeMsg;
                            badge.className = 'room-badge absolute top-3 right-3 bg-slate-200 text-slate-500 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider';
                            cta.classList.add('opacity-50', 'pointer-events-none');
                            cta.textContent = 'Unavailable';
                        }
                    });
                    // Sort: available cards first, then unavailable
                    const grid = document.getElementById('rooms_grid');
                    const cards = Array.from(grid.querySelectorAll('.room-card'));
                    cards.sort((a, b) => {
                        const infoA = data.rooms.find(x => String(x.id) === String(a.getAttribute('data-room-id')));
                        const infoB = data.rooms.find(x => String(x.id) === String(b.getAttribute('data-room-id')));
                        const availA = infoA?.available ? 0 : 1;
                        const availB = infoB?.available ? 0 : 1;
                        return availA - availB;
                    });
                    cards.forEach(c => grid.appendChild(c));

                    lucide.createIcons();
                } catch (e) {
                    statusEl.textContent = 'Could not load live availability. Please try again.';
                    statusEl.className = 'text-sm text-amber-600';
                    console.warn(e);
                }
            }
            loadAvailability();
        <?php endif; ?>

        // ============================================================
        // STEP 3: total price + persist form data when switching room
        // ============================================================
        <?php if ($step === 3 && $selectedRoom): ?>
            const ci = <?php echo json_encode($getCheckIn); ?>;
            const co = <?php echo json_encode($getCheckOut); ?>;
            const nights = <?php echo (int)$nightsCount; ?>;
            const roomId = <?php echo json_encode((string)$selectedRoom['id']); ?>;
            const guestsCount = <?php echo (int)$getGuestsCount; ?>; // fijo en este paso: el campo es de solo lectura

            // ---- Persistencia de datos del formulario al cambiar de habitación ----
            const formEl = document.getElementById('booking_form');
            const stashKey = 'hp_booking_form_v1';

            // Restaurar si veníamos de otra habitación
            try {
                const cached = JSON.parse(sessionStorage.getItem(stashKey) || 'null');
                if (cached && cached.check_in === ci && cached.check_out === co) {
                    Object.entries(cached.data || {}).forEach(([k, v]) => {
                        const el = formEl.querySelector(`[name="${k}"]`);
                        if (el && el.type !== 'hidden') el.value = v;
                    });
                }
            } catch (e) {}

            async function loadPrice() {
                try {
                    const r = await fetch(`rooms_for_dates.php?check_in=${encodeURIComponent(ci)}&check_out=${encodeURIComponent(co)}&guests_count=${encodeURIComponent(guestsCount)}`, { cache: 'no-store' });
                    const data = await r.json();
                    if (!data.ok) throw new Error(data.error || 'no data');
                    const room = data.rooms.find(x => String(x.id) === String(roomId));
                    if (!room) return;

                    const totalARS = Number(room.total_ars || 0); // ya escalado por huéspedes en dormitorios compartidos
                    const usdMultiplier = (room.booking_unit === 'bed') ? guestsCount : 1;
                    const totalUSD = room.price_usd_from ? (Number(room.price_usd_from) * nights * usdMultiplier) : 0;
                    document.getElementById('display_total_ars').textContent = 'AR$ ' + totalARS.toLocaleString('es-AR');
                    // totalUSD no se muestra en pantalla (sólo el total en ARS), pero se
                    // sigue mandando en el hidden field: lo usa el mail para huéspedes extranjeros.
                    document.getElementById('hidden_total').value     = totalUSD || 0;
                    document.getElementById('hidden_total_ars').value = totalARS;

                    // ── Si la habitación NO está disponible para esas fechas/huéspedes:
                    //    - mostrar el banner
                    //    - cambiar el precio total a gris
                    //    - deshabilitar el botón "Confirm booking"
                    //    - bajar la opacidad de la tarjeta de la habitación elegida
                    if (!room.available) {
                        const banner = document.getElementById('room_unavailable_banner');
                        if (banner) banner.classList.remove('hidden');

                        const reasonEl = document.getElementById('room_unavailable_reason');
                        if (reasonEl) {
                            if (room.reason === 'min_stay') {
                                reasonEl.textContent = `Minimum stay is ${room.min_stay} nights for this room — your stay is ${nights}. Extend your dates or pick another room.`;
                            } else if (room.reason === 'not_enough_beds') {
                                reasonEl.textContent = `Only ${room.availability_count} bed(s) left for these dates — go back and reduce the guest count, or pick another room.`;
                            } else if (room.reason === 'over_capacity') {
                                reasonEl.textContent = `This room fits up to ${room.capacity} guests — go back and reduce the guest count, or pick another room.`;
                            } else {
                                reasonEl.textContent = 'No rooms left of this type for those dates. Please pick another room from the list, or change your dates.';
                            }
                        }

                        const totalEl = document.getElementById('display_total_ars');
                        if (totalEl) totalEl.className = 'text-2xl font-bold text-slate-400 line-through';

                        const btn = document.getElementById('submit_btn');
                        if (btn) {
                            btn.disabled = true;
                            btn.classList.add('opacity-50', 'cursor-not-allowed');
                            btn.classList.remove('hover:bg-teal-hover');
                            btn.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4"></i> Not available for these dates';
                        }

                        const wrapper = document.querySelector('.lg\\:col-span-8 .bg-white.rounded-3xl.shadow-sm.border.border-slate-200.overflow-hidden');
                        if (wrapper) wrapper.classList.add('opacity-60', 'grayscale');

                        if (window.lucide) lucide.createIcons();
                    }
                } catch (e) { console.warn(e); }
            }
            loadPrice();

            // Guardar antes de cambiar de habitación
            document.querySelectorAll('#other_rooms_strip a').forEach(a => {
                a.addEventListener('click', (ev) => {
                    const data = {};
                    new FormData(formEl).forEach((v, k) => {
                        if (['submit_booking','room_id','check_in','check_out','total_price','total_price_ars'].includes(k)) return;
                        data[k] = v;
                    });
                    sessionStorage.setItem(stashKey, JSON.stringify({ check_in: ci, check_out: co, data }));
                });
            });

            // Limpiar stash al confirmar
            formEl.addEventListener('submit', () => {
                sessionStorage.removeItem(stashKey);
            });

            // ── Country code prefix ──────────────────────────────────────────
            <?php
                $phoneCodes = [];
                $phoneHints = [];
                foreach ($countries as $c) {
                    if ($c['code']) $phoneCodes[$c['name']] = $c['code'];
                    if ($c['hint']) $phoneHints[$c['name']] = $c['hint'];
                }
            ?>
            const PHONE_CODES = <?= json_encode($phoneCodes, JSON_UNESCAPED_UNICODE) ?>;
            const PHONE_HINTS = <?= json_encode($phoneHints, JSON_UNESCAPED_UNICODE) ?>;

            const countrySelect  = document.getElementById('nationality_select');
            const prefixEl       = document.getElementById('phone_code_prefix');
            const phoneLocal     = document.getElementById('phone_local_input');
            const phoneHidden    = document.getElementById('phone_hidden');

            function updatePhoneCode() {
                const country = countrySelect ? countrySelect.value : '';
                if (country === 'Other' || !country) {
                    // Editable: quitar readonly y limpiar
                    if (prefixEl) {
                        prefixEl.removeAttribute('readonly');
                        prefixEl.value = '';
                        prefixEl.placeholder = '+??';
                        prefixEl.classList.remove('bg-slate-100');
                        prefixEl.classList.add('bg-white');
                        prefixEl.focus();
                    }
                } else {
                    // País conocido: readonly con el código
                    const code = PHONE_CODES[country] || '';
                    if (prefixEl) {
                        prefixEl.setAttribute('readonly', '');
                        prefixEl.value = code;
                        prefixEl.classList.remove('bg-white');
                        prefixEl.classList.add('bg-slate-100');
                    }
                    if (phoneLocal && PHONE_HINTS[country]) phoneLocal.placeholder = PHONE_HINTS[country];
                }
            }

            if (countrySelect) countrySelect.addEventListener('change', updatePhoneCode);
            updatePhoneCode(); // sync on load

            // Combine code + local number into hidden field before submit
            formEl.addEventListener('submit', () => {
                const code  = prefixEl ? prefixEl.value.trim() : '';
                const local = phoneLocal ? phoneLocal.value.trim() : '';
                if (phoneHidden) phoneHidden.value = code ? code + ' ' + local : local;
            }, true); // capture phase so it runs before the stash listener
        <?php endif; ?>
    </script>
</body>
</html>
