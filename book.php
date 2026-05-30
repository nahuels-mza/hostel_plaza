<?php
// =============================================================================
// HOSTEL PLAZA · BOOKING WIZARD (3 steps)
// =============================================================================
// step 1 → fechas
// step 2 → elegir habitación (disponibilidad + precio en vivo desde BananaDesk)
// step 3 → datos del huésped + confirmar
// step success → confirmación post-POST
// =============================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- 0. Config + rooms ---
$config         = is_file('config.json') ? json_decode(file_get_contents('config.json'), true) : [];
$exchangeRateARS = $config['exchangeRateARS'] ?? 1370;

$roomsFile = 'rooms.json';
$rooms = is_file($roomsFile) ? (json_decode(file_get_contents($roomsFile), true) ?: []) : [];

foreach ($rooms as &$room) {
    $raw_price = (float) preg_replace('/[^0-9.]/', '', $room['price']);
    $room['price_ars_num'] = $raw_price * $exchangeRateARS;
}
unset($room);

$mapPath    = __DIR__ . '/room_mapping.json';
$roomMap    = is_file($mapPath) ? (json_decode(file_get_contents($mapPath), true) ?: []) : [];

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

    $newBooking = [
        "id"          => $newReservationId,
        "roomId"      => htmlspecialchars($_POST['room_id'] ?? ''),
        "checkIn"     => htmlspecialchars($_POST['check_in'] ?? ''),
        "checkOut"    => htmlspecialchars($_POST['check_out'] ?? ''),
        "guestsCount" => "1",
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

    // Send confirmation email (Ferozo SMTP)
    $pathException = __DIR__ . '/PHPMailer-master/src/Exception.php';
    $pathPHPMailer = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    $pathSMTP      = __DIR__ . '/PHPMailer-master/src/SMTP.php';

    if (file_exists($pathException) && file_exists($pathPHPMailer) && file_exists($pathSMTP)) {
        require_once $pathException;
        require_once $pathPHPMailer;
        require_once $pathSMTP;

        $smtpDebugLog = '';
        try {
            $mail = new PHPMailer(true);
            $mail->SMTPDebug  = 3;
            $mail->Debugoutput = function($str, $level) use (&$smtpDebugLog) {
                $smtpDebugLog .= "[{$level}] " . trim($str) . "\n";
            };
            $mail->isSMTP();
            $mail->Host       = 'c2721166.ferozo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'confirmation@hostelplaza.com.ar';
            $mail->Password   = 'ThHQ*RW5hG';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->Timeout    = 15;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ];

            $mail->setFrom('confirmation@hostelplaza.com.ar', 'Hostel Plaza');
            $mail->addAddress($newBooking['email'], $newBooking['guestName']);
            $mail->addCC('confirmation@hostelplaza.com.ar', 'Hostel Plaza');
            $mail->addBCC('hostelplazamza@gmail.com');
            $mail->addReplyTo('info@hostelplaza.com.ar', 'Hostel Plaza Info');

            $mail->isHTML(true);
            $mail->Subject = 'Booking Request Received - Hostel Plaza';
            $mail->Body = "
            <html><body style='font-family: Arial, sans-serif; color: #1e293b; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; background:#fff;'>
                <div style='text-align:center;padding-bottom:20px;'><h1 style='color:#1c5457;margin:0;font-size:28px;'>Hostel Plaza Mendoza</h1></div>
                <h2 style='color:#1c5457;margin-top:0;'>Hello {$newBooking['guestName']},</h2>
                <p style='font-size:16px;'>Your booking request has been sent to Hostel Plaza. We are reviewing your reservation and will confirm it shortly.</p>
                <div style='background:#f8fafc;padding:25px;border-radius:12px;border:2px dashed #cbd5e1;margin:30px 0;text-align:center;'>
                    <p style='margin:0 0 10px 0;color:#64748b;font-size:14px;text-transform:uppercase;letter-spacing:1px;font-weight:bold;'>Your Unique PIN</p>
                    <h1 style='margin:0;color:#1c5457;font-size:36px;letter-spacing:3px;'>{$newReservationId}</h1>
                    <p style='margin:15px 0 0 0;color:#ef4444;font-size:14px;font-weight:bold;'>⚠️ Please save this PIN. You will need to show it at reception when checking in.</p>
                </div>
                <table style='width:100%;border-collapse:collapse;margin-bottom:30px;'>
                    <tr><td style='padding:12px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check In</td><td style='padding:12px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$newBooking['checkIn']}</td></tr>
                    <tr><td style='padding:12px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;'>Check Out</td><td style='padding:12px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;'>{$newBooking['checkOut']}</td></tr>
                    <tr><td style='padding:15px 10px;border-bottom:1px solid #e2e8f0;color:#64748b;font-weight:bold;'>Total Due at Check-in</td><td style='padding:15px 10px;border-bottom:1px solid #e2e8f0;font-weight:bold;text-align:right;color:#1c5457;font-size:20px;'>$" . number_format($newBooking['totalPrice'], 2) . "<br><span style='font-size:13px;color:#94a3b8;'>(AR$ {$formattedARS})</span></td></tr>
                </table>
                <p style='font-size:15px;'>Questions? WhatsApp: <strong><a href='https://api.whatsapp.com/send/?phone=549615372767' style='color:#1c5457;text-decoration:none;'>+549615372767</a></strong></p>
                <p style='margin-top:40px;font-size:15px;color:#64748b;'>Safe travels,<br><strong style='color:#1c5457;'>The Hostel Plaza Team</strong></p>
            </body></html>";
            $mail->AltBody = "Booking PIN: {$newReservationId}\nCheck In: {$newBooking['checkIn']}\nCheck Out: {$newBooking['checkOut']}\nTotal: $" . number_format($newBooking['totalPrice'], 2) . " (AR$ {$formattedARS})";

            $mail->send();
            file_put_contents(
                __DIR__ . '/mail_debug.log',
                date('c') . " OK booking={$newReservationId} to={$newBooking['email']}\n" . $smtpDebugLog . "\n",
                FILE_APPEND
            );
        } catch (\Exception $e) {
            $mailError = $e->getMessage() . ($mail->ErrorInfo ? " | {$mail->ErrorInfo}" : '');
            file_put_contents(
                __DIR__ . '/mail_debug.log',
                date('c') . " ERROR booking={$newReservationId} to={$newBooking['email']}\n{$mailError}\n{$smtpDebugLog}\n",
                FILE_APPEND
            );
            error_log("[Hostel Plaza] Mail error {$newReservationId}: {$mailError}");
        }
    } else {
        $mailError = "The 'PHPMailer' folder is missing!";
    }

    $bookingSuccess = true;
}

