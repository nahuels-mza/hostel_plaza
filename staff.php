<?php
session_start();

// Ensure staff is logged in
if (!isset($_SESSION['staff_logged_in']) && !isset($_SESSION['admin_logged_in'])) {
    header('Location: login');
    exit;
}

if (empty($_GET['tab'])) {
    header("Location: ?tab=overview");
    exit;
}

// --- 0. COMMUNICATIONS ENGINE & EMAILS ---
function sendHostelEmail($toEmail, $subject, $htmlBody, $fromEmail, $password = '') {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return false;
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Hostel Plaza <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    if (!empty($password)) {
        $smtpHost = "mail.hostelplaza.com.ar"; $smtpPort = 587;
        $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 5);
        if ($socket) {
            stream_set_timeout($socket, 5);
            fgets($socket, 515); fputs($socket, "EHLO hostelplaza.com.ar\r\n"); fgets($socket, 515);
            fputs($socket, "AUTH LOGIN\r\n"); fgets($socket, 515);
            fputs($socket, base64_encode($fromEmail) . "\r\n"); fgets($socket, 515);
            fputs($socket, base64_encode($password) . "\r\n"); $auth = fgets($socket, 515);
            if (strpos($auth, '235') !== false) {
                fputs($socket, "MAIL FROM: <$fromEmail>\r\n"); fgets($socket, 515);
                fputs($socket, "RCPT TO: <$toEmail>\r\n"); fgets($socket, 515);
                fputs($socket, "DATA\r\n"); fgets($socket, 515);
                fputs($socket, "To: <$toEmail>\r\nSubject: $subject\r\n" . $headers . "\r\n\r\n" . $htmlBody . "\r\n.\r\n");
                fgets($socket, 515); fputs($socket, "QUIT\r\n"); fclose($socket);
                return true;
            }
            fclose($socket);
        }
    }
    return @mail($toEmail, $subject, $htmlBody, $headers);
}

function sendCheckoutEmail($toEmail, $guestName) {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return;
    $year = date('Y');
    $htmlBody = "<div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);'><div style='background-color: #0f172a; padding: 40px 20px; text-align: center;'><h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; letter-spacing: 1px;'>HOSTEL PLAZA</h1><p style='color: #10b981; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;'>Mendoza, Argentina</p></div><div style='padding: 40px 30px;'><h2 style='color: #0f172a; font-size: 22px; margin-top: 0;'>Thank you for your stay!</h2><p style='color: #475569; font-size: 16px; line-height: 1.6;'>Hello " . htmlspecialchars($guestName) . ",<br><br>We hope you had a wonderful time in Mendoza. It was an absolute pleasure having you stay with us at Hostel Plaza.</p><p style='color: #475569; font-size: 16px; line-height: 1.6;'>Safe travels on your next adventure, and we hope to see you again very soon!</p><div style='text-align: center; margin-top: 30px;'><a href='https://test.hostelplaza.com.ar' style='display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: 500; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);'>Visit our website</a></div></div><div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'><p style='color: #94a3b8; font-size: 12px; margin: 0;'>© $year Hostel Plaza Mendoza. All rights reserved.</p></div></div>";
    sendHostelEmail($toEmail, "Thank you for staying with us - Hostel Plaza", $htmlBody, "thank-you@hostelplaza.com.ar", "j2J/69K4dT");
}

// --- 1. DIRECT ICAL OTA SYNC ENGINE (Booking.com & Hostelworld) ---
$icalFeeds = [
    'Booking.com' => '', // PASTE BOOKING.COM .ICS LINK HERE
    'Hostelworld' => ''  // PASTE HOSTELWORLD .ICS LINK HERE
];

$bookingsFile = 'bookings.json';
if (!file_exists($bookingsFile)) { file_put_contents($bookingsFile, json_encode([], JSON_PRETTY_PRINT)); }
$bookings = json_decode(@file_get_contents($bookingsFile), true) ?: [];
$existingIds = array_column($bookings, 'id');
$newSyncFound = false;

foreach ($icalFeeds as $sourceName => $icalUrl) {
    if (!empty($icalUrl)) {
        $icalData = @file_get_contents($icalUrl);
        if ($icalData) {
            preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $icalData, $events);
            foreach ($events[0] as $event) {
                preg_match('/UID:(.*)/', $event, $uid);
                preg_match('/SUMMARY:(.*)/', $event, $summary);
                preg_match('/DTSTART(?:;.*?)?:([0-9]{8})/', $event, $dtstart);
                preg_match('/DTEND(?:;.*?)?:([0-9]{8})/', $event, $dtend);

                if (isset($uid[1]) && isset($dtstart[1]) && isset($dtend[1])) {
                    $eventId = 'ical_' . md5(trim($uid[1]));
                    if (!in_array($eventId, $existingIds)) {
                        $rawName = isset($summary[1]) ? trim(str_replace(["\r", "\n", "\\"], "", $summary[1])) : 'OTA Guest';
                        $guestName = str_replace('CLOSED - ', '', $rawName);
                        $ci = date('Y-m-d', strtotime($dtstart[1]));
                        $co = date('Y-m-d', strtotime($dtend[1]));

                        $bookings[] = [
                            'id' => $eventId, 'guestName' => $guestName, 'email' => '', 'phone' => '', 'passport' => '',
                            'checkIn' => $ci, 'checkOut' => $co, 'roomId' => 'unassigned', 'status' => 'Confirmed',
                            'totalPrice' => 0, 'amountPaid' => 0, 'paymentMethod' => 'None', 'nationality' => 'Global',
                            'source' => $sourceName
                        ];
                        $existingIds[] = $eventId;
                        $newSyncFound = true;
                    }
                }
            }
        }
    }
}
if ($newSyncFound) { @file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT)); }

// --- STAFF SCHEDULE DATABASE (READ-ONLY FOR STAFF) ---
$staffFile = 'staff.json';
if (!file_exists($staffFile)) {
    file_put_contents($staffFile, json_encode([], JSON_PRETTY_PRINT));
}
$staffData = json_decode(@file_get_contents($staffFile), true) ?: [];
$daysOfWeek = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

// --- CONFIGURATION ---
$config = json_decode(@file_get_contents('config.json'), true) ?: ["exchangeRateARS" => 1396];
$exchangeRateARS = isset($config['exchangeRateARS']) && (float)$config['exchangeRateARS'] > 0 ? (float)$config['exchangeRateARS'] : 1396;
$today = date('Y-m-d');

// --- AUTO-CHECKOUT ENGINE (STRICT 10:00 AM MENDOZA TIME) ---
date_default_timezone_set('America/Argentina/Mendoza');
$currentDate = date('Y-m-d');
$currentHour = (int)date('H');

$needsSave = false;
foreach ($bookings as &$b) {
    if (isset($b['checkOut'])) {
        $coDate = $b['checkOut'];
        $isPastCheckoutTime = ($currentDate > $coDate) || ($currentDate === $coDate && $currentHour >= 10);
        if ($isPastCheckoutTime) {
            $currentStatus = strtolower($b['status'] ?? '');
            if (in_array($currentStatus, ['confirmed', 'checked in', 'checked-in'])) {
                $b['status'] = 'Checked Out';
                $needsSave = true;
                if(!empty($b['email'])) { sendCheckoutEmail($b['email'], $b['guestName']); }
            }
        }
    }
}
unset($b);
if ($needsSave) { @file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT)); }

// GUEST HISTORY ENGINE
$guestHistory = [];
foreach ($bookings as $b) {
    $em = strtolower(trim($b['email'] ?? $b['guestName']));
    if (!isset($guestHistory[$em])) $guestHistory[$em] = [];
    $guestHistory[$em][] = $b['checkIn'] . ' to ' . $b['checkOut'];
}
foreach ($bookings as &$b) {
    $em = strtolower(trim($b['email'] ?? $b['guestName']));
    $b['stayCount'] = count($guestHistory[$em]);
    $b['pastStays'] = array_values(array_diff($guestHistory[$em], [$b['checkIn'] . ' to ' . $b['checkOut']]));
}
unset($b);

// --- 2. HANDLE ALL FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. Add New Guest Walk-In
    if (isset($_POST['add_guest'])) {
        $newBooking = [
            "id" => uniqid('b_'),
            "roomId" => htmlspecialchars($_POST['room_id']),
            "checkIn" => htmlspecialchars($_POST['check_in']),
            "checkOut" => htmlspecialchars($_POST['check_out']),
            "guestsCount" => htmlspecialchars($_POST['guests_count'] ?? '1'),
            "guestName" => htmlspecialchars($_POST['guest_name']),
            "age" => htmlspecialchars($_POST['age'] ?? ''),
            "gender" => htmlspecialchars($_POST['gender'] ?? ''),
            "nationality" => htmlspecialchars($_POST['nationality']),
            "idType" => htmlspecialchars($_POST['id_type'] ?? ''),
            "idNumber" => htmlspecialchars($_POST['id_number'] ?? ''),
            "phone" => htmlspecialchars($_POST['phone']),
            "email" => htmlspecialchars($_POST['email']),
            "eta" => htmlspecialchars($_POST['eta'] ?? ''),
            "notes" => htmlspecialchars($_POST['notes'] ?? ''),
            // Convert ARS inputs to USD for saving
            "totalPrice" => round((float)$_POST['total_price'] / $exchangeRateARS, 2),
            "amountPaid" => round((float)($_POST['amount_paid'] ?? 0) / $exchangeRateARS, 2),
            "paymentMethod" => htmlspecialchars($_POST['payment_method'] ?? ''),
            "status" => "confirmed"
        ];
        array_unshift($bookings, $newBooking);
        file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
        header("Location: ?tab=reservations");
        exit;
    }

    // B. Staff "Update" - RESTRICTED TO EXTENSION, PAYMENT, AND STATUS ONLY
    elseif (isset($_POST['update_guest'])) {
        $bId = $_POST['booking_id'];
        foreach ($bookings as &$b) {
            if ($b['id'] === $bId) {
                $oldSt = strtolower($b['status'] ?? '');

                if (!empty($_POST['edit_check_out'])) {
                    $b['checkOut'] = date('Y-m-d', strtotime($_POST['edit_check_out']));
                }

                // Allow staff to update payment details (Convert ARS to USD)
                if (isset($_POST['edit_total_price'])) {
                    $b['totalPrice'] = round((float)$_POST['edit_total_price'] / $exchangeRateARS, 2);
                }
                if (isset($_POST['edit_amount_paid'])) {
                    $b['amountPaid'] = round((float)$_POST['edit_amount_paid'] / $exchangeRateARS, 2);
                }
                if (!empty($_POST['edit_payment_method'])) {
                    $b['paymentMethod'] = htmlspecialchars($_POST['edit_payment_method']);
                }

                // Allow staff to update passport / ID details
                if (!empty($_POST['edit_id_type'])) {
                    $b['idType'] = htmlspecialchars($_POST['edit_id_type']);
                }
                if (!empty($_POST['edit_id_number'])) {
                    $b['idNumber'] = htmlspecialchars($_POST['edit_id_number']);
                }

                if(isset($_POST['edit_status'])) {
                    $newSt = strtolower($_POST['edit_status']);
                    $b['status'] = htmlspecialchars($_POST['edit_status']);

                    if ($oldSt !== 'checked out' && ($newSt === 'checked out' || $newSt === 'checked-out')) {
                        if (!empty($b['email'])) { sendCheckoutEmail($b['email'], $b['guestName']); }
                    }
                }
                break;
            }
        }
        file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
        $returnTab = isset($_POST['return_tab']) ? $_POST['return_tab'] : 'guests';
        header("Location: ?tab=" . urlencode($returnTab));
        exit;
    }

    // C. Quick Update Booking Status (Confirm via Action Buttons)
    elseif (isset($_POST['update_status'])) {
        $bId = $_POST['booking_id'];
        $newStatus = $_POST['update_status'];
        foreach ($bookings as &$b) {
            if ($b['id'] === $bId) {
                $b['status'] = $newStatus;
                break;
            }
        }
        file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
        header("Location: ?tab=reservations");
        exit;
    }

    // D. Handle Drag and Drop Calendar Movement INSTANTLY
    elseif (isset($_POST['move_booking'])) {
        $bId = $_POST['booking_id'];
        $rId = $_POST['room_id'];
        $nDate = $_POST['new_date'];

        foreach ($bookings as &$b) {
            if ($b['id'] === $bId) {
                if (isset($_POST['day_delta']) && (int)$_POST['day_delta'] !== 0) {
                    $delta = (int)$_POST['day_delta'];
                    $ci = new DateTime($b['checkIn']); $co = new DateTime($b['checkOut']);
                    if ($delta > 0) { $ci->modify("+$delta days"); $co->modify("+$delta days"); }
                    else { $ci->modify("$delta days"); $co->modify("$delta days"); }
                    $b['checkIn'] = $ci->format('Y-m-d'); $b['checkOut'] = $co->format('Y-m-d');
                } else {
                    $start = new DateTime($b['checkIn']);
                    $end = new DateTime($b['checkOut']);
                    $duration = $start->diff($end)->days;
                    if ($duration < 1) $duration = 1;
                    $b['checkIn'] = $nDate;
                    $newEnd = new DateTime($nDate);
                    $newEnd->modify("+$duration days");
                    $b['checkOut'] = $newEnd->format('Y-m-d');
                }
                $b['roomId'] = $rId;
                break;
            }
        }
        file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
        if (isset($_POST['ajax'])) { echo "ok"; exit; }
    }
}

$activeTab = $_GET['tab'] ?? 'overview';

