<?php
// --- LOAD CONFIGURATION ---
$config = json_decode(file_get_contents('config.json'), true);
$exchangeRateARS = $config['exchangeRateARS'] ?? 1370;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- 1. LOAD ROOMS DATABASE (SYNCED WITH ADMIN PANEL) ---
$roomsFile = 'rooms.json';
$rooms = [];
if (file_exists($roomsFile)) {
    $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
}

// Fallback just in case JSON is empty or missing
// if (empty($rooms)) {
//     $rooms = [
//         [ "id" => "1", "name" => "Double Room with Shared Bathroom", "price" => "35", "price_ars" => "38911" ],
//         [ "id" => "2", "name" => "Family Room", "price" => "35", "price_ars" => "35024" ],
//         [ "id" => "4", "name" => "4-Bed Female Dorm", "price" => "18", "price_ars" => "17883" ],
//         [ "id" => "5", "name" => "4-Bed Mixed Dorm", "price" => "18", "price_ars" => "" ],
//         [ "id" => "6", "name" => "8-Bed Mixed Dorm", "price" => "15", "price_ars" => "" ]
//     ];
// }

// Ensure price_ars is calculated based on price and exchangeRateARS
foreach ($rooms as &$room) {
    $raw_price = (float) preg_replace('/[^0-9.]/', '', $room['price']);
    $room['price_ars'] = (string)($raw_price * $exchangeRateARS);
}
unset($room);

// --- 2. HANDLE BOOKING SUBMISSION & SEND EMAIL ---
$bookingSuccess = false;
$newReservationId = '';
$mailError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    $bookingsFile = 'bookings.json';

    $bookings = [];
    if (file_exists($bookingsFile)) {
        $bookings = json_decode(file_get_contents($bookingsFile), true);
        if (!is_array($bookings)) $bookings = [];
    }

    $newReservationId = 'HP-' . date('ym') . '-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));

    $newBooking = [
        "id" => $newReservationId,
        "roomId" => htmlspecialchars($_POST['room_id'] ?? ''),
        "checkIn" => htmlspecialchars($_POST['check_in'] ?? ''),
        "checkOut" => htmlspecialchars($_POST['check_out'] ?? ''),
        "guestsCount" => "1", // Hardcoded since field was removed
        "guestName" => htmlspecialchars($_POST['guest_name'] ?? ''),
        "age" => "", // Hardcoded since field was removed
        "gender" => "", // Hardcoded since field was removed
        "nationality" => htmlspecialchars($_POST['nationality'] ?? ''),
        "idType" => htmlspecialchars($_POST['id_type'] ?? ''),
        "idNumber" => htmlspecialchars($_POST['id_number'] ?? ''),
        "phone" => htmlspecialchars($_POST['phone'] ?? ''),
        "email" => htmlspecialchars($_POST['email'] ?? ''),
        "eta" => htmlspecialchars($_POST['eta'] ?? ''),
        "notes" => htmlspecialchars($_POST['notes'] ?? ''),
        "totalPrice" => (float)($_POST['total_price'] ?? 0),
        "amountPaid" => 0,
        "source" => "Website",
        "status" => "pending"
    ];

    // Grab the exact ARS total calculated from JS to put in the email
    $totalPriceARS = (float)($_POST['total_price_ars'] ?? ($newBooking['totalPrice'] * $exchangeRateARS));
    $formattedARS = number_format($totalPriceARS, 0, ',', '.');

    array_unshift($bookings, $newBooking);
    file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));

    // --- SEND AUTOMATED CONFIRMATION EMAIL VIA SMTP (FEROZO) ---
    $pathException = __DIR__ . '/PHPMailer-master/src/Exception.php';
    $pathPHPMailer = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    $pathSMTP = __DIR__ . '/PHPMailer-master/src/SMTP.php';

    if (file_exists($pathException) && file_exists($pathPHPMailer) && file_exists($pathSMTP)) {
        require_once $pathException;
        require_once $pathPHPMailer;
        require_once $pathSMTP;

        try {
            $mail = new PHPMailer(true);

            // --- FEROZO SMTP CONFIGURATION ---
            $mail->isSMTP();
            $mail->Host       = 'c2721166.ferozo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'confirmation@hostelplaza.com.ar'; // Your SMTP username
            $mail->Password   = 'ThHQ*RW5hG'; // Your SMTP password

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;

            // Bypass SSL verification in case Ferozo's cert is self-signed
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            //Recipients
            $mail->setFrom('confirmation@hostelplaza.com.ar', 'Hostel Plaza');
            $mail->addAddress($newBooking['email'], $newBooking['guestName']);
            $mail->addBCC('hostelplazamza@gmail.com'); // Add this line to send a BCC copy
            $mail->addReplyTo('info@hostelplaza.com.ar', 'Hostel Plaza Info');

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Booking Request Received - Hostel Plaza';

            $mail->Body = "
            <html>
            <head><title>Booking Request Received</title></head>
            <body style='font-family: Arial, sans-serif; color: #1e293b; line-height: 1.6; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff;'>
                <div style='text-align: center; padding-bottom: 20px;'>
                    <h1 style='color: #1c5457; margin: 0; font-size: 28px;'>Hostel Plaza Mendoza</h1>
                </div>

                <h2 style='color: #1c5457; margin-top: 0;'>Hello {$newBooking['guestName']},</h2>
                <p style='font-size: 16px;'>Your booking request has been sent to Hostel Plaza. We are reviewing your reservation and will confirm it shortly.</p>

                <div style='background-color: #f8fafc; padding: 25px; border-radius: 12px; border: 2px dashed #cbd5e1; margin: 30px 0; text-align: center;'>
                    <p style='margin: 0 0 10px 0; color: #64748b; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold;'>Your Unique PIN</p>
                    <h1 style='margin: 0; color: #1c5457; font-size: 36px; letter-spacing: 3px;'>{$newReservationId}</h1>
                    <p style='margin: 15px 0 0 0; color: #ef4444; font-size: 14px; font-weight: bold;'>⚠️ Please save this PIN. You will need to show it at reception when checking in.</p>
                </div>

                <table style='width: 100%; border-collapse: collapse; margin-bottom: 30px;'>
                    <tr>
                        <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; color: #64748b;'>Check In</td>
                        <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; text-align: right;'>{$newBooking['checkIn']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; color: #64748b;'>Check Out</td>
                        <td style='padding: 12px 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; text-align: right;'>{$newBooking['checkOut']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 15px 10px; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: bold;'>Total Due at Check-in</td>
                        <td style='padding: 15px 10px; border-bottom: 1px solid #e2e8f0; font-weight: bold; text-align: right; color: #1c5457; font-size: 20px;'>
                            $" . number_format($newBooking['totalPrice'], 2) . "
                            <br><span style='font-size: 13px; color: #94a3b8;'>(AR$ {$formattedARS})</span>
                        </td>
                    </tr>
                </table>

                <p style='font-size: 15px;'>If you have any questions or special requests, please contact us:</p>
                <p style='font-size: 15px;'>💬 WhatsApp: <strong><a href='https://api.whatsapp.com/send/?phone=549615372767' style='color: #1c5457; text-decoration: none;'>+549615372767</a></strong><br>🌐 <a href='https://hostelplaza.com.ar/' style='color: #1c5457; font-weight: bold; text-decoration: none;'>hostelplaza.com.ar</a></p>

                <p style='margin-top: 40px; font-size: 15px; color: #64748b;'>Safe travels,<br><strong style='color: #1c5457;'>The Hostel Plaza Team</strong></p>
            </body>
            </html>
            ";

            $mail->AltBody = "Hello {$newBooking['guestName']},\n\nYour booking request has been sent to Hostel Plaza. We are reviewing your reservation and will confirm it shortly.\n\nYour Unique PIN: {$newReservationId}\n(Please show this PIN at reception when checking in)\n\nCheck In: {$newBooking['checkIn']}\nCheck Out: {$newBooking['checkOut']}\nTotal Due at Check-in: $" . number_format($newBooking['totalPrice'], 2) . " (AR$ {$formattedARS})\n\nWhatsApp us at +549615372767 for any questions.";

            $mail->send();
        } catch (\Exception $e) {
            $mailError = "SMTP Error: {$mail->ErrorInfo}";
        }
    } else {
        $mailError = "The 'PHPMailer' folder is missing! Please make sure you uploaded it correctly.";
    }

    $bookingSuccess = true;
}