// --- 2. URL params (and backward-compat with old ?room=NAME) ---
$getCheckIn  = trim((string)($_GET['check_in']  ?? $_GET['checkIn']  ?? ''));
$getCheckOut = trim((string)($_GET['check_out'] ?? $_GET['checkOut'] ?? ''));
$getRoomId   = trim((string)($_GET['room_id']   ?? ''));
$getRoomName = trim((string)($_GET['room']      ?? ''));

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation · Hostel Plaza</title>

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
                    <?php if ($step === 'success'): ?>You're All Set!
                    <?php elseif ($step === 1):   ?>When are you coming?
                    <?php elseif ($step === 2):   ?>Choose Your Room
                    <?php else:                   ?>Last Step — Your Details
                    <?php endif; ?>
                </h1>
                <p class="text-slate-500 text-lg">
                    <?php if ($step === 'success'): ?>We've sent a confirmation email with your reservation PIN.
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
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Check In</label>
                                <input type="text" name="check_in" id="check_in_input" value="<?php echo htmlspecialchars($getCheckIn); ?>" required
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal font-medium" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Check Out</label>
                                <input type="text" name="check_out" id="check_out_input" value="<?php echo htmlspecialchars($getCheckOut); ?>" required
                                       class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal font-medium" />
                            </div>
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
                                        <a href="book.php?check_in=<?php echo urlencode($getCheckIn); ?>&check_out=<?php echo urlencode($getCheckOut); ?>"
                                           class="text-xs text-slate-500 hover:text-teal flex items-center gap-1">
                                            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Change room
                                        </a>
                                        <div class="text-right">
                                            <p class="text-xs text-slate-400 uppercase tracking-wider font-bold">Total</p>
                                            <p id="display_total_ars" class="text-2xl font-bold text-teal">AR$ —</p>
                                            <p id="display_total_usd" class="text-xs text-slate-500">$—</p>
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
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Full Legal Name *</label>
                                    <input type="text" name="guest_name" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal" placeholder="As it appears on your ID">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Email *</label>
                                    <input type="email" name="email" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal" placeholder="you@example.com">
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Phone *</label>
                                    <input type="tel" name="phone" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal" placeholder="+54 ...">
                                </div>

                                <div>
                                    <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Nationality *</label>
                                    <input type="text" name="nationality" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal" placeholder="e.g. Argentina">
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
                                    Confirm booking <i data-lucide="check-circle" class="w-4 h-4"></i>
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
                                       href="book.php?check_in=<?php echo urlencode($getCheckIn); ?>&check_out=<?php echo urlencode($getCheckOut); ?>&room_id=<?php echo urlencode((string)$r['id']); ?>"
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
            <?php if ($step === 'success'): ?>
                <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-sm border border-slate-200 p-10 text-center">
                    <div class="w-16 h-16 mx-auto bg-teal-light text-teal rounded-full flex items-center justify-center mb-6">
                        <i data-lucide="check" class="w-8 h-8"></i>
                    </div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-3">Reservation Submitted</h2>
                    <p class="text-slate-500 mb-8">We're reviewing your request and will confirm it shortly. A copy of your details has been emailed to you.</p>

                    <div class="bg-teal-light/40 border-2 border-dashed border-teal/30 rounded-2xl p-6 mb-8">
                        <p class="text-xs text-teal uppercase tracking-widest font-bold mb-2">Your reservation PIN</p>
                        <p class="text-3xl font-bold text-teal tracking-widest"><?php echo htmlspecialchars($newReservationId); ?></p>
                        <p class="text-xs text-slate-500 mt-3">Save this PIN — you'll need it at reception.</p>
                    </div>

                    <?php if ($mailError): ?>
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 text-left">
                            <p class="text-sm font-bold text-amber-800 mb-1">No se pudo enviar el email de confirmación.</p>
                            <p class="text-xs text-amber-700 font-mono break-all"><?php echo htmlspecialchars($mailError); ?></p>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-green-600 mb-4">✓ Email de confirmación enviado a <?php echo htmlspecialchars($newBooking['email']); ?></p>
                    <?php endif; ?>

                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="/" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all">Back to home</a>
                        <a href="https://api.whatsapp.com/send/?phone=549615372767" target="_blank" class="px-6 py-3 bg-teal text-white rounded-xl font-bold hover:bg-teal-hover transition-all flex items-center justify-center gap-2">
                            <i data-lucide="message-circle" class="w-4 h-4"></i> Chat with us
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
                onChange: function(sd) {
                    if (fpOut) fpOut.destroy();
                    fpOut = flatpickr(coInput, {
                        minDate: new Date(sd[0]).fp_incr(1),
                        dateFormat: 'Y-m-d',
                    });
                    if (coInput.value && coInput.value <= ciInput.value) {
                        fpOut.setDate(new Date(sd[0]).fp_incr(1), true);
                    }
                },
            });
            fpOut = flatpickr(coInput, {
                minDate: ciInput.value ? new Date(ciInput.value).fp_incr(1) : new Date().fp_incr(1),
                dateFormat: 'Y-m-d',
            });
        <?php endif; ?>

        // ============================================================
        // STEP 2: cargar disponibilidad real desde rooms_for_dates.php
        // ============================================================
        <?php if ($step === 2): ?>
            const checkIn  = <?php echo json_encode($getCheckIn); ?>;
            const checkOut = <?php echo json_encode($getCheckOut); ?>;

            async function loadAvailability() {
                const statusEl = document.getElementById('rooms_status');
                try {
                    const r = await fetch(`rooms_for_dates.php?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}`, { cache: 'no-store' });
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
                            cta.href = `book.php?check_in=${encodeURIComponent(checkIn)}&check_out=${encodeURIComponent(checkOut)}&room_id=${encodeURIComponent(info.id)}`;
                        } else {
                            const minStayMsg = info.min_stay > 1 ? `Min stay ${info.min_stay} nights` : 'Not available';
                            badge.textContent = minStayMsg;
                            badge.className = 'room-badge absolute top-3 right-3 bg-slate-200 text-slate-500 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider';
                            cta.classList.add('opacity-50', 'pointer-events-none');
                            cta.textContent = 'Unavailable';
                        }
                    });
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

            async function loadPrice() {
                try {
                    const r = await fetch(`rooms_for_dates.php?check_in=${encodeURIComponent(ci)}&check_out=${encodeURIComponent(co)}`, { cache: 'no-store' });
                    const data = await r.json();
                    if (!data.ok) throw new Error(data.error || 'no data');
                    const room = data.rooms.find(x => String(x.id) === String(roomId));
                    if (!room) return;

                    const totalARS = Number(room.total_ars || 0);
                    const totalUSD = room.price_usd_from ? (Number(room.price_usd_from) * nights) : 0;
                    document.getElementById('display_total_ars').textContent = 'AR$ ' + totalARS.toLocaleString('es-AR');
                    document.getElementById('display_total_usd').textContent = totalUSD ? '$' + totalUSD.toFixed(2) + ' USD' : '';
                    document.getElementById('hidden_total').value     = totalUSD || 0;
                    document.getElementById('hidden_total_ars').value = totalARS;

                    // ── Si la habitación NO está disponible para esas fechas:
                    //    - mostrar el banner
                    //    - cambiar el precio total a gris
                    //    - deshabilitar el botón "Confirm booking"
                    //    - bajar la opacidad de la tarjeta de la habitación elegida
                    if (!room.available) {
                        const banner = document.getElementById('room_unavailable_banner');
                        if (banner) banner.classList.remove('hidden');

                        const reason = document.getElementById('room_unavailable_reason');
                        if (reason) {
                            if (room.min_stay > 1 && nights < room.min_stay) {
                                reason.textContent = `Minimum stay is ${room.min_stay} nights for this room — your stay is ${nights}. Extend your dates or pick another room.`;
                            } else {
                                reason.textContent = 'No rooms left of this type for those dates. Please pick another room from the list, or change your dates.';
                            }
                        }

                        const totalEl = document.getElementById('display_total_ars');
                        if (totalEl) {
                            totalEl.className = 'text-2xl font-bold text-slate-400 line-through';
                        }

                        const btn = document.getElementById('submit_btn');
                        if (btn) {
                            btn.disabled = true;
                            btn.classList.add('opacity-50', 'cursor-not-allowed');
                            btn.classList.remove('hover:bg-teal-hover');
                            btn.innerHTML = '<i data-lucide="x-circle" class="w-4 h-4"></i> Not available for these dates';
                        }

                        // Suaviza la card seleccionada para señalar que no aplica
                        const wrapper = document.querySelector('.lg\\:col-span-8 .bg-white.rounded-3xl.shadow-sm.border.border-slate-200.overflow-hidden');
                        if (wrapper) wrapper.classList.add('opacity-60', 'grayscale');

                        if (window.lucide) lucide.createIcons();
                    }
                } catch (e) { console.warn(e); }
            }
            loadPrice();

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
        <?php endif; ?>
    </script>
</body>
</html>