// --- ROOMS DATABASE ---
$roomsFile = 'rooms.json';
if (!file_exists($roomsFile)) {
    $defaultRooms = [
        [ "id" => "1", "name" => "Double Room with Shared Bathroom", "price" => "From $35", "capacity" => 3 ],
        [ "id" => "2", "name" => "Family Room", "price" => "From $35", "capacity" => 4 ],
        [ "id" => "3", "name" => "4-Bed Female Dorm", "price" => "From $18", "capacity" => 4 ],
        [ "id" => "5", "name" => "4-Bed Mixed Dorm", "price" => "From $18", "capacity" => 4 ],
        [ "id" => "6", "name" => "8-Bed Mixed Dorm", "price" => "From $15", "capacity" => 8 ]
    ];
    file_put_contents($roomsFile, json_encode($defaultRooms, JSON_PRETTY_PRINT));
}
$rooms = json_decode(file_get_contents($roomsFile), true) ?: [];

// Calculate Dashboard Stats
$pendingCount = 0;
$confirmedCount = 0;
foreach ($bookings as $b) {
    $statusCheck = strtolower(trim($b['status'] ?? 'pending'));
    if ($statusCheck === 'pending' || $statusCheck === 'unconfirmed' || $statusCheck === 'un-confirmed') {
        $pendingCount++;
    } elseif ($statusCheck === 'confirmed' || $statusCheck === 'checked in') {
        $confirmedCount++;
    }
}

$viewStart = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$calendarStartDate = new DateTime($viewStart);
$next14Days = [];
for ($i = 0; $i < 14; $i++) {
    $date = clone $calendarStartDate;
    $date->modify("+$i days");
    $next14Days[] = $date;
}

// CALCULATE DAILY AVAILABILITY
$roomAvailability = [];
foreach ($rooms as $room) {
    $roomAvailability[$room['id']] = [];
    $cap = $room['capacity'] ?? 1;
    for ($i = 0; $i < 14; $i++) {
        $currentDate = $next14Days[$i]->format('Y-m-d');
        $bookedBeds = 0;
        foreach ($bookings as $b) {
            if ($b['roomId'] == $room['id'] && strtolower($b['status'] ?? '') !== 'cancelled') {
                if ($currentDate >= $b['checkIn'] && $currentDate < $b['checkOut']) {
                    $bookedBeds++;
                }
            }
        }
        $avail = $cap - $bookedBeds;
        $roomAvailability[$room['id']][$i] = $avail < 0 ? 0 : $avail;
    }
}

function getInitials($name) {
    if (empty($name)) return "G";
    $parts = explode(" ", $name);
    return count($parts) > 1 ? strtoupper($parts[0][0] . $parts[1][0]) : strtoupper($parts[0][0]);
}

// Helper function to assign exact status colors to the calendar pills
function getGuestColorClass($status) {
    $status = strtolower(trim($status));
    if ($status === 'checked in' || $status === 'checked-in') {
        return 'from-purple-500 to-purple-600'; // Purple
    } elseif ($status === 'checked out' || $status === 'checked-out') {
        return 'from-pink-400 to-pink-500'; // Pink
    } elseif ($status === 'confirmed') {
        return 'from-emerald-400 to-emerald-500'; // Green (Confirmed)
    } else {
        return 'from-blue-400 to-blue-500'; // Light Blue (Pending/Unconfirmed)
    }
}

function getStatusClass($status) {
    $s = strtolower(trim($status));
    if ($s === 'checked in' || $s === 'checked-in') return 'bg-purple-500';
    if ($s === 'checked out' || $s === 'checked-out') return 'bg-pink-500';
    if ($s === 'confirmed') return 'bg-emerald-500';
    return 'bg-sky-400';
}

function getSourceBadge($source) {
    $s = strtolower(trim($source));
    if ($s === '' || $s === 'direct') return '';
    if (strpos($s, 'booking') !== false) return '<span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-600 border border-blue-200 text-[8px] font-bold uppercase tracking-widest shadow-sm whitespace-nowrap">Booking.com</span>';
    if (strpos($s, 'hostelworld') !== false) return '<span class="px-2 py-0.5 rounded-md bg-orange-50 text-orange-600 border border-orange-200 text-[8px] font-bold uppercase tracking-widest shadow-sm whitespace-nowrap">Hostelworld.com</span>';
    return '<span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-500 border border-slate-200 text-[8px] font-bold uppercase tracking-widest shadow-sm whitespace-nowrap">' . htmlspecialchars($source) . '</span>';
}

// Background Colors for Room Names
$roomBgColors = ['bg-blue-50/70', 'bg-emerald-50/70', 'bg-amber-50/70', 'bg-purple-50/70', 'bg-rose-50/70', 'bg-indigo-50/70', 'bg-cyan-50/70'];
$avatarColors = ['bg-rose-100 text-rose-700', 'bg-blue-100 text-blue-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700'];

// Calculate Revenue
$revData = ['Cash' => [0,0], 'Visa' => [0,0], 'Mastercard' => [0,0], 'Mercado Pago' => [0,0]];
$totalRev = 0;
foreach ($bookings as $b) {
    if (($b['status']??'') === 'confirmed' || ($b['status']??'') === 'checked in') {
        $p = (float)($b['amountPaid'] ?? 0); $totalRev += $p; $m = $b['paymentMethod'] ?? 'Other';
        if(isset($revData[$m])) { $revData[$m][0] += $p; $revData[$m][1]++; }
    }
}

// --- GENERATE CALENDAR HTML BLOCK ONCE ---
ob_start();
?>
<div class="flex flex-wrap gap-4 mb-6 text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-50 p-4 rounded-2xl border border-slate-200/60 justify-center shadow-inner">
    <div class="flex items-center gap-2"><div class="w-4 h-4 rounded-md bg-gradient-to-r from-blue-400 to-blue-500 shadow-sm"></div> <span class="nav-label notranslate" data-en="Un-Confirmed" data-es="Sin Confirmar">Un-Confirmed</span></div>
    <div class="flex items-center gap-2"><div class="w-4 h-4 rounded-md bg-gradient-to-r from-emerald-400 to-emerald-500 shadow-sm"></div> <span class="nav-label notranslate" data-en="Confirmed" data-es="Confirmado">Confirmed</span></div>
    <div class="flex items-center gap-2"><div class="w-4 h-4 rounded-md bg-gradient-to-r from-purple-500 to-purple-600 shadow-sm"></div> <span class="nav-label notranslate" data-en="Checked In" data-es="Registrado">Checked In</span></div>
    <div class="flex items-center gap-2"><div class="w-4 h-4 rounded-md bg-gradient-to-r from-pink-400 to-pink-500 shadow-sm"></div> <span class="nav-label notranslate" data-en="Checked Out" data-es="Salida">Checked Out</span></div>
</div>

<div class="overflow-x-auto cal-scroll relative">
    <div class="min-w-[1600px] border-b border-slate-200 pb-2 relative z-0">
        <div class="flex border-b border-slate-200 bg-white sticky top-0 z-30 font-bold text-xs text-slate-500 uppercase tracking-wider">
            <div class="w-[280px] p-4 border-r border-slate-100 bg-slate-50/80 backdrop-blur-md nav-label notranslate" data-en="Room" data-es="Habitación">Room</div>
            <div class="flex-1 grid grid-cols-[repeat(14,_1fr)]">
                <?php foreach($next14Days as $date): ?>
                    <?php $isToday = $date->format('Y-m-d') === (new DateTime())->format('Y-m-d'); ?>
                    <div class="p-2 text-center border-r border-slate-100 <?php echo $isToday ? 'bg-teal-light/60' : 'bg-slate-50/60'; ?>">
                        <span class="block text-[10px] text-slate-400 uppercase"><?php echo $date->format('D'); ?></span>
                        <span class="block text-sm font-bold <?php echo $isToday ? 'text-teal' : 'text-slate-700'; ?>"><?php echo $date->format('j'); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        $totalDays = 14;
        $startDate = $next14Days[0];
        $endDatePlusOne = clone $next14Days[13]; $endDatePlusOne->modify('+1 day');

        $calendarRooms = array_merge([['id' => 'unassigned', 'name' => '⚠️ UNASSIGNED (Drag to Room)']], $rooms);
        foreach($calendarRooms as $rIndex => $room):
            $rColor = $room['id'] === 'unassigned' ? 'bg-amber-50 text-amber-700 border-b border-amber-200' : $roomBgColors[$rIndex % count($roomBgColors)];
        ?>
            <div class="flex relative border-b border-slate-100 min-h-[180px] hover:bg-slate-50/50 transition-colors">
                <div class="w-[280px] p-4 font-bold text-sm text-slate-700 border-r border-slate-100 flex items-center shadow-[2px_0_5px_rgba(0,0,0,0.02)] z-20 <?php echo $rColor; ?>">
                    <span class="truncate"><?php echo htmlspecialchars($room['name']); ?></span>
                </div>
                <div class="flex-1 relative bg-white calendar-drop-zone" data-room-id="<?php echo $room['id']; ?>">

                    <div class="absolute inset-0 grid grid-cols-[repeat(14,_1fr)] z-10 pointer-events-none">
                        <?php if($room['id'] !== 'unassigned'): for($i=0; $i<14; $i++):
                            $avail = $roomAvailability[$room['id']][$i];
                            $availText = $avail > 0 ? $avail . ' left' : 'Full';
                            $availClass = $avail > 0 ? 'text-teal/50' : 'text-red-400/70';
                        ?>
                            <div class="drop-zone border-r border-slate-100 h-full w-full bg-transparent transition-colors flex justify-center pt-1.5 pointer-events-auto"
                                 ondragover="allowDrop(event)"
                                 ondragleave="dragLeave(event)"
                                 ondrop="drop(event, '<?php echo $room['id']; ?>', '<?php echo $next14Days[$i]->format('Y-m-d'); ?>')">
                                 <span class="text-[9px] font-bold uppercase tracking-wider select-none <?php echo $availClass; ?> pointer-events-none"><?php echo $availText; ?></span>
                            </div>
                        <?php endfor; endif; ?>
                    </div>

                    <div class="relative z-20 grid grid-cols-[repeat(14,_1fr)] gap-y-1 p-1.5 pt-8 pb-2 pointer-events-none w-full h-full">
                        <?php
                        $roomBookings = [];

                        foreach($bookings as $res) {
                            if($res['roomId'] != $room['id'] || strtolower($res['status']??'') === 'cancelled') continue;
                            $ci = new DateTime($res['checkIn']); $co = new DateTime($res['checkOut']);
                            // FIXED CALENDAR LOGIC: Ensure walk-ins and same-day checkouts render correctly
                            if ($co < $startDate || $ci >= $endDatePlusOne) continue;
                            $roomBookings[] = $res;
                        }
                        usort($roomBookings, function($a, $b) { return strtotime($a['checkIn']) - strtotime($b['checkIn']); });
                        $lanes = [];

                        foreach($roomBookings as $booking):
                            $ci = strtotime($booking['checkIn']); $assignedLane = -1;
                            foreach ($lanes as $laneIndex => $laneEnd) { if ($ci >= $laneEnd) { $assignedLane = $laneIndex; break; } }
                            if ($assignedLane === -1) { $assignedLane = count($lanes); }
                            $booking['lane'] = $assignedLane; $lanes[$assignedLane] = strtotime($booking['checkOut']);

                            $ciDateStr = $booking['checkIn'];
                            $coDateStr = $booking['checkOut'];

                            $startOffset = (strtotime($ciDateStr) - strtotime($startDate->format('Y-m-d'))) / 86400;
                            $endOffset = (strtotime($coDateStr) - strtotime($startDate->format('Y-m-d'))) / 86400;

                            // Visual Split-Cell Shift for realistic check-in/out times
                            $startOffset += 0.5;
                            $endOffset += 0.5;

                            $clampedStart = max(0, $startOffset);
                            $clampedEnd = min($totalDays, $endOffset);

                            $width = $clampedEnd - $clampedStart;
                            if ($width <= 0) $width = 0.2;

                            $leftPct = ($clampedStart / $totalDays) * 100;
                            $widthPct = ($width / $totalDays) * 100;

                            $booking['roomName'] = $room['name'];
                            $bookingJson = htmlspecialchars(json_encode($booking), ENT_QUOTES, 'UTF-8');
                            $topPx = 8 + ($booking['lane'] * 32);

                            $tooltip = "In: {$booking['checkIn']} | Out: {$booking['checkOut']}\nPaid: AR$ " . number_format($booking['amountPaid'] * $exchangeRateARS, 2) . " (" . ($booking['paymentMethod']?:'None') . ")";
                            $pillColor = getGuestColorClass($booking['status'] ?? 'pending');
                        ?>
                                <div id="pill_<?php echo $booking['id']; ?>"
                                     data-booking-id="<?php echo $booking['id']; ?>"
                                     draggable="true"
                                     ondragstart="drag(event, '<?php echo $booking['id']; ?>')"
                                     ondragend="dragEnd(event)"
                                     onclick="openBookingSidebar('<?php echo $booking['id']; ?>'); switchSidebarTab('guest');"
                                     title="<?php echo htmlspecialchars($tooltip); ?>"
                                     class="booking-pill h-8 rounded-md shadow-[0_2px_4px_rgba(0,0,0,0.1)] flex items-center px-3 cursor-pointer pointer-events-auto bg-gradient-to-r <?php echo $pillColor; ?> text-white font-medium text-[11px] hover:shadow-md hover:scale-[1.02] hover:z-50 transition-all active:scale-95"
                                     style="left: <?php echo $leftPct; ?>%; top: <?php echo $topPx; ?>px; width: <?php echo $widthPct; ?>%;">
                                    <span class="whitespace-nowrap pointer-events-none drop-shadow-md z-10"><?php echo htmlspecialchars($booking['guestName']); ?></span>
                                </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php