// --- 3. CATCH URL PARAMETERS FROM index.php ---
$getCheckIn = $_GET['checkIn'] ?? '';
$getCheckOut = $_GET['checkOut'] ?? '';
$getRoomName = $_GET['room'] ?? '';
$prefillRoomId = '';

if ($getRoomName) {
    foreach ($rooms as $r) {
        if (strtolower(str_replace('+', ' ', $r['name'])) === strtolower(str_replace('+', ' ', $getRoomName))) {
            $prefillRoomId = $r['id'];
            break;
        }
    }
}

// --- 4. CALCULATE BOOKED DATES FOR CALENDAR GRAY-OUT ---
$blockedDatesByRoom = [];
foreach ($rooms as $r) { $blockedDatesByRoom[$r['id']] = []; }

if (file_exists('bookings.json')) {
    $allBookings = json_decode(file_get_contents('bookings.json'), true) ?: [];
    $occupancy = [];

    foreach ($allBookings as $b) {
        if (strtolower($b['status'] ?? '') === 'cancelled' || strtolower($b['status'] ?? '') === 'checked out') continue;
        $rId = $b['roomId'] ?? '';
        if ($rId === 'unassigned' || empty($rId)) continue;
        if (empty($b['checkIn']) || empty($b['checkOut'])) continue;

        try {
            $start = new DateTime($b['checkIn']);
            $end = new DateTime($b['checkOut']);
            if ($start >= $end) continue;

            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            foreach ($period as $date) {
                $dStr = $date->format('Y-m-d');
                if (!isset($occupancy[$rId][$dStr])) $occupancy[$rId][$dStr] = 0;
                $occupancy[$rId][$dStr] += 1; // 1 booking = 1 bed
            }
        } catch (Exception $e) {
            // Ignore invalid dates quietly
        }
    }

    foreach ($rooms as $r) {
        $rId = $r['id'];
        $name = strtolower($r['name']);

        // Capacity logic based on room names
        if (strpos($name, '4-bed') !== false) $cap = 4;
        elseif (strpos($name, '8-bed') !== false) $cap = 8;
        else $cap = 1; // Doubles and Family rooms block entire room

        if (isset($occupancy[$rId])) {
            foreach ($occupancy[$rId] as $dStr => $count) {
                if ($count >= $cap) {
                    $blockedDatesByRoom[$rId][] = $dStr;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Reservation | Hostel Plaza</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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
        #google_translate_element, .goog-te-banner-frame, .skiptranslate, .goog-te-gadget-icon, .goog-tooltip, .goog-tooltip:hover, #goog-gt-tt { display: none !important; opacity: 0 !important; visibility: hidden !important; }
        body { top: 0px !important; position: static !important; }
        html { height: auto !important; top: 0px !important; }
        html.translated-ltr, html.translated-rtl { margin-top: 0 !important; padding-top: 0 !important; }
        .goog-text-highlight { background-color: transparent !important; box-shadow: none !important; }
        .lang-btn { transition: all 0.3s ease; color: #64748b; }
        .lang-btn.active { background-color: #1c5457; color: #fff; }
        .lining-nums { font-variant-numeric: lining-nums; }
    </style>
</head>
<body class="bg-[#F8FAFC] font-sans text-slate-900 min-h-screen flex flex-col">

    <div id="google_translate_element"></div>

    <nav class="bg-white border-b border-slate-200 py-4 shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 flex justify-between items-center">
            <a href="/" class="flex items-center gap-2 hover:opacity-80 transition-opacity">
                <i data-lucide="arrow-left" class="w-5 h-5 text-slate-400"></i>
                <img src="H.png" alt="Hostel Plaza Logo" class="h-20 w-auto object-contain" onerror="this.style.display='none'">
            </a>

            <div class="notranslate flex items-center bg-slate-100 rounded-full p-1 border border-slate-200 text-[10px] font-bold tracking-wider">
                <button class="lang-btn active px-3 py-1 rounded-full" onclick="changeLanguage('en', this)">EN</button>
                <button class="lang-btn px-3 py-1 rounded-full" onclick="changeLanguage('es', this)">ES</button>
                <button class="lang-btn px-3 py-1 rounded-full" onclick="changeLanguage('pt', this)">PT</button>
                <button class="lang-btn px-3 py-1 rounded-full" onclick="changeLanguage('fr', this)">FR</button>
                <button class="lang-btn px-3 py-1 rounded-full" onclick="changeLanguage('de', this)">DE</button>
            </div>
        </div>
    </nav>

    <main class="flex-1 max-w-7xl mx-auto w-full px-6 py-10">

        <?php if ($bookingSuccess): ?>
            <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-xl border border-slate-100 p-12 text-center mt-10">
                <div class="w-24 h-24 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-8">
                    <i data-lucide="check-circle" class="w-12 h-12"></i>
                </div>

                <h1 class="text-4xl font-serif font-bold text-slate-900 mb-4">Booking Request Sent!</h1>

                <p class="text-lg text-slate-500 mb-8">Confirmation sent to Hostel Plaza, please check your email.</p>

                <div class="bg-slate-50 rounded-2xl p-6 mb-8 text-center border border-slate-100 inline-block mx-auto min-w-[300px]">
                    <p class="text-sm text-slate-500 mb-2">Reservation ID</p>
                    <p class="font-mono text-2xl font-bold text-teal tracking-wider bg-teal-light px-4 py-2 rounded-lg"><?php echo $newReservationId; ?></p>
                </div>

                <?php if ($mailError): ?>
                    <div class="mt-4 mb-8 bg-red-50 border border-red-200 rounded-xl p-4 text-left">
                        <p class="text-sm font-bold text-red-700 flex items-center gap-2"><i data-lucide="alert-triangle" class="w-4 h-4"></i> System Note for Admin:</p>
                        <p class="text-xs text-red-600 mt-1"><?php echo $mailError; ?></p>
                    </div>
                <?php endif; ?>

                <br>
                <a href="index.php" class="inline-block bg-teal text-white px-8 py-4 rounded-xl font-bold hover:bg-teal-hover transition-all shadow-md">
                    Return to Homepage
                </a>
            </div>

        <?php else: ?>
            <div class="mb-10">
                <h1 class="text-4xl font-serif font-bold text-slate-900 mb-2">Complete Your Reservation</h1>
                <p class="text-slate-500 text-lg">Please provide your details below. Payment is collected upon arrival in Mendoza.</p>
            </div>

            <form method="POST" action="" class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">

                <div class="lg:col-span-8 space-y-8">

                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                            <div class="w-8 h-8 rounded-full bg-teal-light text-teal flex items-center justify-center font-bold">1</div>
                            <h2 class="text-lg font-bold text-slate-900">Your Stay</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Select Room *</label>
                                <select name="room_id" id="room_id" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal transition-all font-medium">
                                    <option value="">-- Choose a Room --</option>
                                    <?php foreach($rooms as $room):
                                        $raw_price = (float) preg_replace('/[^0-9.]/', '', $room['price']);
                                        $raw_ars = (float) $room['price_ars'];
                                    ?>
                                        <option value="<?php echo $room['id']; ?>" data-price="<?php echo $raw_price; ?>" data-price-ars="<?php echo $raw_ars; ?>" <?php echo $prefillRoomId == $room['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($room['name']); ?> ($<?php echo $raw_price; ?>/night)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Check In *</label>
                                <input type="text" name="check_in" id="check_in" value="<?php echo htmlspecialchars($getCheckIn); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal transition-all font-medium" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Check Out *</label>
                                <input type="text" name="check_out" id="check_out" value="<?php echo htmlspecialchars($getCheckOut); ?>" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:ring-2 focus:ring-teal transition-all font-medium" />
                            </div>
                            <div class="md:col-span-2">
                                <p id="booking_hint" class="text-xs text-slate-500"></p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                            <div class="w-8 h-8 rounded-full bg-teal-light text-teal flex items-center justify-center font-bold">2</div>
                            <h2 class="text-lg font-bold text-slate-900">Guest Information</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-6 gap-6">
                            <div class="md:col-span-6">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Full Legal Name *</label>
                                <input type="text" name="guest_name" required placeholder="As it appears on your ID" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all" />
                            </div>

                            <div class="md:col-span-6">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Nationality *</label>
                                <select name="nationality" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all">
                                    <option value="">Select Country</option>
                                    <option value="Afghanistan">Afghanistan</option>
                                    <option value="Albania">Albania</option>
                                    <option value="Algeria">Algeria</option>
                                    <option value="Andorra">Andorra</option>
                                    <option value="Angola">Angola</option>
                                    <option value="Antigua and Barbuda">Antigua and Barbuda</option>
                                    <option value="Argentina">Argentina</option>
                                    <option value="Armenia">Armenia</option>
                                    <option value="Australia">Australia</option>
                                    <option value="Austria">Austria</option>
                                    <option value="Azerbaijan">Azerbaijan</option>
                                    <option value="Bahamas">Bahamas</option>
                                    <option value="Bahrain">Bahrain</option>
                                    <option value="Bangladesh">Bangladesh</option>
                                    <option value="Barbados">Barbados</option>
                                    <option value="Belarus">Belarus</option>
                                    <option value="Belgium">Belgium</option>
                                    <option value="Belize">Belize</option>
                                    <option value="Benin">Benin</option>
                                    <option value="Bhutan">Bhutan</option>
                                    <option value="Bolivia">Bolivia</option>
                                    <option value="Bosnia and Herzegovina">Bosnia and Herzegovina</option>
                                    <option value="Botswana">Botswana</option>
                                    <option value="Brazil">Brazil</option>
                                    <option value="Brunei">Brunei</option>
                                    <option value="Bulgaria">Bulgaria</option>
                                    <option value="Burkina Faso">Burkina Faso</option>
                                    <option value="Burundi">Burundi</option>
                                    <option value="Cabo Verde">Cabo Verde</option>
                                    <option value="Cambodia">Cambodia</option>
                                    <option value="Cameroon">Cameroon</option>
                                    <option value="Canada">Canada</option>
                                    <option value="Central African Republic">Central African Republic</option>
                                    <option value="Chad">Chad</option>
                                    <option value="Chile">Chile</option>
                                    <option value="China">China</option>
                                    <option value="Colombia">Colombia</option>
                                    <option value="Comoros">Comoros</option>
                                    <option value="Congo">Congo</option>
                                    <option value="Costa Rica">Costa Rica</option>
                                    <option value="Croatia">Croatia</option>
                                    <option value="Cuba">Cuba</option>
                                    <option value="Cyprus">Cyprus</option>
                                    <option value="Czechia">Czechia</option>
                                    <option value="Denmark">Denmark</option>
                                    <option value="Djibouti">Djibouti</option>
                                    <option value="Dominica">Dominica</option>
                                    <option value="Dominican Republic">Dominican Republic</option>
                                    <option value="Ecuador">Ecuador</option>
                                    <option value="Egypt">Egypt</option>
                                    <option value="El Salvador">El Salvador</option>
                                    <option value="Equatorial Guinea">Equatorial Guinea</option>
                                    <option value="Eritrea">Eritrea</option>
                                    <option value="Estonia">Estonia</option>
                                    <option value="Eswatini">Eswatini</option>
                                    <option value="Ethiopia">Ethiopia</option>
                                    <option value="Fiji">Fiji</option>
                                    <option value="Finland">Finland</option>
                                    <option value="France">France</option>
                                    <option value="Gabon">Gabon</option>
                                    <option value="Gambia">Gambia</option>
                                    <option value="Georgia">Georgia</option>
                                    <option value="Germany">Germany</option>
                                    <option value="Ghana">Ghana</option>
                                    <option value="Greece">Greece</option>
                                    <option value="Grenada">Grenada</option>
                                    <option value="Guatemala">Guatemala</option>
                                    <option value="Guinea">Guinea</option>
                                    <option value="Guinea-Bissau">Guinea-Bissau</option>
                                    <option value="Guyana">Guyana</option>
                                    <option value="Haiti">Haiti</option>
                                    <option value="Honduras">Honduras</option>
                                    <option value="Hungary">Hungary</option>
                                    <option value="Iceland">Iceland</option>
                                    <option value="India">India</option>
                                    <option value="Indonesia">Indonesia</option>
                                    <option value="Iran">Iran</option>
                                    <option value="Iraq">Iraq</option>
                                    <option value="Ireland">Ireland</option>
                                    <option value="Israel">Israel</option>
                                    <option value="Italy">Italy</option>
                                    <option value="Jamaica">Jamaica</option>
                                    <option value="Japan">Japan</option>
                                    <option value="Jordan">Jordan</option>
                                    <option value="Kazakhstan">Kazakhstan</option>
                                    <option value="Kenya">Kenya</option>
                                    <option value="Kiribati">Kiribati</option>
                                    <option value="Kuwait">Kuwait</option>
                                    <option value="Kyrgyzstan">Kyrgyzstan</option>
                                    <option value="Laos">Laos</option>
                                    <option value="Latvia">Latvia</option>
                                    <option value="Lebanon">Lebanon</option>
                                    <option value="Lesotho">Lesotho</option>
                                    <option value="Liberia">Liberia</option>
                                    <option value="Libya">Libya</option>
                                    <option value="Liechtenstein">Liechtenstein</option>
                                    <option value="Lithuania">Lithuania</option>
                                    <option value="Luxembourg">Luxembourg</option>
                                    <option value="Madagascar">Madagascar</option>
                                    <option value="Malawi">Malawi</option>
                                    <option value="Malaysia">Malaysia</option>
                                    <option value="Maldives">Maldives</option>
                                    <option value="Mali">Mali</option>
                                    <option value="Malta">Malta</option>
                                    <option value="Marshall Islands">Marshall Islands</option>
                                    <option value="Mauritania">Mauritania</option>
                                    <option value="Mauritius">Mauritius</option>
                                    <option value="Mexico">Mexico</option>
                                    <option value="Micronesia">Micronesia</option>
                                    <option value="Moldova">Moldova</option>
                                    <option value="Monaco">Monaco</option>
                                    <option value="Mongolia">Mongolia</option>
                                    <option value="Montenegro">Montenegro</option>
                                    <option value="Morocco">Morocco</option>
                                    <option value="Mozambique">Mozambique</option>
                                    <option value="Myanmar">Myanmar</option>
                                    <option value="Namibia">Namibia</option>
                                    <option value="Nauru">Nauru</option>
                                    <option value="Nepal">Nepal</option>
                                    <option value="Netherlands">Netherlands</option>
                                    <option value="New Zealand">New Zealand</option>
                                    <option value="Nicaragua">Nicaragua</option>
                                    <option value="Niger">Niger</option>
                                    <option value="Nigeria">Nigeria</option>
                                    <option value="North Korea">North Korea</option>
                                    <option value="North Macedonia">North Macedonia</option>
                                    <option value="Norway">Norway</option>
                                    <option value="Oman">Oman</option>
                                    <option value="Pakistan">Pakistan</option>
                                    <option value="Palau">Palau</option>
                                    <option value="Palestine">Palestine</option>
                                    <option value="Panama">Panama</option>
                                    <option value="Papua New Guinea">Papua New Guinea</option>
                                    <option value="Paraguay">Paraguay</option>
                                    <option value="Peru">Peru</option>
                                    <option value="Philippines">Philippines</option>
                                    <option value="Poland">Poland</option>
                                    <option value="Portugal">Portugal</option>
                                    <option value="Qatar">Qatar</option>
                                    <option value="Romania">Romania</option>
                                    <option value="Russia">Russia</option>
                                    <option value="Rwanda">Rwanda</option>
                                    <option value="Saint Kitts and Nevis">Saint Kitts and Nevis</option>
                                    <option value="Saint Lucia">Saint Lucia</option>
                                    <option value="Saint Vincent and the Grenadines">Saint Vincent and the Grenadines</option>
                                    <option value="Samoa">Samoa</option>
                                    <option value="San Marino">San Marino</option>
                                    <option value="Sao Tome and Principe">Sao Tome and Principe</option>
                                    <option value="Saudi Arabia">Saudi Arabia</option>
                                    <option value="Senegal">Senegal</option>
                                    <option value="Serbia">Serbia</option>
                                    <option value="Seychelles">Seychelles</option>
                                    <option value="Sierra Leone">Sierra Leone</option>
                                    <option value="Singapore">Singapore</option>
                                    <option value="Slovakia">Slovakia</option>
                                    <option value="Slovenia">Slovenia</option>
                                    <option value="Solomon Islands">Solomon Islands</option>
                                    <option value="Somalia">Somalia</option>
                                    <option value="South Africa">South Africa</option>
                                    <option value="South Korea">South Korea</option>
                                    <option value="South Sudan">South Sudan</option>
                                    <option value="Spain">Spain</option>
                                    <option value="Sri Lanka">Sri Lanka</option>
                                    <option value="Sudan">Sudan</option>
                                    <option value="Suriname">Suriname</option>
                                    <option value="Sweden">Sweden</option>
                                    <option value="Switzerland">Switzerland</option>
                                    <option value="Syria">Syria</option>
                                    <option value="Taiwan">Taiwan</option>
                                    <option value="Tajikistan">Tajikistan</option>
                                    <option value="Tanzania">Tanzania</option>
                                    <option value="Thailand">Thailand</option>
                                    <option value="Timor-Leste">Timor-Leste</option>
                                    <option value="Togo">Togo</option>
                                    <option value="Tonga">Tonga</option>
                                    <option value="Trinidad and Tobago">Trinidad and Tobago</option>
                                    <option value="Tunisia">Tunisia</option>
                                    <option value="Turkey">Turkey</option>
                                    <option value="Turkmenistan">Turkmenistan</option>
                                    <option value="Tuvalu">Tuvalu</option>
                                    <option value="Uganda">Uganda</option>
                                    <option value="Ukraine">Ukraine</option>
                                    <option value="United Arab Emirates">United Arab Emirates</option>
                                    <option value="United Kingdom">United Kingdom</option>
                                    <option value="United States">United States</option>
                                    <option value="Uruguay">Uruguay</option>
                                    <option value="Uzbekistan">Uzbekistan</option>
                                    <option value="Vanuatu">Vanuatu</option>
                                    <option value="Vatican City">Vatican City</option>
                                    <option value="Venezuela">Venezuela</option>
                                    <option value="Vietnam">Vietnam</option>
                                    <option value="Yemen">Yemen</option>
                                    <option value="Zambia">Zambia</option>
                                    <option value="Zimbabwe">Zimbabwe</option>
                                </select>
                            </div>

                            <div class="md:col-span-3">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Identification Type *</label>
                                <select name="id_type" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all">
                                    <option value="">Select ID Type</option>
                                    <option value="Passport">Passport</option>
                                    <option value="DNI">DNI (Argentine)</option>
                                    <option value="Driver's License">Driver's License</option>
                                    <option value="National ID Card">National ID Card</option>
                                </select>
                            </div>
                            <div class="md:col-span-3">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">ID Number *</label>
                                <input type="text" name="id_number" required placeholder="Document Number" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 p-8">
                        <div class="flex items-center gap-3 mb-6 border-b border-slate-100 pb-4">
                            <div class="w-8 h-8 rounded-full bg-teal-light text-teal flex items-center justify-center font-bold">3</div>
                            <h2 class="text-lg font-bold text-slate-900">Contact & Arrival</h2>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Email Address *</label>
                                <input type="email" name="email" required placeholder="john@example.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all" />
                            </div>
                            <div>
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">WhatsApp / Phone *</label>
                                <input type="tel" name="phone" required placeholder="+54 9 261..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all" />
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Estimated Time of Arrival (ETA) *</label>
                                <select name="eta" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all">
                                    <option value="">Select expected arrival time</option>
                                    <option value="Morning (08:00 - 12:00)">Morning (08:00 - 12:00)</option>
                                    <option value="Afternoon (12:00 - 18:00)">Afternoon (12:00 - 18:00)</option>
                                    <option value="Evening (18:00 - 22:00)">Evening (18:00 - 22:00)</option>
                                    <option value="Late Night (22:00+)">Late Night (After 22:00)</option>
                                    <option value="I don't know yet">I don't know yet</option>
                                </select>
                                <p class="text-[11px] text-slate-400 mt-2">Standard check-in begins at 14:00. If arriving early, we can hold your bags.</p>
                            </div>

                            <div class="md:col-span-2">
                                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Special Requests / Notes to Staff</label>
                                <textarea name="notes" rows="3" placeholder="Lower bunk preference? Dietary requirements? Let us know..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all resize-none"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-4">
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden sticky top-28">
                        <div class="p-6 bg-slate-900 text-white">
                            <h3 class="text-xl font-serif font-bold mb-1">Booking Summary</h3>
                            <p class="text-white/60 text-sm">Hostel Plaza Mendoza</p>
                        </div>

                        <div class="p-6 space-y-4">
                            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                                <span class="text-slate-500">Price per night</span>
                                <div class="text-right" id="display_night_price">
                                    <span class="font-bold text-slate-900">$0.00</span>
                                    <span class="text-xs font-medium text-slate-400 ml-1">(AR$ 0)</span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
                                <span class="text-slate-500">Total Nights</span>
                                <span class="font-bold text-slate-900" id="display_nights">0</span>
                            </div>
                            <div class="flex justify-between items-end pt-2">
                                <span class="text-sm font-bold uppercase tracking-wider text-teal">Total to pay<br><span class="text-[10px] text-slate-400 font-normal normal-case">Due at check-in</span></span>
                                <div class="text-right" id="display_total">
                                    <span class="text-4xl font-serif font-bold text-teal lining-nums">$0.00</span>
                                    <span class="text-base font-medium text-teal-800/60 block mt-1">AR$ 0</span>
                                </div>
                            </div>

                            <input type="hidden" name="total_price" id="total_price_input" value="0">
                            <input type="hidden" name="total_price_ars" id="total_price_ars_input" value="0">
                        </div>

                        <div class="p-6 bg-slate-50 border-t border-slate-100">
                            <button type="submit" name="submit_booking" id="submit_btn" class="w-full bg-teal text-white py-4 rounded-xl font-bold hover:bg-teal-hover transition-all shadow-lg text-lg flex justify-center items-center gap-2">
                                Confirm Reservation <i data-lucide="arrow-right" class="w-5 h-5"></i>
                            </button>
                            <p class="text-[11px] text-slate-400 text-center mt-4">
                                By confirming, you agree to our cancellation policy. Your data is stored securely.
                            </p>
                        </div>
                    </div>
                </div>

            </form>
        <?php endif; ?>
    </main>

    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: 'en,es,pt,fr,de',
                autoDisplay: false
            }, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <script>
        lucide.createIcons();

        setInterval(function() {
            if (document.body.style.top !== '0px') document.body.style.top = '0px';
            if (document.documentElement.style.top !== '0px') document.documentElement.style.top = '0px';
        }, 50);

        function changeLanguage(langCode, btnElement) {
            var selectField = document.querySelector("#google_translate_element select");
            if(selectField) {
                selectField.value = langCode;
                selectField.dispatchEvent(new Event('change'));
            }
            document.querySelectorAll('.lang-btn').forEach(btn => btn.classList.remove('active'));
            btnElement.classList.add('active');
        }

        const roomSelect = document.getElementById('room_id');
        const checkInInput = document.getElementById('check_in');
        const checkOutInput = document.getElementById('check_out');
        const displayNightPrice = document.getElementById('display_night_price');
        const displayNights = document.getElementById('display_nights');
        const displayTotal = document.getElementById('display_total');
        const totalPriceInput = document.getElementById('total_price_input');
        const totalPriceArsInput = document.getElementById('total_price_ars_input');
        const submitBtn = document.getElementById('submit_btn');

        // --- FALLBACK EXCHANGE RATE INJECTED FROM PHP ---
        const exchangeRate = <?php echo $exchangeRateARS; ?>;

        function calculatePrice() {
            if(!roomSelect || !checkInInput || !checkOutInput) return;

            const checkInDate = new Date(checkInInput.value);
            const checkOutDate = new Date(checkOutInput.value);
            const roomOption = roomSelect.options[roomSelect.selectedIndex];

            let pricePerNight = 0;
            let arsPerNight = 0;

            if (roomOption && roomOption.value !== "") {
                pricePerNight = parseFloat(roomOption.getAttribute('data-price'));
                arsPerNight = parseFloat(roomOption.getAttribute('data-price-ars'));

                if (isNaN(arsPerNight) || arsPerNight <= 0) {
                    arsPerNight = pricePerNight * exchangeRate;
                }

                displayNightPrice.innerHTML = '<span class="font-bold text-slate-900">$' + pricePerNight.toFixed(2) + '</span> <span class="text-xs font-medium text-slate-400 ml-1">(AR$ ' + arsPerNight.toLocaleString('es-AR') + ')</span>';
            } else {
                displayNightPrice.innerHTML = '<span class="font-bold text-slate-900">$0.00</span> <span class="text-xs font-medium text-slate-400 ml-1">(AR$ 0)</span>';
            }

            let nights = 0;
            if (checkInInput.value && checkOutInput.value && checkOutDate > checkInDate) {
                const diffTime = Math.abs(checkOutDate - checkInDate);
                nights = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                displayNights.innerText = nights;

            } else {
                displayNights.innerText = '0';
            }

            const total = nights * pricePerNight;
            const arsTotal = nights * arsPerNight;

            displayTotal.innerHTML = '<span class="text-4xl font-serif font-bold text-teal lining-nums">$' + total.toFixed(2) + '</span><span class="text-base font-medium text-teal-800/60 block mt-1">AR$ ' + arsTotal.toLocaleString('es-AR') + '</span>';

            totalPriceInput.value = total;
            if(totalPriceArsInput) {
                totalPriceArsInput.value = arsTotal;
            }

            if (nights <= 0 || pricePerNight === 0) {
                if(submitBtn) {
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    submitBtn.disabled = true;
                }
            } else {
                if(submitBtn) {
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    submitBtn.disabled = false;
                }
            }
        }

        // --- FLATPICKR CALENDAR BLOCKED DATES ENGINE (BananaDesk live) ---
        // Fallback local (desde bookings.json) por si el endpoint de
        // BananaDesk falla momentáneamente.
        const blockedDatesFallback = <?php echo json_encode($blockedDatesByRoom); ?>;
        // Cache en memoria de respuestas para no re-pegarle al endpoint
        // si el usuario va y vuelve entre habitaciones.
        const bananaCache = {};

        async function fetchBananaAvailability(roomId) {
            if (!roomId) return { unavailable_dates: [], first_available: null };
            if (bananaCache[roomId]) return bananaCache[roomId];
            try {
                const resp = await fetch(`room_availability.php?room_id=${encodeURIComponent(roomId)}&days=30`, { cache: 'no-store' });
                if (!resp.ok) throw new Error('HTTP ' + resp.status);
                const data = await resp.json();
                if (!data || data.ok === false) throw new Error(data && data.error ? data.error : 'invalid');
                bananaCache[roomId] = data;
                return data;
            } catch (e) {
                console.warn('BananaDesk lookup falló, uso fallback local:', e);
                return {
                    unavailable_dates: blockedDatesFallback[roomId] || [],
                    first_available: null,
                    _fallback: true,
                };
            }
        }

        let fpIn, fpOut;
        let calendarsBusy = false;

        function setBookingHint(text, tone = 'info') {
            const el = document.getElementById('booking_hint');
            if (!el) return;
            el.textContent = text || '';
            el.className = 'text-xs mt-2 ' + (tone === 'warn'
                ? 'text-amber-600 font-medium'
                : tone === 'ok'
                    ? 'text-teal font-medium'
                    : 'text-slate-500');
        }

        async function initCalendars({ autoSelect = true } = {}) {
            const selectedRoom = roomSelect ? roomSelect.value : "";

            if (!selectedRoom) {
                if (fpIn) fpIn.destroy();
                if (fpOut) fpOut.destroy();
                fpIn = flatpickr("#check_in", { minDate: "today", dateFormat: "Y-m-d" });
                fpOut = flatpickr("#check_out", { minDate: "today", dateFormat: "Y-m-d" });
                setBookingHint('Choose a room to start booking for available dates.');
                return;
            }

            calendarsBusy = true;
            setBookingHint('Searching availability...', 'info');

            const banana = await fetchBananaAvailability(selectedRoom);
            const disabledDates = banana.unavailable_dates || [];
            const firstAvail = banana.first_available;

            if (fpIn) fpIn.destroy();
            if (fpOut) fpOut.destroy();

            const minDateOut = checkInInput.value ? new Date(checkInInput.value).fp_incr(1) : "today";

            fpIn = flatpickr("#check_in", {
                minDate: "today",
                disable: disabledDates,
                dateFormat: "Y-m-d",
                onChange: function(selectedDates) {
                    const nextDisabled = disabledDates
                        .map(d => new Date(d))
                        .filter(d => d > selectedDates[0])
                        .sort((a, b) => a - b)[0];

                    if (fpOut) fpOut.destroy();
                    fpOut = flatpickr("#check_out", {
                        minDate: new Date(selectedDates[0]).fp_incr(1),
                        maxDate: nextDisabled || null,
                        dateFormat: "Y-m-d",
                        disable: disabledDates,
                        onChange: calculatePrice,
                    });

                    if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
                        fpOut.setDate(new Date(selectedDates[0]).fp_incr(1), true);
                    }
                    calculatePrice();
                },
            });

            fpOut = flatpickr("#check_out", {
                minDate: minDateOut,
                disable: disabledDates,
                dateFormat: "Y-m-d",
                onChange: calculatePrice,
            });

            // Auto-prefill: si no hay fechas seleccionadas, sugerir la primera disponibilidad
            if (autoSelect && firstAvail && !checkInInput.value) {
                fpIn.setDate(firstAvail.check_in, true);
                if (fpOut) fpOut.setDate(firstAvail.check_out, true);
            }

            if (banana._fallback) {
                setBookingHint('No pude consultar BananaDesk; usando datos locales.', 'warn');
            } else if (!firstAvail) {
                setBookingHint('Sin disponibilidad en los próximos 30 días para esta habitación.', 'warn');
            } else {
                setBookingHint('Next availability dates: ' + firstAvail.check_in + ' → ' + firstAvail.check_out, 'ok');
            }
            calendarsBusy = false;
        }

        if (roomSelect) {
            roomSelect.addEventListener('change', async () => {
                checkInInput.value = '';
                checkOutInput.value = '';
                displayNights.innerText = '0';
                displayNightPrice.innerHTML = '<span class="font-bold text-slate-900">$0.00</span> <span class="text-xs font-medium text-slate-400 ml-1">(AR$ 0)</span>';
                displayTotal.innerHTML = '<span class="text-4xl font-serif font-bold text-teal lining-nums">$0.00</span><span class="text-base font-medium text-teal-800/60 block mt-1">AR$ 0</span>';
                if(submitBtn) { submitBtn.classList.add('opacity-50', 'cursor-not-allowed'); submitBtn.disabled = true; }
                await initCalendars();
                calculatePrice();
            });
            initCalendars();
            calculatePrice();
        }
    </script>
</body>
</html>