$calendarHTML = ob_get_clean();
$daysOfWeek = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
?>

<!DOCTYPE html>
<html lang="en" class="overflow-x-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Desk | Hostel Plaza</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">

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
        html, body { overflow-x: hidden !important; width: 100%; position: relative; }
        .tab-content { display: none; animation: fadeIn 0.3s ease-in-out; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .modal-overlay { background-color: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
        .cal-scroll::-webkit-scrollbar { height: 8px; }
        .cal-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 4px; }
        .cal-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

        .booking-pill { position: absolute; z-index: 20; }
        .is-dragging-body * { cursor: grabbing !important; }

        /* --- TRANSLATION STYLES --- */
        #google_translate_element, .goog-te-banner-frame, .skiptranslate, .goog-tooltip, #goog-gt-tt { display: none !important; opacity: 0 !important; visibility: hidden !important; }
        body { top: 0px !important; position: static !important; }
        html { height: auto !important; top: 0px !important; }
        html.translated-ltr, html.translated-rtl { margin-top: 0 !important; padding-top: 0 !important; }
        .goog-text-highlight { background-color: transparent !important; box-shadow: none !important; }
        .lang-btn { transition: all 0.3s ease; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900 min-h-screen flex flex-col md:flex-row overflow-x-hidden">

    <div id="google_translate_element"></div>

    <div class="md:hidden bg-slate-900 text-white p-4 flex justify-between items-center z-30 sticky top-0 shadow-md w-full shrink-0">
        <h1 class="text-xl font-serif font-semibold tracking-tight">Hostel Plaza</h1>
        <button onclick="toggleMobileMenu()" class="p-1 hover:bg-slate-800 rounded-lg"><i data-lucide="menu" class="w-6 h-6"></i></button>
    </div>

    <div id="mobileOverlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden md:hidden" onclick="toggleMobileMenu()"></div>

    <aside id="mainSidebar" class="w-64 bg-slate-900 text-slate-300 flex flex-col fixed inset-y-0 left-0 z-50 shadow-2xl transition-transform transform -translate-x-full md:translate-x-0 h-full">
        <div class="p-6 border-b border-slate-800 shrink-0 flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-serif font-bold text-white tracking-tight">Hostel Plaza</h1>
                <p class="text-[10px] text-teal-400 uppercase tracking-widest mt-1 font-bold">Staff Desk</p>
            </div>
            <button onclick="toggleMobileMenu()" class="md:hidden p-1 text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>
        <nav class="flex-1 py-6 px-4 space-y-2 overflow-y-auto custom-scrollbar" id="sidebarNav">
            <button onclick="switchTab('overview')" class="nav-btn <?php echo $activeTab === 'overview' ? 'active bg-gradient-to-r from-teal to-teal-hover shadow-md text-white font-bold' : 'hover:bg-slate-800 hover:text-white text-slate-300'; ?> w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all" data-target="overview"><i data-lucide="layout-dashboard" class="w-5 h-5 shrink-0 flex-none"></i> <span class="nav-label notranslate text-[15px] whitespace-nowrap truncate" data-en="Overview" data-es="Resumen">Overview</span></button>

            <button onclick="switchTab('schedule')" class="nav-btn <?php echo $activeTab === 'schedule' ? 'active bg-gradient-to-r from-teal to-teal-hover shadow-md text-white font-bold' : 'hover:bg-slate-800 hover:text-white text-slate-300'; ?> w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all" data-target="schedule"><i data-lucide="clock-4" class="w-5 h-5 shrink-0 flex-none"></i> <span class="nav-label notranslate text-[15px] whitespace-nowrap truncate" data-en="Staff Schedule" data-es="Horarios">Staff Schedule</span></button>

            <button onclick="switchTab('calendar')" class="nav-btn <?php echo $activeTab === 'calendar' ? 'active bg-gradient-to-r from-teal to-teal-hover shadow-md text-white font-bold' : 'hover:bg-slate-800 hover:text-white text-slate-300'; ?> w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all" data-target="calendar"><i data-lucide="calendar" class="w-5 h-5 shrink-0 flex-none"></i> <span class="nav-label notranslate text-[15px] whitespace-nowrap truncate" data-en="Booking Calendar" data-es="Calendario">Booking Calendar</span></button>

            <button onclick="switchTab('reservations')" class="nav-btn <?php echo $activeTab === 'reservations' ? 'active bg-gradient-to-r from-teal to-teal-hover shadow-md text-white font-bold' : 'hover:bg-slate-800 hover:text-white text-slate-300'; ?> w-full flex items-center justify-between px-4 py-3 rounded-xl transition-all" data-target="reservations">
                <div class="flex items-center gap-3 overflow-hidden"><i data-lucide="calendar-days" class="w-5 h-5 shrink-0 flex-none"></i> <span class="nav-label notranslate text-[15px] whitespace-nowrap truncate" data-en="Reservations" data-es="Reservas">Reservations</span></div>
                <?php if($pendingCount > 0): ?>
                    <span class="bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm shrink-0 flex-none"><?php echo $pendingCount; ?></span>
                <?php endif; ?>
            </button>

            <button onclick="switchTab('guests')" class="nav-btn <?php echo $activeTab === 'guests' ? 'active bg-gradient-to-r from-teal to-teal-hover shadow-md text-white font-bold' : 'hover:bg-slate-800 hover:text-white text-slate-300'; ?> w-full flex items-center justify-between px-4 py-3 rounded-xl transition-all" data-target="guests">
                <div class="flex items-center gap-3 overflow-hidden"><i data-lucide="users" class="w-5 h-5 shrink-0 flex-none"></i> <span class="nav-label notranslate text-[15px] whitespace-nowrap truncate" data-en="Guests" data-es="Huéspedes">Guests</span></div>
            </button>
        </nav>

        <div class="px-4 pb-2 flex gap-1 notranslate border-t border-slate-700/50 pt-4 shrink-0">
            <button class="lang-btn flex-1 py-2 rounded-lg bg-slate-800 text-xs shadow-inner text-slate-400 hover:text-white transition-colors" onclick="changeAdminLanguage('en', this)">EN</button>
            <button class="lang-btn flex-1 py-2 rounded-lg bg-gradient-to-r from-teal to-[#144042] text-white text-xs font-bold shadow-md transition-colors" onclick="changeAdminLanguage('es', this)">ES</button>
        </div>

        <div class="p-4 space-y-2 shrink-0">
            <button onclick="window.location.reload()" class="w-full flex items-center justify-center gap-2 bg-slate-800/80 text-slate-300 hover:bg-slate-700 hover:text-white px-4 py-3 rounded-xl text-xs font-bold transition-all shadow-sm border border-slate-700">
                <i data-lucide="refresh-cw" class="w-3 h-3 shrink-0 flex-none"></i> <span class="nav-label notranslate whitespace-nowrap truncate" data-en="Refresh Data" data-es="Actualizar">Refresh Data</span>
            </button>
            <button onclick="window.location.href='logout.php'" class="w-full flex items-center justify-center gap-2 bg-gradient-to-r from-teal to-[#144042] text-white hover:shadow-lg hover:-translate-y-0.5 px-4 py-3 rounded-xl font-bold transition-all shadow-md">
                <i data-lucide="log-out" class="w-4 h-4 shrink-0 flex-none"></i> <span class="nav-label notranslate whitespace-nowrap truncate" data-en="Logout" data-es="Salir">Logout</span>
            </button>
        </div>
    </aside>

    <main class="flex-1 md:ml-64 p-6 md:p-10 overflow-y-auto h-screen relative w-full md:max-w-[calc(100%-16rem)]">

        <div id="overview" class="tab-content <?php echo $activeTab === 'overview' ? 'active' : ''; ?>">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-teal/10 rounded-xl flex items-center justify-center text-teal shadow-sm shrink-0 flex-none"><i data-lucide="layout-dashboard" class="w-5 h-5"></i></div>
                <div>
                    <h2 class="text-3xl font-serif font-bold text-slate-900 nav-label notranslate leading-tight" data-en="Staff Overview" data-es="Resumen">Staff Overview</h2>
                    <p class="text-sm text-slate-500 mt-1 nav-label notranslate" data-en="Welcome to your shift. Here is what is happening at Hostel Plaza today." data-es="Bienvenido a tu turno. Esto es lo que sucede hoy.">Welcome to your shift. Here is what is happening at Hostel Plaza today.</p>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 md:gap-6 mb-10">
                <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/50 p-5 border border-emerald-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden flex flex-col justify-center">
                    <div class="absolute -right-3 -top-3 opacity-10 text-emerald-600"><i data-lucide="banknote" class="w-20 h-20"></i></div>
                    <p class="text-[11px] font-bold text-emerald-700 uppercase tracking-wider relative z-10"><span class="nav-label notranslate" data-en="Cash" data-es="Efectivo">Cash</span> (<?php echo $revData['Cash'][1]; ?>)</p>
                    <p class="text-xl font-bold text-emerald-900 mt-1 relative z-10 whitespace-nowrap">AR$ <?php echo number_format($revData['Cash'][0] * $exchangeRateARS, 2); ?></p>
                </div>
                <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 p-5 border border-blue-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden flex flex-col justify-center">
                    <div class="absolute -right-3 -top-3 opacity-10 text-blue-600"><i data-lucide="credit-card" class="w-20 h-20"></i></div>
                    <p class="text-[11px] font-bold text-blue-700 uppercase tracking-wider relative z-10"><span class="nav-label notranslate" data-en="Visa" data-es="Visa">Visa</span> (<?php echo $revData['Visa'][1]; ?>)</p>
                    <p class="text-xl font-bold text-blue-900 mt-1 relative z-10 whitespace-nowrap">AR$ <?php echo number_format($revData['Visa'][0] * $exchangeRateARS, 2); ?></p>
                </div>
                <div class="bg-gradient-to-br from-indigo-50 to-indigo-100/50 p-5 border border-indigo-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden flex flex-col justify-center">
                    <div class="absolute -right-3 -top-3 opacity-10 text-indigo-600"><i data-lucide="credit-card" class="w-20 h-20"></i></div>
                    <p class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider relative z-10"><span class="nav-label notranslate" data-en="Mastercard" data-es="Mastercard">Mastercard</span> (<?php echo $revData['Mastercard'][1]; ?>)</p>
                    <p class="text-xl font-bold text-indigo-900 mt-1 relative z-10 whitespace-nowrap">AR$ <?php echo number_format($revData['Mastercard'][0] * $exchangeRateARS, 2); ?></p>
                </div>
                <div class="bg-gradient-to-br from-cyan-50 to-cyan-100/50 p-5 border border-cyan-200 rounded-2xl shadow-sm hover:shadow-md transition-shadow relative overflow-hidden flex flex-col justify-center">
                    <div class="absolute -right-3 -top-3 opacity-10 text-cyan-600"><i data-lucide="smartphone" class="w-20 h-20"></i></div>
                    <p class="text-[11px] font-bold text-cyan-700 uppercase tracking-wider relative z-10"><span class="nav-label notranslate" data-en="Mercado Pago" data-es="Mercado Pago">Mercado Pago</span> (<?php echo $revData['Mercado Pago'][1]; ?>)</p>
                    <p class="text-xl font-bold text-cyan-900 mt-1 relative z-10 whitespace-nowrap">AR$ <?php echo number_format($revData['Mercado Pago'][0] * $exchangeRateARS, 2); ?></p>
                </div>
                <div class="bg-gradient-to-br from-teal to-[#113537] p-5 border border-teal-hover rounded-2xl shadow-lg relative overflow-hidden text-white flex flex-col justify-center col-span-2 md:col-span-1">
                    <div class="absolute -right-3 -bottom-3 opacity-10"><i data-lucide="wallet" class="w-24 h-24"></i></div>
                    <p class="text-[11px] uppercase tracking-widest text-teal-200 font-bold relative z-10 nav-label notranslate" data-en="Total Revenue" data-es="Ingresos Totales">Total Revenue</p>
                    <p class="text-2xl font-bold mt-1 relative z-10 drop-shadow-md whitespace-nowrap">AR$ <?php echo number_format($totalRev * $exchangeRateARS, 2); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/60 flex items-center gap-5 hover:shadow-md transition-shadow">
                    <div class="w-16 h-16 bg-blue-50 text-blue-500 rounded-2xl flex items-center justify-center shadow-inner shrink-0 flex-none">
                        <i data-lucide="clock" class="w-8 h-8"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider nav-label notranslate leading-tight" data-en="Pending / Unconfirmed" data-es="Pendiente / Sin Confirmar">Pending / Unconfirmed</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo $pendingCount; ?></p>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/60 flex items-center gap-5 hover:shadow-md transition-shadow">
                    <div class="w-16 h-16 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center shadow-inner shrink-0 flex-none">
                        <i data-lucide="check-circle" class="w-8 h-8"></i>
                    </div>
                    <div>
                        <p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider nav-label notranslate leading-tight" data-en="Confirmed / Checked In" data-es="Confirmadas / Registradas">Confirmed / Checked In</p>
                        <p class="text-3xl font-bold text-slate-900 mt-1"><?php echo $confirmedCount; ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div id="schedule" class="tab-content <?php echo $activeTab === 'schedule' ? 'active' : ''; ?>">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-teal/10 rounded-xl flex items-center justify-center text-teal shadow-sm shrink-0 flex-none"><i data-lucide="clock-4" class="w-5 h-5"></i></div>
                <div>
                    <h2 class="text-3xl font-serif font-bold text-slate-900 nav-label notranslate" data-en="Staff Shift Schedule" data-es="Horarios">Staff Shift Schedule</h2>
                    <p class="text-sm text-slate-500 mt-1 nav-label notranslate" data-en="View assigned hours for the week." data-es="Ver horas asignadas para la semana.">View assigned hours for the week.</p>
                </div>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 overflow-hidden mb-12">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[1000px]">
                        <thead class="bg-slate-50/80 border-b border-slate-200 font-bold uppercase text-slate-500 text-[11px] tracking-wider">
                            <tr>
                                <th class="p-5 text-center border-r border-slate-200/60 nav-label notranslate" data-en="Staff Name" data-es="Personal">Staff Name</th>
                                <th class="p-5 text-center border-l border-slate-200/60 text-blue-600 nav-label notranslate" data-en="Mon" data-es="Lun">Mon</th>
                                <th class="p-5 text-center border-l border-slate-200/60 text-indigo-600 nav-label notranslate" data-en="Tue" data-es="Mar">Tue</th>
                                <th class="p-5 text-center border-l border-slate-200/60 text-violet-600 nav-label notranslate" data-en="Wed" data-es="Mié">Wed</th>
                                <th class="p-5 text-center border-l border-slate-200/60 text-purple-600 nav-label notranslate" data-en="Thu" data-es="Jue">Thu</th>
                                <th class="p-5 text-center border-l border-slate-200/60 text-fuchsia-600 nav-label notranslate" data-en="Fri" data-es="Vie">Fri</th>
                                <th class="p-5 text-center border-l border-slate-200/60 text-rose-600 nav-label notranslate" data-en="Sat" data-es="Sáb">Sat</th>
                                <th class="p-5 text-center text-red-600 bg-red-50/30 nav-label notranslate" data-en="Sun" data-es="Dom">Sun</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <?php if(empty($staffData) || !is_array($staffData)): ?>
                                <tr><td colspan="8" class="p-8 text-center text-slate-400 italic font-medium">No schedule posted for this week yet.</td></tr>
                            <?php else: ?>
                                <?php foreach($staffData as $staff): if(is_array($staff) && isset($staff['name'])): ?>
                                    <tr class="hover:bg-slate-50/50 transition-colors">
                                        <td class="px-6 py-6 border-r border-slate-100 bg-slate-50/30">
                                            <div class="flex items-center gap-4">
                                                <div class="w-10 h-10 min-w-[40px] min-h-[40px] rounded-full bg-white border border-slate-200 flex items-center justify-center font-semibold text-slate-700 shadow-sm shrink-0 flex-none notranslate" translate="no">
                                                    <?php echo strtoupper($staff['name'][0] ?? 'S'); ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-slate-900 text-sm truncate"><?php echo htmlspecialchars($staff['name']); ?></p>
                                                    <p class="text-[10px] text-slate-400 font-medium uppercase mt-0.5 truncate"><?php echo htmlspecialchars($staff['email'] ?? ''); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <?php foreach($daysOfWeek as $day):
                                            $shift = $staff['schedule'][$day] ?? 'OFF';
                                            $isOff = ($shift === 'OFF');
                                            $bgClass = $isOff ? "bg-slate-50 text-red-400 border-slate-100" : "bg-emerald-50 text-emerald-700 border-emerald-100 shadow-sm";
                                        ?>
                                            <td class="px-4 py-6">
                                                <div class="mx-auto w-full max-w-[120px] p-2 rounded-2xl transition-all border flex items-center justify-center <?php echo $bgClass; ?>">
                                                    <p class="text-center bg-transparent font-semibold text-xs uppercase m-0 truncate select-none"><?php echo htmlspecialchars($shift); ?></p>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="calendar" class="tab-content <?php echo $activeTab === 'calendar' ? 'active' : ''; ?>">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-teal/10 rounded-xl flex items-center justify-center text-teal shadow-sm shrink-0 flex-none"><i data-lucide="calendar" class="w-5 h-5"></i></div>
                    <div>
                        <h2 class="text-3xl font-serif font-bold text-slate-800 page-title" data-en="Booking Calendar" data-es="Calendario">Booking Calendar</h2>
                        <p class="text-sm text-slate-500 mt-1 nav-label notranslate" data-en="Drag and drop guests to change their dates or reassign rooms instantly." data-es="Arrastra y suelta a los huéspedes para reasignar habitaciones al instante.">Drag and drop guests to change their dates or reassign rooms instantly.</p>
                    </div>
                </div>
                <div class="flex gap-2 bg-white border border-slate-200 p-2 rounded-2xl shadow-sm w-full md:w-auto justify-between shrink-0">
                    <button type="button" onclick="moveCalendar(-7)" class="p-2 hover:bg-slate-50 text-slate-600 rounded-xl transition-all"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                    <span id="calendar_date_range" class="text-sm font-semibold px-4 text-center flex items-center justify-center whitespace-nowrap"><?php echo $startDate->format('M d') . ' - ' . $endDatePlusOne->format('M d, Y'); ?></span>
                    <button type="button" onclick="moveCalendar(7)" class="p-2 hover:bg-slate-50 text-slate-600 rounded-xl transition-all"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200/60 p-4 md:p-8 shadow-sm mb-10 overflow-hidden">
                <div id="calendar_container_ajax">
                    <?php echo $calendarHTML; ?>
                </div>
            </div>
        </div>

        <div id="reservations" class="tab-content <?php echo $activeTab === 'reservations' ? 'active' : ''; ?>">
             <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
                 <div class="flex items-center gap-3">
                     <div class="w-10 h-10 bg-teal/10 rounded-xl flex items-center justify-center text-teal shadow-sm shrink-0 flex-none"><i data-lucide="book-open" class="w-5 h-5"></i></div>
                     <div>
                         <h2 class="text-3xl font-serif font-bold text-slate-800 page-title" data-en="Reservations List" data-es="Lista de Reservas">Reservations List</h2>
                         <p class="text-sm text-slate-500 mt-1 nav-label notranslate" data-en="Manage all bookings and walk-ins." data-es="Gestionar todas las reservas y las visitas sin cita previa.">Manage all bookings and walk-ins.</p>
                     </div>
                 </div>

                 <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
                     <div class="relative w-full sm:w-64 shrink-0"><div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i></div><input type="text" id="reservationSearchInput" onkeyup="filterReservations()" placeholder="Search names..." class="w-full bg-white border border-slate-200/80 rounded-xl pl-10 p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal shadow-sm transition-all shadow-inner"></div>

                     <button onclick="toggleModal('guestModal')" class="w-full sm:w-auto bg-gradient-to-r from-teal to-[#144042] text-white px-6 py-3 rounded-xl font-bold flex items-center justify-center gap-2 shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all whitespace-nowrap shrink-0">
                         <i data-lucide="plus" class="w-5 h-5"></i> <span class="nav-label notranslate" data-en="Add Walk-in" data-es="Añadir Reserva">Add Walk-in</span>
                     </button>
                 </div>
             </div>
             <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 overflow-hidden">
                 <div class="overflow-x-auto">
                     <table class="w-full text-left text-sm min-w-[800px]">
                         <thead class="bg-slate-50/80 border-b border-slate-200 font-bold text-xs uppercase tracking-wider text-slate-500">
                             <tr>
                                 <th class="p-5 nav-label notranslate" data-en="GUEST DETAILS" data-es="DETALLES DEL HUÉSPED">GUEST DETAILS</th>
                                 <th class="p-5 nav-label notranslate" data-en="CHECK IN / OUT" data-es="REGISTRO DE ENTRADA/SALIDA">CHECK IN / OUT</th>
                                 <th class="p-5 nav-label notranslate" data-en="TOTAL & PAID" data-es="TOTAL Y PAGADO">TOTAL & PAID</th>
                                 <th class="p-5 nav-label notranslate" data-en="STATUS" data-es="ESTADO">STATUS</th>
                                 <th class="p-5 text-center nav-label notranslate" data-en="ACTIONS" data-es="ACCIONES">ACTIONS</th>
                             </tr>
                         </thead>
                         <tbody id="reservationsTableBody" class="divide-y divide-slate-100">
                             <?php foreach($bookings as $booking):
                                 $statusCheck = strtolower(trim($booking['status'] ?? ''));
                                 if ($statusCheck === 'checked out' || $statusCheck === 'cancelled') continue;
                                 $badge = getStatusClass($booking['status']);

                                 $lFee = isset($booking['luggage']) ? (float)$booking['luggage']['fee'] : 0;
                                 $dFee = 0; if (!empty($booking['damages'])) { foreach($booking['damages'] as $dam) { $dFee += (float)$dam['fee']; } }
                                 $tP = (float)($booking['totalPrice'] ?? 0) + $lFee + $dFee;
                                 $bal = ($tP - (float)($booking['amountPaid'] ?? 0)) * $exchangeRateARS;

                                 $totalArs = $tP * $exchangeRateARS;
                                 $roomName = 'Unassigned'; foreach($rooms as $rm) { if($rm['id'] == $booking['roomId']) { $roomName = $rm['name']; break; } }
                                 $booking['roomName'] = $roomName;

                                 $currentStatus = strtolower($booking['status'] ?? 'pending');
                                 if($currentStatus === 'unconfirmed' || $currentStatus === 'un-confirmed') $currentStatus = 'pending';

                                 $init = getInitials($booking['guestName']);
                             ?>
                             <tr class="hover:bg-slate-50/50 transition-colors">
                                 <td class="p-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 min-w-[40px] min-h-[40px] flex-none rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-semibold text-sm shrink-0 notranslate" translate="no"><?php echo $init; ?></div>
                                        <div>
                                            <p class="font-bold text-slate-800 text-base flex items-center gap-2"><?php echo htmlspecialchars($booking['guestName']); ?>
                                            <?php if(isset($booking['source']) && strpos($booking['source'], 'Sync') !== false || isset($booking['source']) && $booking['source'] === 'Booking.com' || isset($booking['source']) && $booking['source'] === 'Hostelworld' || isset($booking['source']) && strpos($booking['source'], 'Extension') !== false): ?>
                                                <span class="bg-blue-100 text-blue-700 text-[9px] px-2 py-0.5 rounded uppercase tracking-wider font-bold"><?php echo htmlspecialchars($booking['source']); ?></span>
                                            <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                 </td>
                                 <td class="p-5 text-slate-600 font-medium"><?php echo date('M j', strtotime($booking['checkIn'])); ?> <span class="mx-2 text-slate-300">→</span> <?php echo date('M j', strtotime($booking['checkOut'])); ?></td>
                                 <td class="p-5">
                                     <div class="font-bold text-slate-800 text-base whitespace-nowrap">AR$ <?php echo number_format($totalArs, 0); ?></div>
                                     <?php if($bal > 0): ?>
                                         <div class="text-xs text-rose-500 font-bold bg-rose-50 px-2 py-0.5 rounded-md inline-block mt-1 border border-rose-100 whitespace-nowrap">Bal: AR$ <?php echo number_format($bal, 0); ?></div>
                                     <?php else: ?>
                                         <div class="text-xs text-emerald-600 font-bold bg-emerald-50 px-2 py-0.5 rounded-md inline-block mt-1 border border-emerald-100">Paid (<?php echo htmlspecialchars($booking['paymentMethod']?:'Cash'); ?>)</div>
                                     <?php endif; ?>
                                 </td>
                                 <td class="p-5">
                                     <?php if($currentStatus === 'checked in'): ?>
                                        <span class="px-3 py-1.5 text-[11px] uppercase tracking-wider font-bold rounded-lg border bg-purple-50 text-purple-700 border-purple-200 shadow-sm inline-flex items-center gap-1.5 whitespace-nowrap"><i data-lucide="log-in" class="w-3 h-3"></i> Checked In</span>
                                     <?php elseif($currentStatus === 'checked out'): ?>
                                        <span class="px-3 py-1.5 text-[11px] uppercase tracking-wider font-bold rounded-lg border bg-pink-50 text-pink-700 border-pink-200 shadow-sm inline-flex items-center gap-1.5 whitespace-nowrap"><i data-lucide="log-out" class="w-3 h-3"></i> Checked Out</span>
                                     <?php elseif($currentStatus === 'confirmed'): ?>
                                        <span class="px-3 py-1.5 text-[11px] uppercase tracking-wider font-bold rounded-lg border bg-emerald-50 text-emerald-700 border-emerald-200 shadow-sm inline-flex items-center gap-1.5 whitespace-nowrap"><i data-lucide="check-circle" class="w-3 h-3"></i> Confirmed</span>
                                     <?php else: ?>
                                        <span class="px-3 py-1.5 text-[11px] uppercase tracking-wider font-bold rounded-lg border bg-blue-50 text-blue-700 border-blue-200 shadow-sm inline-flex items-center gap-1.5 whitespace-nowrap"><i data-lucide="clock" class="w-3 h-3"></i> Un-confirmed</span>
                                     <?php endif; ?>
                                 </td>
                                 <td class="p-5 text-center relative">
                                     <button type="button" onclick="toggleActionMenu('menu_res_<?php echo $booking['id']; ?>')" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg transition-all"><i data-lucide="more-vertical" class="w-5 h-5"></i></button>
                                     <div id="menu_res_<?php echo $booking['id']; ?>" class="action-menu hidden absolute right-10 top-8 w-44 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden flex flex-col text-left">
                                         <button type="button" onclick="openBookingSidebar('<?php echo $booking['id']; ?>'); switchSidebarTab('guest');" class="w-full text-left px-4 py-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2"><i data-lucide="eye" class="w-4 h-4 text-slate-400"></i> View Profile</button>
                                         <?php if(!empty($booking['phone'])): $waNum = preg_replace('/[^0-9]/', '', $booking['phone']); ?>
                                             <a href="https://wa.me/<?php echo $waNum; ?>" target="_blank" class="w-full text-left px-4 py-3 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 flex items-center gap-2 border-t border-slate-50"><i data-lucide="message-circle" class="w-4 h-4 text-emerald-500"></i> WhatsApp</a>
                                         <?php endif; ?>
                                         <?php if($currentStatus === 'pending'): ?>
                                            <form method="POST" action="" class="inline m-0 w-full border-t border-slate-50">
                                                <input type="hidden" name="update_status" value="confirmed">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" class="w-full text-left px-4 py-3 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 flex items-center gap-2" title="Confirm Booking"><i data-lucide="check" class="w-4 h-4 text-emerald-500"></i> Confirm Booking</button>
                                            </form>
                                         <?php endif; ?>
                                     </div>
                                 </td>
                             </tr>
                             <?php endforeach; ?>
                         </tbody>
                     </table>
                 </div>
             </div>
        </div>

        <div id="guests" class="tab-content <?php echo $activeTab === 'guests' ? 'active' : ''; ?>">
             <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-8">
                 <div class="flex items-center gap-3">
                     <div class="w-10 h-10 bg-teal/10 rounded-xl flex items-center justify-center text-teal shadow-sm shrink-0 flex-none"><i data-lucide="users" class="w-5 h-5"></i></div>
                     <div>
                         <h2 class="text-3xl font-serif font-bold text-slate-800 page-title" data-en="Guest Directory" data-es="Directorio y Pagos">Guest Directory</h2>
                         <p class="text-sm text-slate-500 mt-1 nav-label notranslate" data-en="Full breakdown of everyone who owes money." data-es="Desglose completo de quién debe dinero.">Full breakdown of everyone who owes money.</p>
                     </div>
                 </div>
                 <div class="flex flex-col sm:flex-row items-center gap-4 w-full md:w-auto">
                     <div class="relative w-full md:w-72">
                         <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i data-lucide="search" class="h-4 w-4 text-slate-400"></i></div>
                         <input type="text" id="guestSearchInput" onkeyup="searchGuests()" placeholder="Search names or email..." class="w-full bg-white border border-slate-200/80 rounded-xl pl-10 p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal shadow-sm transition-all shadow-inner">
                         <div id="guestSearchDropdown" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-slate-100 z-50 hidden max-h-64 overflow-y-auto"></div>
                     </div>
                 </div>
             </div>

             <div class="bg-white rounded-3xl shadow-sm border border-slate-200/60 overflow-hidden">
                 <div class="overflow-x-auto">
                     <table class="w-full text-left text-sm min-w-[1000px]">
                         <thead class="bg-slate-50/80 border-b border-slate-200 font-bold text-[11px] uppercase text-slate-500 tracking-wider">
                             <tr>
                                 <th class="p-5 nav-label notranslate" data-en="NAME & NATIONALITY" data-es="NOMBRE Y NACIONALIDAD">NAME & NATIONALITY</th>
                                 <th class="p-5 nav-label notranslate" data-en="CONTACT INFO" data-es="CONTACTO">CONTACT INFO</th>
                                 <th class="p-5 nav-label notranslate" data-en="ROOM & DATES" data-es="HABITACIÓN Y FECHas">ROOM & DATES</th>
                                 <th class="p-5 text-right nav-label notranslate" data-en="TOTAL DUE" data-es="TOTAL A PAGAR">TOTAL DUE</th>
                                 <th class="p-5 text-right nav-label notranslate" data-en="CURRENT BALANCE" data-es="SALDO ACTUAL">CURRENT BALANCE</th>
                                 <th class="p-5 text-center nav-label notranslate" data-en="ACTIONS" data-es="ACCIONES">ACTIONS</th>
                             </tr>
                         </thead>
                         <tbody id="guestsTableBody" class="divide-y divide-slate-100">
                             <?php foreach($bookings as $b):
                                 $lFee = isset($b['luggage']) ? (float)$b['luggage']['fee'] : 0;
                                 $dFee = 0; if (!empty($b['damages'])) { foreach($b['damages'] as $dam) { $dFee += (float)$dam['fee']; } }
                                 $tPrice = (float)($b['totalPrice'] ?? 0) + $lFee + $dFee;
                                 $bal = ($tPrice - (float)($b['amountPaid'] ?? 0)) * $exchangeRateARS;
                                 $rName = 'Unassigned'; foreach($rooms as $rm) { if($rm['id'] == $b['roomId']) { $rName = $rm['name']; break; } }

                                 $colorClass = $avatarColors[strlen($b['guestName']) % 5];

                                 $stCheck = strtolower(trim($b['status'] ?? ''));
                                 $statusBorder = '';
                                 if ($stCheck === 'confirmed') $statusBorder = 'border-l-4 border-emerald-400';
                                 elseif ($stCheck === 'checked in' || $stCheck === 'checked-in') $statusBorder = 'border-l-4 border-purple-400';
                                 elseif ($stCheck === 'checked out' || $stCheck === 'checked-out') $statusBorder = 'border-l-4 border-pink-400';
                                 else $statusBorder = 'border-l-4 border-sky-400';
                             ?>
                             <tr class="hover:bg-slate-50/50 transition-colors group guest-row <?php echo $statusBorder; ?>" data-name="<?php echo htmlspecialchars($b['guestName']); ?>" data-email="<?php echo htmlspecialchars($b['email']); ?>">
                                 <td class="p-5 pl-6">
                                     <div class="flex items-center gap-3">
                                         <div class="w-9 h-9 min-w-[36px] min-h-[36px] flex-none rounded-full <?php echo $colorClass; ?> flex items-center justify-center font-bold text-sm shrink-0 shadow-sm notranslate" translate="no"><?php echo strtoupper($b['guestName'][0]); ?></div>
                                         <div class="min-w-0">
                                             <p class="font-bold text-slate-900 text-sm whitespace-normal leading-tight flex items-center flex-wrap gap-1.5"><?php echo htmlspecialchars($b['guestName']); ?> <?php echo getSourceBadge($b['source'] ?? ''); ?></p>
                                             <p class="text-[10px] font-bold text-slate-400 uppercase mt-0.5"><?php echo htmlspecialchars($b['nationality'] ?? 'Global'); ?></p>
                                         </div>
                                     </div>
                                 </td>
                                 <td class="p-5 text-slate-500 text-xs space-y-2">
                                     <?php if(!empty($b['email'])): ?><div class="flex items-center gap-2"><i data-lucide="mail" class="w-3.5 h-3.5 text-teal"></i> <span class="font-medium truncate max-w-[150px]"><?php echo htmlspecialchars($b['email']); ?></span></div><?php endif; ?>
                                     <?php if(!empty($b['phone'])): ?><div class="flex items-center gap-2"><i data-lucide="phone" class="w-3.5 h-3.5 text-teal"></i> <span class="font-medium truncate max-w-[150px]"><?php echo htmlspecialchars($b['phone']); ?></span></div><?php endif; ?>
                                 </td>
                                 <td class="p-5 text-xs text-slate-600"><div class="font-bold text-slate-700 truncate max-w-[150px]"><?php echo htmlspecialchars($rName); ?></div><div class="text-slate-500 font-medium mt-1 bg-slate-100 px-2 py-0.5 rounded inline-block"><?php echo date('M j', strtotime($b['checkIn'])); ?> <span class="mx-1 text-slate-300">→</span> <?php echo date('M j', strtotime($b['checkOut'])); ?></div></td>
                                 <td class="p-5 text-right font-bold text-slate-800 text-base whitespace-nowrap">AR$ <?php echo number_format($tPrice * $exchangeRateARS, 0); ?></td>
                                 <td class="p-5 text-right whitespace-nowrap">
                                     <?php if ($bal <= 0): ?>
                                         <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-slate-100 text-slate-500 border border-slate-200 inline-block shadow-sm uppercase tracking-wider">Settled</span>
                                     <?php else: ?>
                                         <span class="px-2.5 py-1 text-[11px] font-bold rounded-lg bg-rose-50 text-rose-600 border border-rose-100 inline-block shadow-sm uppercase tracking-wider">Owes AR$ <?php echo number_format($bal); ?></span>
                                     <?php endif; ?>
                                 </td>
                                 <td class="p-5 text-center relative">
                                     <button type="button" onclick="toggleActionMenu('menu_<?php echo $b['id']; ?>')" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg transition-all"><i data-lucide="more-vertical" class="w-5 h-5"></i></button>
                                     <div id="menu_<?php echo $b['id']; ?>" class="action-menu hidden absolute right-10 top-8 w-44 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden flex flex-col text-left">
                                         <button type="button" onclick="openBookingSidebar('<?php echo $b['id']; ?>'); switchSidebarTab('guest');" class="w-full text-left px-4 py-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2"><i data-lucide="eye" class="w-4 h-4 text-slate-400"></i> View Profile</button>
                                         <?php if(!empty($b['phone'])): $waNum = preg_replace('/[^0-9]/', '', $b['phone']); ?>
                                             <a href="https://wa.me/<?php echo $waNum; ?>" target="_blank" class="w-full text-left px-4 py-3 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 flex items-center gap-2 border-t border-slate-50"><i data-lucide="message-circle" class="w-4 h-4 text-emerald-500"></i> WhatsApp</a>
                                         <?php endif; ?>
                                         <button type="button" onclick="openPaymentModal('<?php echo $b['id']; ?>', 'guests')" class="w-full text-left px-4 py-3 text-xs font-semibold text-blue-600 hover:bg-blue-50 flex items-center gap-2 border-t border-slate-50"><i data-lucide="banknote" class="w-4 h-4 text-blue-500"></i> Log Payment</button>
                                     </div>
                                 </td>
                             </tr>
                         <?php endforeach; ?>
                     </tbody>
                 </table>
             </div>
        </div>

    </main>

    <div id="sbOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden transition-opacity opacity-0" onclick="closeBookingSidebar()"></div>
    <div id="bookingSidebar" class="fixed right-0 top-0 h-screen w-full md:w-[420px] bg-white shadow-2xl z-[110] border-l flex flex-col transform translate-x-full transition-transform duration-300">
        <div class="p-6 border-b flex items-center justify-between bg-slate-50/50">
            <div><h3 class="text-xl font-semibold text-slate-900" id="sb_guest_name">Name</h3><p class="text-[10px] text-slate-400 uppercase tracking-widest mt-1 font-medium" id="sb_reservation_id">#ID</p></div>
            <button type="button" onclick="closeBookingSidebar()" class="p-2 hover:bg-white rounded-xl border text-slate-400"><i data-lucide="x"></i></button>
        </div>
        <div class="flex border-b px-6">
            <button type="button" onclick="switchSidebarTab('operations')" id="btn_sb_ops" class="sidebar-tab flex items-center gap-2 px-4 py-4 text-sm font-medium sidebar-tab-active transition-all">Operations</button>
            <button type="button" onclick="switchSidebarTab('guest')" id="btn_sb_guest" class="sidebar-tab flex items-center gap-2 px-4 py-4 text-sm font-medium border-transparent text-slate-400 hover:text-slate-600 transition-all">Guest Profile</button>
        </div>

        <div class="flex-1 overflow-y-auto p-6 space-y-6 bg-slate-50/30">
            <div id="view_sb_operations" class="sidebar-tab-view space-y-5">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-3">Status</p>
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex items-center gap-3">
                        <div class="w-3.5 h-3.5 rounded-full shadow-sm" id="sb_status_dot"></div>
                        <span class="text-sm font-semibold capitalize text-slate-700" id="sb_status_label">Status</span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm"><p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Check-in</p><p class="text-sm font-semibold text-slate-900" id="sb_ci"></p></div>
                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm"><p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1.5">Check-out</p><p class="text-sm font-semibold text-slate-900" id="sb_co"></p></div>
                </div>
                <div class="p-6 bg-emerald-50 rounded-2xl border border-emerald-100 shadow-sm space-y-4">
                    <div class="flex justify-between items-center"><span class="text-[10px] font-bold uppercase tracking-widest text-emerald-800">Total Price</span><span class="text-base font-bold text-emerald-900" id="sb_total"></span></div>
                    <div class="h-px w-full bg-emerald-200/60"></div>
                    <div class="flex justify-between items-center"><span class="text-[10px] font-bold uppercase tracking-widest text-rose-600">Balance Due</span><span class="text-base font-bold text-rose-600" id="sb_balance"></span></div>
                </div>
                <div id="sb_luggage_container"></div>
                <div id="sb_damages_container"></div>

                <div class="pt-2 space-y-3">
                    <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-widest px-1">Quick Actions</h4>
                    <div class="grid grid-cols-1 gap-3">
                        <button type="button" onclick="openPaymentModal(currentBookingId, 'reservations')" class="flex flex-col items-center justify-center p-4 bg-white border border-slate-200 rounded-2xl shadow-sm hover:border-emerald-300 transition-all group"><i data-lucide="edit" class="w-5 h-5 text-slate-400 group-hover:text-emerald-500 mb-2"></i><span class="text-[10px] uppercase text-slate-600 font-bold">Edit Details & Payments</span></button>
                    </div>
                </div>
            </div>

            <div id="view_sb_guest" class="sidebar-tab-view space-y-5 hidden">
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 text-center">
                    <div class="w-16 h-16 min-w-[64px] min-h-[64px] flex-none rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-medium mx-auto mb-3 shadow-inner notranslate" translate="no" id="sb_initials">G</div>
                    <h4 class="text-xl font-semibold text-slate-900" id="sb_guest_name_p">Name</h4>
                    <div class="flex justify-center items-center gap-2 mt-2">
                        <p class="text-[9px] px-2 py-1 rounded-lg bg-amber-50 text-amber-600 border border-amber-100 uppercase flex justify-center items-center gap-1 font-bold" id="sb_loyalty_badge"><i data-lucide="award" class="w-3 h-3"></i> <span id="sb_stay_count">1st Stay</span></p>
                        <div id="sb_source_badge_container"></div>
                    </div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 space-y-4">
                    <h5 class="text-[10px] text-slate-400 uppercase border-b border-slate-50 pb-2 font-bold tracking-widest">Contact & Identity</h5>
                    <div class="grid grid-cols-2 gap-4">
                        <div><p class="text-[9px] text-slate-400 uppercase font-bold tracking-widest">Phone</p><p class="text-sm font-semibold text-slate-800 break-all mt-1" id="sb_phone">Pending</p></div>
                        <div><p class="text-[9px] text-slate-400 uppercase font-bold tracking-widest">Passport / ID</p><p class="text-sm font-semibold text-slate-800 break-all mt-1" id="sb_passport">Pending</p></div>
                    </div>
                    <div class="pt-2"><p class="text-[9px] text-slate-400 uppercase font-bold tracking-widest">Nationality</p><p class="text-sm text-slate-800 font-medium mt-1" id="sb_nat">Global</p></div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 space-y-3">
                    <h5 class="text-[10px] text-slate-400 uppercase border-b border-slate-50 pb-2 font-bold tracking-widest">Payment Method</h5>
                    <div><p class="text-sm font-medium text-slate-800" id="sb_pay_method">None</p></div>
                </div>
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 space-y-3">
                    <h5 class="text-[10px] text-slate-400 uppercase border-b border-slate-50 pb-2 font-bold tracking-widest">Stay History</h5>
                    <div id="sb_history_list" class="space-y-1"></div>
                </div>
            </div>
        </div>
        <div class="p-6 border-t bg-white grid grid-cols-1 sticky bottom-0 z-20">
            <form method="POST" class="m-0 w-full" action=""><input type="hidden" name="update_status" value="confirmed"><input type="hidden" name="booking_id" id="quick_conf_id"><button type="submit" class="w-full py-3 bg-emerald-500 text-white rounded-xl text-xs uppercase shadow-sm hover:bg-emerald-600 font-semibold flex items-center justify-center gap-2"><i data-lucide="check" class="w-4 h-4"></i> Confirm Status</button></form>
        </div>
    </div>

    <div id="guestHistoryModal" class="fixed inset-0 modal-overlay z-[250] hidden items-center justify-center p-4 transition-opacity opacity-0">
        <div class="bg-white rounded-[2.5rem] max-w-4xl w-full flex flex-col md:flex-row overflow-hidden shadow-2xl h-[600px] border border-slate-200 transform scale-95 transition-transform duration-300 modal-content">
            <div class="w-full md:w-80 bg-slate-50 border-r border-slate-100 p-8 flex flex-col items-center text-center shrink-0 overflow-y-auto">
                <div class="w-24 h-24 min-w-[96px] min-h-[96px] flex-none rounded-[1.5rem] bg-teal-100 text-teal-700 flex items-center justify-center text-4xl font-serif font-bold mb-4 shadow-inner notranslate" translate="no" id="gh_initial">G</div>
                <h2 class="text-2xl font-bold text-slate-900 leading-tight" id="gh_name">Guest Name</h2>
                <div id="gh_vip_badge" class="mt-3 px-3 py-1.5 bg-amber-100 text-amber-700 border border-amber-200 rounded-lg text-[10px] font-bold uppercase tracking-widest hidden flex items-center gap-1.5 shadow-sm"><i data-lucide="crown" class="w-3.5 h-3.5"></i> VIP Guest</div>

                <div class="w-full mt-8 space-y-5 text-left">
                    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm"><p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Email</p><p class="text-sm font-semibold text-slate-800 break-all" id="gh_email"></p></div>
                    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm"><p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Phone</p><p class="text-sm font-semibold text-slate-800" id="gh_phone"></p></div>
                    <div class="h-px bg-slate-200/60 my-4 w-full"></div>
                    <div class="flex justify-between items-center px-1"><p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Stays</p><p class="text-xl font-bold text-slate-900" id="gh_stay_count"></p></div>
                    <div class="flex justify-between items-center px-1"><p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Total Spent</p><p class="text-xl font-bold text-teal-600" id="gh_total_spent"></p></div>
                </div>
            </div>
            <div class="flex-1 flex flex-col bg-white overflow-hidden">
                <div class="p-6 md:p-8 border-b border-slate-100 flex justify-between items-center bg-white z-10 shadow-[0_4px_10px_-4px_rgba(0,0,0,0.05)]">
                    <h3 class="text-xl font-bold text-slate-800">Complete Stay History</h3>
                    <button type="button" onclick="closeModal('guestHistoryModal')" class="p-2.5 text-slate-400 hover:text-slate-800 bg-slate-50 rounded-full transition-colors border border-slate-200 hover:border-slate-300"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 md:p-8 bg-slate-50/50" id="gh_stays_container">
                </div>
            </div>
        </div>
    </div>

    <div id="guestModal" class="fixed inset-0 modal-overlay hidden items-center justify-center p-4 z-50 transition-opacity opacity-0">
        <div class="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto transform scale-95 transition-transform duration-300 modal-content relative border border-slate-200">
            <button onclick="toggleModal('guestModal')" class="absolute top-6 right-6 p-2 bg-slate-50 hover:bg-slate-100 rounded-full transition-colors text-slate-400 border border-slate-200">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
            <div class="p-8 border-b border-slate-100 flex items-center gap-4 bg-slate-50/50">
                <div class="w-12 h-12 bg-gradient-to-br from-teal to-teal-hover text-white rounded-2xl flex items-center justify-center shadow-md shrink-0 flex-none"><i data-lucide="user-plus" class="w-6 h-6"></i></div>
                <div>
                    <h2 class="text-2xl font-serif font-bold text-slate-900 nav-label notranslate" data-en="Add Walk-in Guest" data-es="Añadir Huésped (Walk-in)">Add Walk-in Guest</h2>
                    <p class="text-sm text-slate-500 nav-label notranslate" data-en="Register a new booking manually." data-es="Registrar una nueva reserva manualmente.">Register a new booking manually.</p>
                </div>
            </div>
            <form method="POST" action="" class="p-8 space-y-8">
                <input type="hidden" name="add_guest" value="1">

                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-widest text-teal border-b border-slate-100 pb-2 mb-4 flex items-center gap-2"><i data-lucide="bed" class="w-4 h-4"></i> <span class="nav-label notranslate" data-en="Stay Details" data-es="Detalles de Estancia">Stay Details</span></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Select Room" data-es="Seleccionar Habitación">Select Room</label>
                            <select name="room_id" required class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner">
                                <option value="" disabled selected class="nav-label notranslate" data-en="Choose a room..." data-es="Elige una habitación...">Choose a room...</option>
                                <?php foreach($rooms as $rm): ?>
                                    <option value="<?php echo $rm['id']; ?>"><?php echo htmlspecialchars($rm['name']); ?> (<?php echo htmlspecialchars($rm['price']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Number of Guests" data-es="Número de Huéspedes">Number of Guests</label>
                            <input type="number" name="guests_count" value="1" min="1" required class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Check-In Date" data-es="Fecha de Entrada">Check-In Date</label>
                            <input type="date" name="check_in" value="<?php echo date('Y-m-d'); ?>" required class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Check-Out Date" data-es="Fecha de Salida">Check-Out Date</label>
                            <input type="date" name="check_out" value="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-widest text-teal border-b border-slate-100 pb-2 mb-4 flex items-center gap-2"><i data-lucide="user" class="w-4 h-4"></i> <span class="nav-label notranslate" data-en="Primary Guest Information" data-es="Información del Huésped Principal">Primary Guest Information</span></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Full Name" data-es="Nombre Completo">Full Name</label>
                            <input type="text" name="guest_name" required placeholder="John Doe" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Nationality" data-es="Nacionalidad">Nationality</label>
                            <input type="text" name="nationality" required placeholder="e.g. British, Argentine" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="ID Type & Number" data-es="Tipo y Número de ID">ID Type & Number</label>
                            <div class="flex gap-2">
                                <select name="id_type" class="w-1/3 border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner">
                                    <option value="Passport">Passport</option>
                                    <option value="DNI">DNI</option>
                                    <option value="Driver License">License</option>
                                </select>
                                <input type="text" name="id_number" placeholder="AB1234567" class="w-2/3 border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Age" data-es="Edad">Age</label>
                            <input type="number" name="age" placeholder="25" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Gender" data-es="Género">Gender</label>
                            <select name="gender" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner">
                                <option value="">Select...</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Phone Number" data-es="Número de Teléfono">Phone Number</label>
                            <input type="text" name="phone" required placeholder="+54 9 11 1234-5678" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Email Address" data-es="Correo Electrónico">Email Address</label>
                            <input type="email" name="email" required placeholder="guest@example.com" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-widest text-teal border-b border-slate-100 pb-2 mb-4 flex items-center gap-2"><i data-lucide="wallet" class="w-4 h-4"></i> <span class="nav-label notranslate" data-en="Payment Details" data-es="Detalles de Pago">Payment Details</span></h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 bg-slate-50 p-5 rounded-2xl border border-slate-200/60">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Total Price (ARS)" data-es="Precio Total (ARS)">Total Price (ARS)</label>
                            <input type="number" step="0.01" name="total_price" required placeholder="100000" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-lg font-bold text-teal outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Amount Paid Now (ARS)" data-es="Monto Pagado Ahora (ARS)">Amount Paid Now (ARS)</label>
                            <input type="number" step="0.01" name="amount_paid" placeholder="50000" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-lg font-bold text-emerald-600 outline-none focus:ring-2 focus:ring-emerald-500/30 focus:border-emerald-500 transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1 md:col-span-2">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Payment Method" data-es="Método de Pago">Payment Method</label>
                            <select name="payment_method" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner">
                                <option value="None">None (Pay Later)</option>
                                <option value="Cash">Cash</option>
                                <option value="Visa">Visa</option>
                                <option value="Mastercard">Mastercard</option>
                                <option value="Mercado Pago">Mercado Pago</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-[11px] font-bold uppercase tracking-widest text-teal border-b border-slate-100 pb-2 mb-4 flex items-center gap-2"><i data-lucide="message-square" class="w-4 h-4"></i> <span class="nav-label notranslate" data-en="Additional Information" data-es="Información Adicional">Additional Information</span></h3>
                    <div class="grid grid-cols-1 gap-5">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Estimated Time of Arrival (ETA)" data-es="Hora Estimada de Llegada (ETA)">Estimated Time of Arrival (ETA)</label>
                            <input type="time" name="eta" class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Notes / Special Requests" data-es="Notas / Peticiones Especiales">Notes / Special Requests</label>
                            <textarea name="notes" rows="2" placeholder="Any dietary requirements, early check-in requests, etc." class="w-full border border-slate-300 bg-white rounded-xl p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end gap-4 border-t border-slate-100">
                    <button type="button" onclick="toggleModal('guestModal')" class="px-6 py-3 font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition-colors shadow-sm border border-slate-200 nav-label notranslate" data-en="Cancel" data-es="Cancelar">Cancel</button>
                    <button type="submit" class="bg-gradient-to-r from-teal to-[#144042] text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md nav-label notranslate" data-en="Confirm Booking" data-es="Confirmar Reserva">Confirm Booking</button>
                </div>
            </form>
        </div>
    </div>

    <div id="payGuestModal" class="fixed inset-0 modal-overlay hidden items-center justify-center p-4 z-50 transition-opacity opacity-0">
        <div class="bg-white rounded-3xl shadow-2xl max-w-2xl w-full transform scale-95 transition-transform duration-300 modal-content border border-slate-200">
            <div class="border-b border-slate-100 p-6 flex justify-between items-center bg-slate-50 rounded-t-3xl">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center shadow-inner shrink-0 flex-none"><i data-lucide="edit-3" class="w-5 h-5"></i></div>
                    <div>
                        <h2 class="text-xl font-serif font-bold text-slate-900 nav-label notranslate leading-tight" data-en="Edit Payment & Info" data-es="Editar Pago e Info">Edit Payment & Info</h2>
                        <p class="text-xs text-slate-500 nav-label notranslate" data-en="Update payment collected and confirm guest status." data-es="Actualizar pago y estado de reserva.">Update payment collected and confirm guest status.</p>
                    </div>
                </div>
                <button onclick="toggleModal('payGuestModal')" class="p-2 hover:bg-slate-200 rounded-full transition-colors text-slate-500">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" action="" class="p-6 space-y-6">
                <input type="hidden" name="update_guest" value="1">
                <input type="hidden" name="booking_id" id="pay_booking_id">
                <input type="hidden" name="return_tab" id="pay_return_tab">

                <div class="grid grid-cols-2 gap-4 mb-2 p-4 bg-slate-50 rounded-xl border border-slate-100">
                    <div class="min-w-0">
                        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider nav-label notranslate" data-en="Guest Name" data-es="Nombre">Guest Name</p>
                        <p class="font-bold text-slate-900 text-base truncate" id="pay_guest_name"></p>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-500 uppercase font-bold tracking-wider nav-label notranslate" data-en="Total Base Price (ARS)" data-es="Precio Base (ARS)">Total Base Price (ARS)</p>
                        <input type="number" step="0.01" name="edit_total_price" id="pay_total_price" required class="w-full bg-transparent border-b border-slate-300 font-bold text-teal text-lg outline-none focus:border-teal transition-all">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1 bg-teal/5 p-4 rounded-xl border border-teal/20">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-teal ml-1 nav-label notranslate flex items-center gap-1.5" data-en="Check Out Date (Extend Stay)" data-es="Fecha de Salida (Extender Estancia)"><i data-lucide="calendar-plus" class="w-3 h-3"></i> Check Out Date (Extend Stay)</label>
                        <input type="date" name="edit_check_out" id="pay_check_out" required class="w-full bg-white border border-teal/20 rounded-lg p-3 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-teal/40 outline-none shadow-sm transition-all mt-1">
                    </div>
                    <div class="space-y-1 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Booking Status" data-es="Estado">Booking Status</label>
                        <select name="edit_status" id="pay_status" class="w-full border border-slate-300 bg-white rounded-lg p-3 text-sm outline-none focus:ring-2 focus:ring-teal transition-all shadow-inner">
                            <option value="pending">Un-confirmed / Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="checked in">Checked In</option>
                            <option value="checked out">Checked Out</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Amount Paid (ARS)" data-es="Monto Pagado (ARS)">Amount Paid (ARS)</label>
                        <input type="number" step="0.01" name="edit_amount_paid" id="pay_amount_paid" class="w-full border border-slate-300 bg-white rounded-lg p-3 outline-none font-bold text-emerald-600 text-base focus:ring-2 focus:ring-emerald-500 transition-all shadow-inner" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="Payment Method" data-es="Método de Pago">Payment Method</label>
                        <select name="edit_payment_method" id="pay_payment_method" class="w-full border border-slate-300 bg-white rounded-lg p-3 text-sm outline-none focus:ring-2 focus:ring-emerald-500 transition-all shadow-inner">
                            <option value="">None / Pending</option>
                            <option value="Cash">Cash</option>
                            <option value="Visa">Visa</option>
                            <option value="Mastercard">Mastercard</option>
                            <option value="Mercado Pago">Mercado Pago</option>
                        </select>
                    </div>
                </div>

                <h3 class="text-[10px] font-bold uppercase tracking-widest text-teal border-b border-slate-100 pb-2 mt-4 nav-label notranslate" data-en="Identity Details" data-es="Detalles de Identidad">Identity Details</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="ID Type" data-es="Tipo de Documento">ID Type</label>
                        <select name="edit_id_type" id="pay_id_type" class="w-full border border-slate-300 bg-white rounded-lg p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner">
                            <option value="">Select...</option>
                            <option value="Passport">Passport</option>
                            <option value="DNI">DNI</option>
                            <option value="Driver License">License</option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500 block mb-1 nav-label notranslate" data-en="ID Number" data-es="Número de Documento">ID Number</label>
                        <input type="text" name="edit_id_number" id="pay_id_number" placeholder="Enter ID Number" class="w-full border border-slate-300 bg-white rounded-lg p-3 text-sm outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal transition-all shadow-inner" />
                    </div>
                </div>

                <div class="pt-4 flex justify-end gap-4 mt-8 border-t border-slate-100">
                    <button type="button" onclick="toggleModal('payGuestModal')" class="px-6 py-3 font-bold text-slate-500 hover:bg-slate-100 rounded-xl transition-colors shadow-sm border border-slate-200 nav-label notranslate" data-en="Cancel" data-es="Cancelar">Cancel</button>
                    <button type="submit" class="bg-gradient-to-r from-emerald-500 to-emerald-600 text-white px-8 py-3 rounded-xl font-bold hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md nav-label notranslate" data-en="Save Changes" data-es="Guardar Cambios">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.remove('hidden');
                m.classList.add('flex');
                setTimeout(() => { m.classList.remove('opacity-0'); }, 10);
            }
        }
        function closeModal(id) {
            const m = document.getElementById(id);
            if (m) {
                m.classList.add('opacity-0');
                setTimeout(() => { m.classList.add('hidden'); m.classList.remove('flex'); }, 300);
            }
        }
    </script>

    <script>
        lucide.createIcons();
        const bData = <?php echo json_encode($bookings); ?>;
        const roomsData = <?php echo json_encode($rooms); ?>;
        const exchangeRate = <?php echo $exchangeRateARS; ?>;

        window.isDragging = false;
        let currentBookingId = null;

        function safeSetVal(id, val) { const el = document.getElementById(id); if(el) el.value = val; }
        function safeSetText(id, text) { const el = document.getElementById(id); if(el) el.innerText = text; }

        function allowDrop(ev) {
            ev.preventDefault();
            ev.dataTransfer.dropEffect = "move";
        }

        function drag(ev, id) {
            window.isDragging = true;
            ev.dataTransfer.setData("text/plain", id);
            ev.dataTransfer.setData("startX", ev.clientX);
            document.body.classList.add('is-dragging-body');
        }

        function dragLeave(ev) {}

        function drop(ev, roomId, date) {
            ev.preventDefault();
            document.body.classList.remove('is-dragging-body');
            const bookingId = ev.dataTransfer.getData("text/plain");
            const startX = parseFloat(ev.dataTransfer.getData("startX"));
            let rowContainer = ev.target.closest('.relative.flex-1'); if (!rowContainer) return;
            let rect = rowContainer.getBoundingClientRect();
            let totalDays = 14;
            let colWidth = rect.width / totalDays;
            let dayDelta = Math.round((ev.clientX - startX) / colWidth);

            if (bookingId) {
                let pill = document.getElementById('pill_' + bookingId);
                if (pill) {
                    rowContainer.appendChild(pill);
                    let currentLeft = parseFloat(pill.style.left) || 0;
                    let newLeftPct = currentLeft + (dayDelta / totalDays) * 100;
                    if (newLeftPct < 0) newLeftPct = 0;
                    pill.style.left = newLeftPct + '%';
                    pill.style.top = '14px';
                }

                const formData = new FormData();
                formData.append('drag_drop_update', '1');
                formData.append('booking_id', bookingId);
                formData.append('new_room_id', roomId);
                formData.append('day_delta', dayDelta);
                formData.append('ajax', '1');

                fetch(window.location.href, { method: 'POST', body: formData });
            }
            setTimeout(() => { window.isDragging = false; }, 100);
        }

        function dragEnd(ev) {
            document.body.classList.remove('is-dragging-body');
            setTimeout(() => { window.isDragging = false; }, 100);
        }

        async function moveCalendar(delta) {
            const url = new URL(window.location);
            const curStart = url.searchParams.get('start_date') || '<?php echo $viewStart; ?>';
            const curDate = new Date(curStart);
            curDate.setDate(curDate.getDate() + delta);
            url.searchParams.set('start_date', curDate.toISOString().split('T')[0]);
            url.searchParams.set('tab', 'calendar');

            try {
                const response = await fetch(url.href);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');

                document.querySelector('.cal-scroll').innerHTML = doc.querySelector('.cal-scroll').innerHTML;
                document.getElementById('calendar_date_range').innerText = doc.getElementById('calendar_date_range').innerText;

                window.history.pushState({}, '', url.href);
                lucide.createIcons();
            } catch(e) {
                window.location.href = url.href;
            }
        }

        function openBookingSidebar(id) {
            if (window.isDragging) return;
            const b = bData.find(x => x.id === id); if(!b) return;
            currentBookingId = id;

            const lFee = b.luggage ? parseFloat(b.luggage.fee) || 0 : 0;
            let dFee = 0; let dHtml = '';
            if (b.damages && b.damages.length > 0) {
                b.damages.forEach(d => {
                    let f = parseFloat(d.fee) || 0; dFee += f;
                    dHtml += `<div class="flex justify-between items-center text-sm mt-2"><span class="text-rose-600 font-medium text-xs"><i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> ${d.desc}</span><span class="font-bold text-rose-600 text-xs">AR$ ${Math.round(f * exchangeRate).toLocaleString()}</span></div>`;
                });
            }

            const tPrice = (parseFloat(b.totalPrice) || 0) + lFee + dFee;
            const aPaid = parseFloat(b.amountPaid) || 0;
            const bal = (tPrice - aPaid) * exchangeRate;

            safeSetText('sb_guest_name', b.guestName); safeSetText('sb_guest_name_p', b.guestName);
            safeSetText('sb_reservation_id', 'ID: ' + b.id);
            safeSetText('sb_ci', b.checkIn); safeSetText('sb_co', b.checkOut);
            safeSetText('sb_total', 'ARS ' + Math.round(tPrice * exchangeRate).toLocaleString());
            safeSetText('sb_balance', 'ARS ' + Math.max(0, Math.round(bal)).toLocaleString());

            let docId = b.passport || 'Pending'; safeSetText('sb_passport', docId);
            safeSetText('sb_nat', b.nationality || 'Global'); safeSetText('sb_phone', b.phone || 'Pending');
            safeSetText('sb_pay_method', b.paymentMethod || 'None');

            const sCount = b.stayCount || 1; let suffix = sCount === 1 ? 'st' : (sCount === 2 ? 'nd' : (sCount === 3 ? 'rd' : 'th'));
            safeSetText('sb_stay_count', sCount + suffix + ' Stay');

            let initials = "G";
            if(b.guestName) {
                let parts = b.guestName.split(" ");
                initials = parts.length > 1 ? parts[0][0].toUpperCase() + parts[1][0].toUpperCase() : parts[0][0].toUpperCase();
            }
            safeSetText('sb_initials', initials);

            let displayStatus = b.status || 'Pending';
            if(displayStatus.toLowerCase() === 'un-confirmed' || displayStatus.toLowerCase() === 'unconfirmed') displayStatus = 'Pending';
            safeSetText('sb_status_label', displayStatus);

            const s = (b.status || '').toLowerCase();
            const statusDot = document.getElementById('sb_status_dot');
            if(statusDot) statusDot.className = 'w-3.5 h-3.5 rounded-full shadow-sm ' + (s==='confirmed'?'bg-emerald-500':s==='checked in'?'bg-purple-500':s==='checked out'?'bg-pink-500':'bg-sky-400');

            const source = (b.source || 'Direct').toLowerCase();
            const sbBadgeContainer = document.getElementById('sb_source_badge_container');
            if (sbBadgeContainer) {
                if (source.includes('booking')) { sbBadgeContainer.innerHTML = '<span class="px-2 py-1 rounded-md bg-blue-50 text-blue-600 border border-blue-200 text-[9px] font-bold uppercase tracking-widest shadow-sm">Booking.com</span>'; }
                else if (source.includes('hostelworld')) { sbBadgeContainer.innerHTML = '<span class="px-2 py-1 rounded-md bg-orange-50 text-orange-600 border border-orange-200 text-[9px] font-bold uppercase tracking-widest shadow-sm">Hostelworld.com</span>'; }
                else if (source !== 'direct' && source !== '') { sbBadgeContainer.innerHTML = `<span class="px-2 py-1 rounded-md bg-slate-100 text-slate-500 border border-slate-200 text-[9px] font-bold uppercase tracking-widest shadow-sm">${source}</span>`; }
                else { sbBadgeContainer.innerHTML = ''; }
            }

            const histList = document.getElementById('sb_history_list');
            if (histList) { if (b.pastStays && b.pastStays.length > 0) { histList.innerHTML = b.pastStays.map(d => `<div class="p-2 bg-slate-50 border border-slate-100 rounded-lg text-[10px] text-slate-500 font-medium"><i data-lucide="calendar-check" class="inline w-3 h-3 mr-1 text-slate-400"></i> ${d}</div>`).join(''); } else { histList.innerHTML = `<p class="text-[10px] text-slate-400 italic font-medium">First time guest.</p>`; } }

            let lugContainer = document.getElementById('sb_luggage_container');
            if (lugContainer) {
                if (b.luggage && b.luggage.days > 0) {
                    lugContainer.innerHTML = `<div class="p-4 bg-purple-50 border border-purple-200 rounded-2xl flex items-center justify-between shadow-sm"><div class="flex items-center gap-2"><i data-lucide="briefcase" class="w-4 h-4 text-purple-600"></i><span class="text-sm font-medium text-purple-700">Luggage Storage</span></div><span class="text-sm font-bold text-purple-700">${b.luggage.days} Days (AR$ ${Math.round(b.luggage.fee * exchangeRate).toLocaleString()})</span></div>`;
                } else { lugContainer.innerHTML = ''; }
            }

            let damContainer = document.getElementById('sb_damages_container');
            if (damContainer) {
                if (dHtml !== '') { damContainer.innerHTML = `<div class="space-y-3 mt-3">${dHtml}</div>`; }
                else { damContainer.innerHTML = ''; }
            }

            safeSetVal('quick_conf_id', id);

            switchSidebarTab('operations');
            document.getElementById('sbOverlay').classList.remove('hidden');
            document.getElementById('bookingSidebar').classList.remove('translate-x-full');
            setTimeout(() => { document.getElementById('sbOverlay').classList.remove('opacity-0'); }, 10);
            lucide.createIcons();
        }

        function closeBookingSidebar() {
            document.getElementById('bookingSidebar').classList.add('translate-x-full');
            document.getElementById('sbOverlay').classList.add('opacity-0');
            setTimeout(() => { document.getElementById('sbOverlay').classList.add('hidden'); }, 300);
        }

        function switchSidebarTab(tab) {
            document.querySelectorAll('.sidebar-tab-view').forEach(v => v.classList.add('hidden'));
            const viewTab = document.getElementById('view_sb_' + tab); if(viewTab) viewTab.classList.remove('hidden');

            document.querySelectorAll('.sidebar-tab').forEach(b => {
                b.classList.remove('text-slate-900', 'border-teal', 'sidebar-tab-active');
                b.classList.add('text-slate-400', 'border-transparent');
            });

            const btnTab = document.getElementById('btn_sb_'+tab);
            if(btnTab) {
                btnTab.classList.add('text-slate-900', 'border-teal', 'sidebar-tab-active');
                btnTab.classList.remove('text-slate-400', 'border-transparent');
            }
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            const tabEl = document.getElementById(tabId);
            if (tabEl) tabEl.classList.add('active');

            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.className = 'nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all hover:bg-slate-800 hover:text-white text-slate-300';
            });
            const activeBtn = document.querySelector(`.nav-btn[data-target="${tabId}"]`);
            if (activeBtn) {
                if(activeBtn.querySelector('div')) {
                    activeBtn.className = 'nav-btn active w-full flex items-center justify-between px-4 py-3 rounded-xl transition-all bg-gradient-to-r from-teal to-teal-hover shadow-md text-white font-bold';
                } else {
                    activeBtn.className = 'nav-btn active w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all bg-gradient-to-r from-teal to-teal-hover shadow-md text-white font-bold';
                }
            }
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);

            if(window.innerWidth < 768) { toggleMobileMenu(); }
        }

        function toggleMobileMenu() {
            const sidebar = document.getElementById('mainSidebar');
            const overlay = document.getElementById('mobileOverlay');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }

        function toggleModal(modalId) {
            const m = document.getElementById(modalId);
            const content = m.querySelector('.modal-content');
            if (m.classList.contains('hidden')) {
                m.classList.remove('hidden');
                m.classList.add('flex');
                setTimeout(() => {
                    m.classList.remove('opacity-0');
                    content.classList.remove('scale-95');
                }, 10);
            } else {
                m.classList.add('opacity-0');
                content.classList.add('scale-95');
                setTimeout(() => {
                    m.classList.add('hidden');
                    m.classList.remove('flex');
                }, 300);
            }
        }

        function toggleActionMenu(id) {
            document.querySelectorAll('.action-menu').forEach(m => { if(m.id !== id) m.classList.add('hidden'); });
            const menu = document.getElementById(id); if (menu) menu.classList.toggle('hidden');
        }

        function toggleAccordion(id) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden');
        }

        document.addEventListener('click', function(event) {
            if (!event.target.closest('td.relative')) {
                document.querySelectorAll('.action-menu').forEach(menu => menu.classList.add('hidden'));
            }
            if(!event.target.closest('.relative.w-full')) {
                const dd = document.getElementById('guestSearchDropdown');
                if(dd) dd.classList.add('hidden');
            }
        });

        function openPaymentModal(bookingId, returnTab) {
            const booking = bData.find(b => b.id === bookingId);
            if (booking) {
                document.getElementById('pay_booking_id').value = booking.id;
                document.getElementById('pay_return_tab').value = returnTab;

                document.getElementById('pay_guest_name').innerText = booking.guestName || 'Unknown';

                // POPULATE ARS VALUES INSTANTLY FOR THE STAFF
                document.getElementById('pay_total_price').value = Math.round((booking.totalPrice || 0) * exchangeRate);
                document.getElementById('pay_check_out').value = booking.checkOut;

                document.getElementById('pay_amount_paid').value = Math.round((booking.amountPaid || 0) * exchangeRate);
                document.getElementById('pay_payment_method').value = booking.paymentMethod || '';

                document.getElementById('pay_id_type').value = booking.idType || '';
                document.getElementById('pay_id_number').value = booking.idNumber || '';

                let stat = booking.status ? booking.status.toLowerCase() : 'pending';
                if(stat === 'unconfirmed' || stat === 'un-confirmed') stat = 'pending';
                document.getElementById('pay_status').value = stat;

                if(!document.getElementById('bookingSidebar').classList.contains('translate-x-full')){
                    closeBookingSidebar();
                }

                toggleModal('payGuestModal');
            }
        }

        function searchGuests() {
            let input = document.getElementById('guestSearchInput').value.toLowerCase();

            let rows = document.querySelectorAll('.guest-row');
            rows.forEach(row => {
                let name = row.getAttribute('data-name').toLowerCase();
                let email = row.getAttribute('data-email').toLowerCase();
                row.style.display = (name.includes(input) || email.includes(input)) ? '' : 'none';
            });

            let dropdown = document.getElementById('guestSearchDropdown');
            if(!dropdown) return;
            if (input.length < 2) { dropdown.classList.add('hidden'); return; }

            let uniqueGuests = [];
            let seenNames = new Set();
            bData.forEach(b => {
                if(!seenNames.has(b.guestName)) {
                    seenNames.add(b.guestName);
                    uniqueGuests.push(b);
                }
            });

            let matches = uniqueGuests.filter(n => n.guestName.toLowerCase().includes(input) || (n.email && n.email.toLowerCase().includes(input)));

            if (matches.length > 0) {
                dropdown.innerHTML = matches.map(m => {
                    return `<div onclick="openGuestHistory('${m.guestName.replace(/'/g, "\\'")}')" class="p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 flex items-center gap-3"><div class="w-8 h-8 min-w-[32px] min-h-[32px] flex-none rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold notranslate" translate="no"><i data-lucide="search" class="w-4 h-4 shrink-0 flex-none"></i></div><div><p class="text-sm font-bold text-slate-800">${m.guestName}</p><p class="text-[9px] text-slate-400 uppercase tracking-widest mt-0.5">View Stay History</p></div></div>`;
                }).join('');
                dropdown.classList.remove('hidden');
                lucide.createIcons();
            } else {
                dropdown.classList.add('hidden');
            }
        }

        function openGuestHistory(guestName) {
            document.getElementById('guestSearchDropdown').classList.add('hidden');

            let stays = bData.filter(b => b.guestName.toLowerCase() === guestName.toLowerCase());
            stays.sort((a, b) => new Date(b.checkIn) - new Date(a.checkIn));

            if(stays.length === 0) return;

            let guest = stays[0];

            let totalSpent = 0;
            stays.forEach(b => {
                let lFee = b.luggage ? parseFloat(b.luggage.fee) : 0;
                let dFee = 0; if (b.damages && b.damages.length > 0) { b.damages.forEach(d => dFee += parseFloat(d.fee)); }
                totalSpent += (parseFloat(b.totalPrice) + lFee + dFee) * exchangeRate;
            });

            safeSetText('gh_name', guest.guestName);
            safeSetText('gh_initial', guest.guestName.charAt(0).toUpperCase());
            safeSetText('gh_email', guest.email || 'No Email on file');
            safeSetText('gh_phone', guest.phone || 'No Phone on file');
            safeSetText('gh_stay_count', stays.length + " Stays");
            safeSetText('gh_total_spent', "AR$ " + Math.round(totalSpent).toLocaleString());

            const badge = document.getElementById('gh_vip_badge');
            if(stays.length > 2) { badge.classList.remove('hidden'); } else { badge.classList.add('hidden'); }

            let staysHtml = stays.map((s, index) => {
                let rName = 'Unassigned';
                let rm = roomsData.find(r => r.id === s.roomId);
                if(rm) rName = rm.name;

                let lFee = s.luggage ? parseFloat(s.luggage.fee) : 0;
                let dFee = 0; let dHtml = '';
                if (s.damages && s.damages.length > 0) {
                    s.damages.forEach(d => {
                        let f = parseFloat(d.fee) || 0; dFee += f;
                        dHtml += `<div class="flex justify-between text-sm"><span class="text-rose-600 font-medium"><i data-lucide="alert-triangle" class="w-3 h-3 inline"></i> ${d.desc}</span><span class="font-bold text-rose-600">AR$ ${Math.round(f * exchangeRate).toLocaleString()}</span></div>`;
                    });
                }

                let tP = parseFloat(s.totalPrice) + lFee + dFee;
                let bal = (tP - parseFloat(s.amountPaid)) * exchangeRate;

                let sSt = s.status ? s.status.toLowerCase() : 'pending';
                if(sSt === 'unconfirmed' || sSt === 'un-confirmed') sSt = 'pending';

                let statusCol = sSt === 'confirmed' ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : (sSt === 'checked in' ? 'text-purple-600 bg-purple-50 border-purple-200' : (sSt === 'checked out' ? 'text-pink-600 bg-pink-50 border-pink-200' : 'text-slate-600 bg-slate-50 border-slate-200'));

                let displayStatus = s.status || 'Pending';
                if(displayStatus.toLowerCase() === 'un-confirmed' || displayStatus.toLowerCase() === 'unconfirmed') displayStatus = 'Pending';

                let lugHtml = s.luggage && s.luggage.days > 0 ? `<div class="flex justify-between text-sm"><span class="text-slate-600 font-medium text-purple-600"><i data-lucide="briefcase" class="w-3 h-3 inline"></i> Luggage (${s.luggage.days} days)</span><span class="font-bold text-slate-900">AR$ ${Math.round(lFee * exchangeRate).toLocaleString()}</span></div>` : '';

                return `
                <div class="border border-slate-100 rounded-2xl mb-4 overflow-hidden shadow-sm bg-white">
                    <button type="button" onclick="toggleAccordion('stay_acc_${index}')" class="w-full flex items-center justify-between p-4 md:p-5 hover:bg-slate-50 transition-colors focus:outline-none">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 min-w-[40px] min-h-[40px] flex-none rounded-full flex items-center justify-center bg-slate-100 text-slate-500 font-bold text-xs shrink-0"><i data-lucide="key" class="w-4 h-4"></i></div>
                            <div class="text-left min-w-0">
                                <p class="font-bold text-slate-900 text-sm truncate">${s.checkIn} to ${s.checkOut}</p>
                                <p class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5 truncate max-w-[150px] md:max-w-[200px]">${rName}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 shrink-0 flex-none">
                            <span class="px-3 py-1 rounded-lg text-[9px] font-bold uppercase tracking-widest border hidden md:inline-block ${statusCol}">${displayStatus}</span>
                            <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400"></i>
                        </div>
                    </button>
                    <div id="stay_acc_${index}" class="hidden bg-slate-50 p-4 md:p-5 border-t border-slate-100">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Financial Breakdown</p>
                                <div class="space-y-2 mt-3">
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Room Rate</span><span class="font-bold text-slate-900">AR$ ${Math.round(s.totalPrice * exchangeRate).toLocaleString()}</span></div>
                                    ${lugHtml}
                                    ${dHtml}
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Amount Paid</span><span class="font-bold text-emerald-600">AR$ ${Math.round(s.amountPaid * exchangeRate).toLocaleString()}</span></div>
                                    <div class="h-px bg-slate-200 my-2"></div>
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Balance</span><span class="font-bold ${bal > 0 ? 'text-rose-500' : 'text-slate-400'}">AR$ ${Math.round(bal).toLocaleString()}</span></div>
                                </div>
                            </div>
                            <div>
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1">Stay Information</p>
                                <div class="space-y-2 mt-3">
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Status</span><span class="font-bold uppercase tracking-widest text-[10px] px-2 py-1 rounded-md border ${statusCol}">${displayStatus}</span></div>
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Booking Source</span><span class="font-bold text-slate-900">${s.source || 'Direct'}</span></div>
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Payment Method</span><span class="font-bold text-slate-900">${s.paymentMethod || 'None'}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');

            document.getElementById('gh_stays_container').innerHTML = staysHtml;
            toggleModal('guestHistoryModal');
            lucide.createIcons();
        }

        function filterReservations() {
            let f = document.getElementById("reservationSearchInput").value.toLowerCase().trim();
            let rows = document.getElementById("reservationsTableBody").getElementsByTagName("tr");
            for (let row of rows) {
                if (f === "") {
                    row.style.display = "";
                    continue;
                }
                let nameCell = row.cells[0];
                let exactName = nameCell ? nameCell.textContent.toLowerCase().trim() : "";
                if (exactName.includes(f)) {
                    row.style.display = "";
                } else {
                    row.style.display = "none";
                }
            }
        }

        // --- GOOGLE TRANSLATE LOGIC ---
        function changeAdminLanguage(langCode, btnElement) {
            var selectField = document.querySelector("#google_translate_element select") || document.querySelector(".goog-te-combo");
            if(selectField) {
                selectField.value = langCode;
                selectField.dispatchEvent(new Event('change'));
            }

            document.querySelectorAll('.nav-label').forEach(label => {
                label.innerText = label.getAttribute('data-' + langCode) || label.getAttribute('data-en');
            });

            document.querySelectorAll('.lang-btn').forEach(btn => {
                btn.classList.remove('bg-teal', 'bg-gradient-to-r', 'from-teal', 'to-[#144042]', 'text-white', 'shadow-md', 'font-bold');
                btn.classList.add('text-slate-400');
            });
            btnElement.classList.add('bg-gradient-to-r', 'from-teal', 'to-[#144042]', 'text-white', 'shadow-md', 'font-bold');
            btnElement.classList.remove('text-slate-400');

            localStorage.setItem('admin_lang', langCode);
        }

        window.addEventListener('load', function() {
            setTimeout(function() {
                let savedLang = localStorage.getItem('admin_lang') || 'es';
                let btns = document.querySelectorAll('.lang-btn');
                let targetBtn = savedLang === 'es' ? btns[1] : btns[0];
                if(targetBtn && savedLang === 'es') {
                    changeAdminLanguage('es', targetBtn);
                }
            }, 1000);
        });

        setInterval(function() {
            if (document.body.style.top !== '0px') document.body.style.top = '0px';
            if (document.documentElement.style.top !== '0px') document.documentElement.style.top = '0px';
        }, 50);
    </script>

    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'en', includedLanguages: 'en,es', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

</body>
</html>