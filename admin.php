<?php
error_reporting(0); // Prevents HTTP 500 errors from crashing the UI

// Secrets desde fuera del webroot
require_once __DIR__ . '/dev_env.php';
$_secretsFile = hp_secrets_path(__DIR__);
$_secrets     = is_file($_secretsFile) ? (require $_secretsFile) : [];
$smtpPassword = $_secrets['smtp_password'] ?? '';

// --- 1. DIRECT ICAL OTA SYNC ENGINE (Booking.com & Hostelworld) ---
$icalFeeds = [
    'Booking.com' => '', // PASTE BOOKING.COM .ICS LINK HERE
    'Hostelworld' => ''  // PASTE HOSTELWORLD .ICS LINK HERE
];

$bookings = json_decode(@file_get_contents('bookings.json'), true) ?: [];
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
                            'id' => $eventId,
                            'guestName' => $guestName,
                            'email' => '',
                            'phone' => '',
                            'passport' => '',
                            'checkIn' => $ci,
                            'checkOut' => $co,
                            'roomId' => 'unassigned',
                            'status' => 'Confirmed',
                            'totalPrice' => 0,
                            'amountPaid' => 0,
                            'paymentMethod' => 'None',
                            'nationality' => 'Global',
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
if ($newSyncFound) { @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT)); }


// --- EXPORT CSV ENGINE ---
$config = json_decode(file_get_contents('config.json'), true);
$exchangeRateARS = $config['exchangeRateARS'] ?? 1370;

if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=hostelplaza_guests_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Booking ID', 'Guest Name', 'Email', 'Phone', 'Passport/ID', 'Check-In', 'Check-Out', 'Status', 'Total Price (ARS)', 'Amount Paid (ARS)', 'Nationality', 'Source']);
    foreach ($bookings as $b) {
        $lFee = isset($b['luggage']) ? (float)$b['luggage']['fee'] : 0;
        $dFee = 0; if (!empty($b['damages'])) { foreach($b['damages'] as $dam) { $dFee += (float)$dam['fee']; } }
        $tPrice = (float)($b['totalPrice'] ?? 0) + $lFee + $dFee;

        fputcsv($output, [
            $b['id'], $b['guestName'], $b['email'], $b['phone'], $b['passport'], $b['checkIn'], $b['checkOut'],
            $b['status'], round($tPrice * $exchangeRateARS), round((float)($b['amountPaid'] ?? 0) * $exchangeRateARS),
            $b['nationality'], $b['source']
        ]);
    }
    fclose($output);
    exit;
}

// --- STANDALONE CHECKLIST VIEW ---
if (isset($_GET['view_checklist'])) {
    $listId = $_GET['view_checklist'];
    $staffTasks = json_decode(@file_get_contents('tasks.json'), true) ?: [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_task'])) {
        foreach ($staffTasks as &$l) {
            if ($l['id'] === $_POST['list_id']) {
                foreach ($l['tasks'] as &$t) { if ($t['id'] === $_POST['task_id']) { $t['done'] = !$t['done']; break 2; } }
            }
        }
        @file_put_contents('tasks.json', json_encode($staffTasks, JSON_PRETTY_PRINT));
        header("Location: ?view_checklist=" . urlencode($listId)); exit;
    }
    $targetList = null;
    foreach ($staffTasks as $list) { if ($list['id'] === $listId) { $targetList = $list; break; } }
    if (!$targetList) { die("Checklist not found."); }
    ?>
    <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><script src="https://cdn.tailwindcss.com"></script><script src="https://unpkg.com/lucide@latest"></script></head>
    <body class="bg-slate-50 p-4 md:p-8 flex justify-center items-start min-h-screen">
        <div class="max-w-2xl w-full bg-white rounded-3xl shadow-2xl overflow-hidden border mt-10">
            <div class="<?php echo htmlspecialchars($targetList['color']); ?> p-8 text-white text-center"><h1 class="text-3xl font-semibold mb-2"><?php echo htmlspecialchars($targetList['name']); ?></h1><p class="text-xs opacity-80 uppercase tracking-widest">Live Action Items</p></div>
            <div class="p-8 space-y-4"><?php foreach($targetList['tasks'] as $t): ?>
                <form method="POST">
                    <input type="hidden" name="toggle_task" value="1"><input type="hidden" name="list_id" value="<?php echo htmlspecialchars($targetList['id']); ?>"><input type="hidden" name="task_id" value="<?php echo htmlspecialchars($t['id']); ?>">
                    <button type="submit" class="w-full text-left flex items-center gap-4 p-5 rounded-2xl border transition-all <?php echo $t['done'] ? 'bg-slate-50 border-slate-100 opacity-60' : 'bg-white border-slate-200 shadow-md hover:shadow-lg'; ?>">
                        <i data-lucide="<?php echo $t['done'] ? 'check-circle' : 'circle'; ?>"></i><span class="text-lg <?php echo $t['done'] ? 'line-through text-slate-400' : 'text-slate-800 font-medium'; ?>"><?php echo htmlspecialchars($t['text']); ?></span>
                    </button>
                </form>
            <?php endforeach; ?></div>
        </div><script>lucide.createIcons();</script></body></html>
    <?php exit;
}

// --- ADMIN AUTH ---
session_start();
if (!isset($_SESSION['admin_logged_in'])) { header('Location: login.php'); exit; }

function safeRedirect($url) {
    if (!headers_sent()) { header("Location: " . $url); } else { echo "<script>window.location.href='" . $url . "';</script>"; }
    exit;
}

// --- MAKE.COM WEBHOOK CONFIGURATION ---
// Paste your unique Make.com Custom Webhook URL between the quotes below:
$makeWebhookUrl = "https://hook.us2.make.com/y9ccircg58ry16ujxom9h5fs5ziu54h4";

function triggerMakeWebhook($url, $guestName, $phone, $checkIn, $checkOut) {
    if (empty($url) || empty($phone)) return;
    $payload = json_encode([
        "guestName" => $guestName,
        "phone" => preg_replace('/[^0-9]/', '', $phone), // Cleans phone number for WhatsApp API
        "checkIn" => $checkIn,
        "checkOut" => $checkOut,
        "trigger" => "new_booking"
    ]);
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => $payload,
            'timeout' => 2 // Ensures your dashboard never lags while waiting for Make.com
        ]
    ];
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);
}

// --- DATABASES & PLACEHOLDERS ---
$rooms = json_decode(@file_get_contents('rooms.json'), true) ?: [];
$weeklyEvents = json_decode(@file_get_contents('weekly_events.json'), true) ?: [];
$plazaEvents = json_decode(@file_get_contents('plaza_events.json'), true) ?: [];
$checklistsDB = json_decode(@file_get_contents('tasks.json'), true) ?: [];
$expenses = json_decode(@file_get_contents('expenses.json'), true) ?: [];

if ($exchangeRateARS == 1050) { $exchangeRateARS = 1396; $config['exchangeRateARS'] = 1396; @file_put_contents('config.json', json_encode($config)); }

$staffFile = 'staff.json';
$staffData = json_decode(@file_get_contents($staffFile), true);
if (!is_array($staffData) || empty($staffData)) {
    $staffData = [['id' => 's1', 'name' => 'Sarah Connor', 'email' => 'sarah@hostelplaza.com', 'schedule' => ['MON'=>'07:00 - 15:00', 'TUE'=>'07:00 - 15:00', 'WED'=>'OFF', 'THU'=>'07:00 - 15:00', 'FRI'=>'07:00 - 15:00', 'SAT'=>'OFF', 'SUN'=>'OFF']]];
    @file_put_contents($staffFile, json_encode($staffData, JSON_PRETTY_PRINT));
}
$daysOfWeek = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];

$today = date('Y-m-d');
$activeTab = $_GET['tab'] ?? 'overview';

$hour = (int)date('H');
if ($hour < 12) { $greeting = 'Good morning'; } elseif ($hour < 18) { $greeting = 'Good afternoon'; } else { $greeting = 'Good evening'; }

// --- COMMUNICATIONS ENGINE ---
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- COMMUNICATIONS ENGINE ---
function sendHostelEmail($toEmail, $subject, $htmlBody, $fromEmail, $password = '') {
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return false;

    $pathException = __DIR__ . '/PHPMailer-master/src/Exception.php';
    $pathPHPMailer = __DIR__ . '/PHPMailer-master/src/PHPMailer.php';
    $pathSMTP = __DIR__ . '/PHPMailer-master/src/SMTP.php';

    if (!empty($password) && file_exists($pathPHPMailer)) {
        require_once $pathException;
        require_once $pathPHPMailer;
        require_once $pathSMTP;

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = 'c2721166.ferozo.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = $fromEmail;
            $mail->Password   = $password;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->SMTPOptions = array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true));

            $mail->setFrom($fromEmail, 'Hostel Plaza');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->send();
            return true;
        } catch (\Exception $e) { }
    }

    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: Hostel Plaza <" . $fromEmail . ">\r\n";
    return @mail($toEmail, $subject, $htmlBody, $headers);
}

function sendCheckoutEmail($toEmail, $guestName) {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) return;
    $year = date('Y');
    $htmlBody = "<div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);'><div style='background-color: #0f172a; padding: 40px 20px; text-align: center;'><h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; letter-spacing: 1px;'>HOSTEL PLAZA</h1><p style='color: #10b981; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;'>Mendoza, Argentina</p></div><div style='padding: 40px 30px;'><h2 style='color: #0f172a; font-size: 22px; margin-top: 0;'>Thank you for your stay!</h2><p style='color: #475569; font-size: 16px; line-height: 1.6;'>Hello " . htmlspecialchars($guestName) . ",<br><br>We hope you had a wonderful time in Mendoza. It was an absolute pleasure having you stay with us at Hostel Plaza.</p><p style='color: #475569; font-size: 16px; line-height: 1.6;'>Safe travels on your next adventure, and we hope to see you again very soon!</p><div style='text-align: center; margin-top: 30px;'><a href='https://test.hostelplaza.com.ar' style='display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: 500; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);'>Visit our website</a></div></div><div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'><p style='color: #94a3b8; font-size: 12px; margin: 0;'>© $year Hostel Plaza Mendoza. All rights reserved.</p></div></div>";
    sendHostelEmail($toEmail, "Thank you for staying with us - Hostel Plaza", $htmlBody, "thank-you@hostelplaza.com.ar", "j2J/69K4dT");
}

// AUTO-CHECKOUT ENGINE
// --- AUTO-CHECKOUT ENGINE (STRICT 10:00 AM MENDOZA TIME) ---
date_default_timezone_set('America/Argentina/Mendoza');
$currentDate = date('Y-m-d');
$currentHour = (int)date('H'); // 24-hour format (0-23)

$needsSave = false;
foreach ($bookings as &$b) {
    if (isset($b['checkOut'])) {
        $coDate = $b['checkOut'];

        // Trigger if today is strictly past the checkout date OR (today is the checkout date AND it is 10:00 AM or later)
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
if ($needsSave) { @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT)); }

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

// --- POST HANDLERS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- EXPENSES (FINANCIAL LEDGER) LOGIC ---
        if (isset($_POST['add_expense'])) {
            $expenses[] = ['id' => 'exp_' . uniqid(), 'date' => $_POST['exp_date'] ?: date('Y-m-d'), 'description' => trim($_POST['exp_desc']), 'category' => $_POST['exp_category'], 'amount' => (float)$_POST['exp_amount']];
            @file_put_contents('expenses.json', json_encode($expenses, JSON_PRETTY_PRINT)); safeRedirect("?tab=finances");
        }
        if (isset($_POST['delete_expense'])) {
            $eId = $_POST['expense_id']; $expenses = array_values(array_filter($expenses, fn($e) => $e['id'] !== $eId));
            @file_put_contents('expenses.json', json_encode($expenses, JSON_PRETTY_PRINT)); safeRedirect("?tab=finances");
        }

        // --- CSV IMPORT LOGIC ---
        if (isset($_POST['import_csv']) && isset($_FILES['csv_file'])) {
            $file = $_FILES['csv_file']['tmp_name'];
            if (($handle = fopen($file, "r")) !== FALSE) {
                $headers = fgetcsv($handle, 1000, ",");
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($headers) == count($data)) {
                        $row = array_combine($headers, $data);
                        $bookings[] = [
                            'id' => 'imp_' . uniqid(),
                            'guestName' => $row['Name'] ?? $row['Guest'] ?? 'Imported Guest',
                            'email' => $row['Email'] ?? '',
                            'phone' => $row['Phone'] ?? '',
                            'passport' => $row['Passport'] ?? '',
                            'checkIn' => $row['Check-in'] ?? $row['Arrival'] ?? $today,
                            'checkOut' => $row['Check-out'] ?? $row['Departure'] ?? date('Y-m-d', strtotime('+1 day')),
                            'roomId' => 'unassigned', 'status' => 'Confirmed',
                            'totalPrice' => (float)($row['Total'] ?? 0) / $exchangeRateARS,
                            'amountPaid' => 0, 'nationality' => $row['Nationality'] ?? 'Global', 'source' => 'Manual Import'
                        ];
                    }
                }
                fclose($handle);
                @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT));
                safeRedirect("?tab=guests");
            }
        }

        // --- WEBSITE CONTENT ---
        if (isset($_POST['add_room'])) { $rooms[] = ['id' => 'r_' . uniqid(), 'name' => trim($_POST['room_name']), 'image' => trim($_POST['room_image']), 'description' => trim($_POST['room_description'])]; @file_put_contents('rooms.json', json_encode($rooms, JSON_PRETTY_PRINT)); safeRedirect("?tab=rooms"); }
        if (isset($_POST['edit_room'])) { foreach ($rooms as &$r) { if ($r['id'] == $_POST['room_id']) { $r['name'] = trim($_POST['room_name']); $r['image'] = trim($_POST['room_image']); $r['description'] = trim($_POST['room_description']); break; } } @file_put_contents('rooms.json', json_encode($rooms, JSON_PRETTY_PRINT)); safeRedirect("?tab=rooms"); }
if (isset($_POST['delete_room'])) { $rId = $_POST['room_id']; $rooms = array_values(array_filter($rooms, fn($r) => $r['id'] != $rId)); @file_put_contents('rooms.json', json_encode($rooms, JSON_PRETTY_PRINT)); safeRedirect("?tab=rooms"); }

        if (isset($_POST['add_weekly_event'])) { $weeklyEvents[] = ['id' => 'we_' . uniqid(), 'name' => trim($_POST['we_name']), 'day' => trim($_POST['we_day']), 'image' => trim($_POST['we_image']), 'description' => trim($_POST['we_description'])]; @file_put_contents('weekly_events.json', json_encode($weeklyEvents, JSON_PRETTY_PRINT)); safeRedirect("?tab=weekly_events"); }
        if (isset($_POST['edit_weekly_event'])) { foreach ($weeklyEvents as &$e) { if ($e['id'] === $_POST['we_id']) { $e['name'] = trim($_POST['we_name']); $e['day'] = trim($_POST['we_day']); $e['image'] = trim($_POST['we_image']); $e['description'] = trim($_POST['we_description']); break; } } @file_put_contents('weekly_events.json', json_encode($weeklyEvents, JSON_PRETTY_PRINT)); safeRedirect("?tab=weekly_events"); }
        if (isset($_POST['delete_weekly_event'])) { $eId = $_POST['we_id']; $weeklyEvents = array_values(array_filter($weeklyEvents, fn($e) => $e['id'] !== $eId)); @file_put_contents('weekly_events.json', json_encode($weeklyEvents, JSON_PRETTY_PRINT)); safeRedirect("?tab=weekly_events"); }

        if (isset($_POST['add_plaza_event'])) { $plazaEvents[] = ['id' => 'pe_' . uniqid(), 'name' => trim($_POST['pe_name']), 'date' => trim($_POST['pe_date']), 'image' => trim($_POST['pe_image']), 'description' => trim($_POST['pe_description'])]; @file_put_contents('plaza_events.json', json_encode($plazaEvents, JSON_PRETTY_PRINT)); safeRedirect("?tab=plaza_events"); }
        if (isset($_POST['edit_plaza_event'])) { foreach ($plazaEvents as &$e) { if ($e['id'] === $_POST['pe_id']) { $e['name'] = trim($_POST['pe_name']); $e['date'] = trim($_POST['pe_date']); $e['image'] = trim($_POST['pe_image']); $e['description'] = trim($_POST['pe_description']); break; } } @file_put_contents('plaza_events.json', json_encode($plazaEvents, JSON_PRETTY_PRINT)); safeRedirect("?tab=plaza_events"); }
        if (isset($_POST['delete_plaza_event'])) { $eId = $_POST['pe_id']; $plazaEvents = array_values(array_filter($plazaEvents, fn($e) => $e['id'] !== $eId)); @file_put_contents('plaza_events.json', json_encode($plazaEvents, JSON_PRETTY_PRINT)); safeRedirect("?tab=plaza_events"); }

        // --- CALENDAR INSTANT DRAG & DROP ---
        if (isset($_POST['drag_drop_update'])) {
            foreach ($bookings as &$b) {
                if ($b['id'] === $_POST['booking_id']) {
                    $b['roomId'] = $_POST['new_room_id'];
                    if (isset($_POST['day_delta']) && (int)$_POST['day_delta'] !== 0) {
                        $delta = (int)$_POST['day_delta'];
                        $ci = new DateTime($b['checkIn']); $co = new DateTime($b['checkOut']);
                        if ($delta > 0) { $ci->modify("+$delta days"); $co->modify("+$delta days"); }
                        else { $ci->modify("$delta days"); $co->modify("$delta days"); }
                        $b['checkIn'] = $ci->format('Y-m-d'); $b['checkOut'] = $co->format('Y-m-d');
                    }
                    break;
                }
            }
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT));
            if (isset($_POST['ajax'])) { echo "ok"; exit; }
        }

        // --- STAFF & CHECKLISTS ---
        if (isset($_POST['add_staff'])) { $newStaff = ['id' => 's_' . uniqid(), 'name' => $_POST['staff_name'], 'email' => $_POST['staff_email'], 'schedule' => ['MON'=>'OFF', 'TUE'=>'OFF', 'WED'=>'OFF', 'THU'=>'OFF', 'FRI'=>'OFF', 'SAT'=>'OFF', 'SUN'=>'OFF']]; $staffData[] = $newStaff; @file_put_contents('staff.json', json_encode($staffData, JSON_PRETTY_PRINT)); safeRedirect("?tab=staff"); }
        if (isset($_POST['save_schedule'])) { $newSched = $_POST['schedule'] ?? []; foreach ($staffData as &$s) { if (isset($newSched[$s['id']])) { $s['schedule'] = $newSched[$s['id']]; } } @file_put_contents('staff.json', json_encode($staffData, JSON_PRETTY_PRINT)); safeRedirect("?tab=staff"); }
        if (isset($_POST['delete_staff'])) { $staffId = $_POST['staff_id']; $staffData = array_filter($staffData, function($s) use ($staffId) { return $s['id'] !== $staffId; }); $staffData = array_values($staffData); @file_put_contents('staff.json', json_encode($staffData, JSON_PRETTY_PRINT)); safeRedirect("?tab=staff"); }

        if (isset($_POST['create_checklist'])) {
            $tasks = []; foreach ($_POST['task_texts'] as $t) { if (trim($t) !== '') { $tasks[] = ['id' => 't_' . uniqid(), 'text' => trim($t), 'done' => false]; } }
            if (!empty($tasks)) {
                $clId = 'cl_' . uniqid();
                $newCl = ['id' => $clId, 'staffName' => $_POST['staff_name'], 'date' => date('Y-m-d'), 'color' => 'bg-emerald-500', 'name' => 'Daily Checklist', 'tasks' => $tasks];
                array_unshift($checklistsDB, $newCl); @file_put_contents('tasks.json', json_encode($checklistsDB, JSON_PRETTY_PRINT));

                $staffEmail = ''; $staffSched = [];
                foreach ($staffData as $s) { if ($s['name'] === $_POST['staff_name']) { $staffEmail = $s['email']; $staffSched = $s['schedule'] ?? []; break; } }

                if (!empty($staffEmail)) {
                    $checklistUrl = "https://test.hostelplaza.com.ar/admin.php?view_checklist=" . $clId;
                    $taskListHtml = ""; foreach($tasks as $tk) { $taskListHtml .= "<li style='margin-bottom: 10px; color: #475569; font-size: 15px;'>• " . htmlspecialchars($tk['text']) . "</li>"; }
                    $schedHtml = "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'><tr style='background-color: #f8fafc;'><th style='padding: 10px; text-align: left; font-size: 12px; color: #64748b; border-bottom: 1px solid #e2e8f0;'>DAY</th><th style='padding: 10px; text-align: left; font-size: 12px; color: #64748b; border-bottom: 1px solid #e2e8f0;'>SHIFT</th></tr>";
                    foreach(['MON','TUE','WED','THU','FRI','SAT','SUN'] as $day) {
                        $shift = $staffSched[$day] ?? 'OFF'; $color = ($shift === 'OFF') ? '#ef4444' : '#10b981';
                        $schedHtml .= "<tr><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: bold; color: #0f172a;'>$day</td><td style='padding: 10px; border-bottom: 1px solid #e2e8f0; font-size: 14px; font-weight: bold; color: $color;'>$shift</td></tr>";
                    }
                    $schedHtml .= "</table>";
                    $emailBody = "<div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);'><div style='background-color: #0f172a; padding: 40px 20px; text-align: center;'><h1 style='color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 1px;'>HOSTEL PLAZA</h1><p style='color: #10b981; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;'>Daily Checklist & Schedule</p></div><div style='padding: 40px 30px;'><h2 style='color: #0f172a; font-size: 20px; margin-top: 0;'>Hello " . htmlspecialchars($_POST['staff_name']) . ",</h2><p style='color: #475569; font-size: 15px; line-height: 1.6;'>Here is your assigned checklist and weekly schedule.</p><div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 25px; margin: 30px 0;'><h3 style='color: #0f172a; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-top: 0; margin-bottom: 15px;'>Your Tasks:</h3><ul style='list-style-type: none; padding: 0; margin: 0;'>" . $taskListHtml . "</ul></div><div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 25px; margin: 30px 0;'><h3 style='color: #0f172a; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; margin-top: 0; margin-bottom: 10px;'>Your Schedule</h3>" . $schedHtml . "</div><div style='text-align: center; margin-top: 30px;'><a href='" . $checklistUrl . "' style='display: inline-block; background-color: #10b981; color: #ffffff; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: 500; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.3);'>Open Live Checklist</a></div></div></div>";
                    sendHostelEmail($staffEmail, "Your Checklist & Schedule - Hostel Plaza", $emailBody, "checklist@hostelplaza.com.ar", "w/bfc8y7sY");
                }
            }
            safeRedirect("?tab=checklists");
        }

        if (isset($_POST['admin_toggle_task'])) {
            foreach ($checklistsDB as &$cl) { if ($cl['id'] === $_POST['list_id']) { foreach ($cl['tasks'] as &$t) { if ($t['id'] === $_POST['task_id']) { $t['done'] = !$t['done']; break 2; } } } }
            @file_put_contents('tasks.json', json_encode($checklistsDB, JSON_PRETTY_PRINT)); safeRedirect("?tab=checklists");
        }

        if (isset($_POST['delete_checklist'])) {
            $clId = $_POST['checklist_id']; $checklistsDB = array_filter($checklistsDB, function($cl) use ($clId) { return $cl['id'] !== $clId; });
            $checklistsDB = array_values($checklistsDB); @file_put_contents('tasks.json', json_encode($checklistsDB, JSON_PRETTY_PRINT)); safeRedirect("?tab=checklists");
        }

        // --- GUEST ACTIONS ---
        if (isset($_POST['delete_guest'])) {
            $delId = $_POST['guest_id'];
            $bookings = array_values(array_filter($bookings, fn($b) => $b['id'] !== $delId));
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT));
            safeRedirect("?tab=".$_POST['tab_redirect']);
        }

        if (isset($_POST['save_luggage'])) {
            foreach ($bookings as &$b) {
                if ($b['id'] === $_POST['booking_id']) {
                    $b['luggage'] = ['days' => (int)$_POST['luggage_days'], 'fee' => (float)$_POST['luggage_fee'] / $exchangeRateARS];
                    break;
                }
            }
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT)); safeRedirect("?tab=".$_POST['tab_redirect']);
        }

        if (isset($_POST['save_damage'])) {
            foreach ($bookings as &$b) {
                if ($b['id'] === $_POST['booking_id']) {
                    if (!isset($b['damages'])) $b['damages'] = [];
                    $b['damages'][] = ['desc' => trim($_POST['damage_desc']), 'fee' => (float)$_POST['damage_fee'] / $exchangeRateARS];
                    break;
                }
            }
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT)); safeRedirect("?tab=".$_POST['tab_redirect']);
        }

        if (isset($_POST['edit_guest_info'])) {
            foreach ($bookings as &$b) {
                if ($b['id'] === $_POST['booking_id']) {
                    $oldSt = strtolower($b['status'] ?? ''); $newSt = strtolower($_POST['edit_status']);
                    $b['status'] = $_POST['edit_status']; $b['guestName'] = $_POST['edit_guest_name']; $b['checkOut'] = $_POST['edit_check_out']; $b['roomId'] = $_POST['edit_room_id'];
                    if (isset($_POST['edit_passport'])) { $b['passport'] = $_POST['edit_passport']; }

                   // Subtract extras if they exist so we only override the base price
                    $lFee = isset($b['luggage']) ? (float)$b['luggage']['fee'] : 0;
                    $dFee = 0; if (!empty($b['damages'])) { foreach($b['damages'] as $dam) { $dFee += (float)$dam['fee']; } }

                    $b['totalPrice'] = ((float)$_POST['edit_total_price'] / $exchangeRateARS) - $lFee - $dFee;
                    $b['amountPaid'] = (float)$_POST['edit_amount_paid'] / $exchangeRateARS;

                    $gEmail = $b['email'] ?? '';

                    if ($oldSt !== 'confirmed' && $newSt === 'confirmed') {
                        // --- MAKE.COM WEBHOOK TRIGGER ---
                        triggerMakeWebhook($makeWebhookUrl, $b['guestName'], $b['phone'], $b['checkIn'], $b['checkOut']);

                        if(!empty($gEmail)) {
                            $year = date('Y');
                            $highEndEmail = "<div style='font-family: \"Helvetica Neue\", Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);'><div style='background-color: #0f172a; padding: 40px 20px; text-align: center;'><h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: 600; letter-spacing: 1px;'>HOSTEL PLAZA</h1><p style='color: #10b981; margin: 5px 0 0 0; font-size: 12px; text-transform: uppercase; letter-spacing: 2px;'>Mendoza, Argentina</p></div><div style='padding: 40px 30px;'><h2 style='color: #0f172a; font-size: 22px; margin-top: 0;'>Your stay is confirmed.</h2><p style='color: #475569; font-size: 16px; line-height: 1.6;'>Hello " . htmlspecialchars($b['guestName']) . ",<br><br>We are thrilled to officially confirm your reservation for <strong>" . $b['checkIn'] . "</strong>. Our team is getting everything ready for your arrival.</p><div style='margin-top: 30px;'><h3 style='color: #0f172a; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;'>Location & Directions</h3><p style='color: #475569; font-size: 14px; line-height: 1.5;'>We are located in the vibrant heart of Godoy Cruz, Mendoza. Click below for GPS directions straight to our door.</p><a href='https://www.google.com/maps/search/?api=1&query=Godoy+Cruz,+Mendoza,+Argentina' style='display: inline-block; color: #10b981; font-weight: 500; text-decoration: none; font-size: 14px; margin-top: 5px;'>📍 View on Google Maps</a></div></div><div style='background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;'><p style='color: #94a3b8; font-size: 12px; margin: 0;'>© $year Hostel Plaza Mendoza. All rights reserved.</p></div></div>";

                            sendHostelEmail($gEmail, "Booking Confirmed - Hostel Plaza Mendoza", $highEndEmail, "confirmation@hostelplaza.com.ar", $smtpPassword);
                        }
                    }

                    // Check if newly checked out to send Thank You Email
                    if ($oldSt !== 'checked out' && ($newSt === 'checked out' || $newSt === 'checked-out')) {
                        if (!empty($gEmail)) { sendCheckoutEmail($gEmail, $b['guestName']); }
                    }

                    break;
                }
            }
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT)); safeRedirect("?tab=".$_POST['tab_redirect']);
        }

        if (isset($_POST['confirm_payment'])) {
            foreach ($bookings as &$b) { if ($b['id'] === $_POST['booking_id']) { $b['amountPaid'] += (float)$_POST['payment_amount'] / $exchangeRateARS; $b['paymentMethod'] = $_POST['payment_method']; break; } }
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT)); safeRedirect("?tab=".$_POST['tab_redirect']);
        }

        if (isset($_POST['extend_stay'])) {
            foreach ($bookings as &$b) { if ($b['id'] === $_POST['booking_id']) { $b['checkOut'] = $_POST['new_check_out']; break; } }
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT)); safeRedirect("?tab=".$_POST['tab_redirect']);
        }

        if (isset($_POST['apply_discount'])) {
            $targetGuest = null; $discText = '';
            foreach ($bookings as &$b) {
                if ($b['id'] === $_POST['booking_id']) {
                    $type = $_POST['discount_type']; $val = (float)$_POST['discount_value'];
                    if ($type === 'percent') { $b['totalPrice'] = $b['totalPrice'] * (1 - ($val / 100)); $discText = $val . "%"; }
                    else { $b['totalPrice'] = max(0, $b['totalPrice'] - ($val / $exchangeRateARS)); $discText = "AR$ " . number_format($val); }
                    $targetGuest = $b; break;
                }
            }
            @file_put_contents('bookings.json', json_encode($bookings, JSON_PRETTY_PRINT));

            if ($targetGuest && !empty($targetGuest['email'])) {
                $promoCode = 'VIP-' . strtoupper(substr(uniqid(), -5));
                $expDate = date('F j, Y', strtotime('+6 months'));
                $gName = htmlspecialchars($targetGuest['guestName']);
                $gEmail = htmlspecialchars($targetGuest['email']);

                $htmlBody = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; background-color: #ffffff;'><div style='background-color: #205b5a; padding: 30px; text-align: center;'><h1 style='color: #ffffff; margin: 0; font-size: 28px; font-family: Georgia, serif; letter-spacing: 1px;'>Hostel Plaza</h1></div><div style='padding: 40px;'><h2 style='margin-top: 0; font-size: 18px; color: #111827;'>Dear " . $gName . ",</h2><p style='color: #4b5563; line-height: 1.6; font-size: 15px;'>We noticed you've stayed with us multiple times, and we want to take a moment to say <strong>thank you</strong>. Guests like you are the heartbeat of Hostel Plaza.</p><p style='color: #4b5563; line-height: 1.6; font-size: 15px;'>As a token of our deepest appreciation for your loyalty, we would love to offer you an exclusive discount on your next visit to Mendoza.</p><div style='background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center; margin: 30px 0;'><p style='font-size: 11px; font-weight: bold; color: #64748b; letter-spacing: 1.5px; text-transform: uppercase; margin: 0 0 15px 0;'>Your Exclusive Promo Code</p><p style='font-size: 36px; font-weight: bold; color: #205b5a; margin: 0 0 15px 0; letter-spacing: 2px;'>" . $promoCode . "</p><p style='font-size: 16px; font-weight: bold; color: #10b981; margin: 0;'>" . $discText . " OFF your next stay!</p></div><ul style='color: #64748b; font-size: 13px; line-height: 1.5; padding-left: 20px; margin-bottom: 30px;'><li>Valid exclusively for bookings made with this email address (<a href='mailto:" . $gEmail . "' style='color: #3b82f6;'>" . $gEmail . "</a>).</li><li>Expires on: " . $expDate . " (6 months from today).</li></ul><p style='color: #4b5563; font-size: 15px; margin-bottom: 20px;'>We can't wait to welcome you back home.</p><p style='color: #111827; font-size: 15px; font-weight: bold; margin: 0;'>Warm regards,<br>The Hostel Plaza Team</p></div></div>";

                // FIXED: Used the valid password for the confirmation account (since discount@ doesn't exist natively on your server)
                sendHostelEmail($targetGuest['email'], "Your VIP Loyalty Discount - Hostel Plaza", $htmlBody, "confirmation@hostelplaza.com.ar", $smtpPassword);
            }
            safeRedirect("?tab=".$_POST['tab_redirect']);
        }
    } catch (Throwable $t) { $fallbackTab = isset($_POST['tab_redirect']) ? $_POST['tab_redirect'] : 'overview'; safeRedirect("?tab=" . $fallbackTab); }
}

// --- 9. LOGIC CALCULATIONS ---
$checkInsToday = 0; $checkOutsToday = 0; $currentlyStaying = 0; $failedPayments = 0; $cancelledCount = 0;
$bookingComCount = 0; $hostelworldCount = 0;
$revData = ['Cash'=>['bg-emerald-50 text-emerald-700 border-emerald-200', 0, 0], 'Visa'=>['bg-blue-50 text-blue-700 border-blue-200', 0, 0], 'Mastercard'=>['bg-indigo-50 text-indigo-700 border-indigo-200', 0, 0], 'Mercado Pago'=>['bg-cyan-50 text-cyan-700 border-cyan-200', 0, 0]];
$totalRev = 0; $pendingCount = 0;

foreach($bookings as $b) {
    $stat = strtolower($b['status'] ?? '');
    $lFee = isset($b['luggage']) ? (float)$b['luggage']['fee'] : 0;
    $dFee = 0; if (!empty($b['damages'])) { foreach($b['damages'] as $dam) { $dFee += (float)$dam['fee']; } }
    $tPrice = (float)($b['totalPrice'] ?? 0) + $lFee + $dFee;
    $bal = ($tPrice - (float)($b['amountPaid'] ?? 0)) * $exchangeRateARS;

    if (!in_array($stat, ['cancelled', 'checked out'])) {
        $src = strtolower(trim($b['source'] ?? ''));
        if (strpos($src, 'booking') !== false) { $bookingComCount++; } elseif (strpos($src, 'hostelworld') !== false) { $hostelworldCount++; }
    }
    if (in_array($stat, ['pending', 'unconfirmed', 'un-confirmed'])) $pendingCount++;
    if($stat === 'cancelled') { $cancelledCount++; continue; }
    if($b['checkIn'] === $today) $checkInsToday++; if($b['checkOut'] === $today) $checkOutsToday++;
    if($today >= $b['checkIn'] && $today < $b['checkOut']) $currentlyStaying++;
    if($bal > 0 && strtotime($b['checkOut'] ?? '') < strtotime($today)) $failedPayments++;
    if (in_array($stat, ['confirmed', 'checked in'])) { $p = (float)($b['amountPaid'] ?? 0); $totalRev += $p; $m = $b['paymentMethod'] ?? 'Other'; if(isset($revData[$m])) { $revData[$m][1] += $p; $revData[$m][2]++; } }
}

$totalExpARS = 0;
foreach($expenses as $e) { $totalExpARS += (float)$e['amount']; }
$totalRevARS = $totalRev * $exchangeRateARS;
$netProfitARS = $totalRevARS - $totalExpARS;

$occupiedRooms = [];
foreach($bookings as $b) {
    $st = strtolower($b['status'] ?? '');
    if($today >= $b['checkIn'] && $today < $b['checkOut'] && in_array($st, ['checked in', 'checked-in', 'confirmed', 'pending', 'un-confirmed', 'unconfirmed'])) {
        if($b['roomId'] !== 'unassigned') $occupiedRooms[$b['roomId']] = true;
    }
}
$occRate = count($rooms) > 0 ? round((count($occupiedRooms) / count($rooms)) * 100) : 0;

$chartDates = []; $weeklyCI = []; $weeklyCO = [];
for($i=0; $i<7; $i++) { $d = date('Y-m-d', strtotime("+$i days")); $chartDates[] = date('D', strtotime($d)); $ci = 0; $co = 0; foreach($bookings as $b) { if($b['checkIn'] === $d) $ci++; if($b['checkOut'] === $d) $co++; } $weeklyCI[] = $ci; $weeklyCO[] = $co; }

$viewStart = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$startDate = new DateTime($viewStart); $endDate = (clone $startDate)->modify('+13 days');
$daysInView = []; $period = new DatePeriod($startDate, new DateInterval('P1D'), (clone $endDate)->modify('+1 day'));
foreach ($period as $date) { $daysInView[] = $date; }

$totalDays = count($daysInView); $dayWidth = 100 / max(1, $totalDays);

$statusConfig = ['un-confirmed' => ['color' => 'bg-sky-400', 'label' => 'Un-confirmed'], 'confirmed' => ['color' => 'bg-emerald-500', 'label' => 'Confirmed'], 'checked in' => ['color' => 'bg-purple-500', 'label' => 'Checked in'], 'checked out' => ['color' => 'bg-pink-500', 'label' => 'Checked out']];

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

$chartStaffNames = []; $chartStaffCompletion = [];
foreach ($checklistsDB as $cl) {
    $tot = count($cl['tasks'] ?? []); $d = 0;
    if ($tot > 0) { foreach($cl['tasks'] as $tsk) { if($tsk['done'] ?? false) $d++; } }
    $chartStaffNames[] = $cl['staffName']; $chartStaffCompletion[] = $tot > 0 ? round(($d/$tot)*100) : 0;
}

$avatarColors = ['bg-rose-100 text-rose-700', 'bg-blue-100 text-blue-700', 'bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Admin Master | Hostel Plaza</title>
    <script src="https://cdn.tailwindcss.com"></script><script src="https://unpkg.com/lucide@latest"></script><script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,600;1,600&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457' } }, fontFamily: { serif: ['"Playfair Display"', 'serif'], sans: ['"Inter"', 'sans-serif'] } } } }</script>
    <style>
        .tab-content { display: none; } .tab-content.active { display: block; animation: fadeIn 0.3s ease-in-out; }
        .modal-overlay { background: rgba(15,23,42,0.5); backdrop-filter: blur(8px); }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; } }
        .cal-scroll::-webkit-scrollbar { display: none; } .cal-scroll { -ms-overflow-style: none; scrollbar-width: none; }
        .booking-pill { position: absolute; height: 28px; border-radius: 8px; display: flex; align-items: center; padding: 0 0.5rem; color: white; cursor: grab; z-index: 20; border: 1px solid rgba(255,255,255,0.4); box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.1); transition: box-shadow 0.15s ease; }
        .booking-pill:hover { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.15); z-index: 30; } .booking-pill:active { cursor: grabbing; }
        .sidebar-tab-active { border-bottom: 2px solid #10b981; color: #059669; }
        .skiptranslate { display: none !important; }
        body { top: 0px !important; }
    </style>
</head>
<body class="bg-[#f8fafc] font-sans text-slate-800 overflow-x-hidden">

    <div class="flex flex-col md:flex-row min-h-screen">

        <div class="md:hidden bg-slate-900 text-white p-4 flex justify-between items-center z-30 sticky top-0 shadow-md w-full">
            <h1 class="text-xl font-serif font-semibold tracking-tight">Hostel Plaza</h1>
            <button onclick="toggleMobileMenu()" class="p-1 hover:bg-slate-800 rounded-lg"><i data-lucide="menu" class="w-6 h-6"></i></button>
        </div>

        <div id="mobileOverlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 hidden md:hidden" onclick="toggleMobileMenu()"></div>

        <aside id="mainSidebar" class="w-64 bg-slate-900 text-slate-300 flex flex-col fixed inset-y-0 left-0 z-50 shadow-2xl transition-transform transform -translate-x-full md:translate-x-0 h-full">
            <div class="p-6 border-b border-slate-800 shrink-0 flex justify-between items-center">
                <div><h1 class="text-2xl font-serif font-semibold text-white tracking-tight">Hostel Plaza</h1><p class="text-[10px] text-teal-400 uppercase mt-1 font-medium">Admin Management</p></div>
                <button onclick="toggleMobileMenu()" class="md:hidden p-1 text-slate-400 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>
            <nav class="flex-1 py-4 px-4 space-y-1 overflow-y-auto">
                <button onclick="switchTab('overview')" class="nav-btn w-full text-left p-3 rounded-xl hover:bg-slate-800 transition-all flex items-center gap-3 <?php echo $activeTab==='overview'?'bg-teal-700 text-white font-medium':''; ?>" data-target="overview"><i data-lucide="layout-dashboard" class="w-5 h-5 shrink-0 flex-none"></i> <span class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">Overview</span></button>
                <button onclick="switchTab('finances')" class="nav-btn w-full text-left p-3 rounded-xl hover:bg-slate-800 transition-all flex items-center gap-3 <?php echo $activeTab==='finances'?'bg-teal-700 text-white font-medium':''; ?>" data-target="finances"><i data-lucide="pie-chart" class="w-5 h-5 shrink-0 flex-none"></i> <span class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">Financial Ledger</span></button>

                <button onclick="switchTab('calendar')" class="nav-btn w-full text-left p-3 rounded-xl hover:bg-slate-800 transition-all flex items-center gap-3 <?php echo $activeTab==='calendar'?'bg-teal-700 text-white font-medium':''; ?>" data-target="calendar"><i data-lucide="calendar" class="w-5 h-5 shrink-0 flex-none"></i> <span class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">Guest Calendar</span></button>

                <button onclick="switchTab('reservations')" class="nav-btn w-full flex items-center justify-between p-3 rounded-xl hover:bg-slate-800 transition-all <?php echo $activeTab==='reservations'?'bg-teal-700 text-white font-medium':''; ?>" data-target="reservations">
                    <div class="flex items-center gap-3 min-w-0"><i data-lucide="calendar-days" class="w-5 h-5 shrink-0 flex-none"></i> <span class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">Reservations</span></div>
                    <?php if($pendingCount > 0): ?><span class="bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm shrink-0"><?php echo $pendingCount; ?> New</span><?php endif; ?>
                </button>

                <button onclick="switchTab('guests')" class="nav-btn w-full text-left p-3 rounded-xl hover:bg-slate-800 transition-all flex items-center gap-3 <?php echo $activeTab==='guests'?'bg-teal-700 text-white font-medium':''; ?>" data-target="guests"><i data-lucide="users" class="w-5 h-5 shrink-0 flex-none"></i> <span class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">Guests</span></button>

                <button onclick="toggleMenu('content_submenu', 'content_chevron')" class="nav-btn w-full flex items-center justify-between p-3 rounded-xl hover:bg-slate-800 transition-all mt-2">
                    <div class="flex items-center gap-3 min-w-0"><i data-lucide="layout-template" class="w-5 h-5 shrink-0 flex-none"></i> <span class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">Website Content</span></div>
                    <i data-lucide="chevron-down" id="content_chevron" class="w-4 h-4 transition-transform shrink-0 flex-none <?php echo in_array($activeTab, ['rooms', 'weekly_events', 'plaza_events']) ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div id="content_submenu" class="<?php echo in_array($activeTab, ['rooms', 'weekly_events', 'plaza_events']) ? '' : 'hidden'; ?> pl-11 pr-4 py-2 space-y-1">
                    <button onclick="switchTab('rooms')" class="nav-btn w-full text-left p-2 rounded-lg hover:bg-slate-800 text-sm transition-all overflow-hidden text-ellipsis whitespace-nowrap <?php echo $activeTab==='rooms'?'text-white font-medium':'text-slate-400'; ?>" data-target="rooms">Edit Rooms</button>
                    <button onclick="switchTab('weekly_events')" class="nav-btn w-full text-left p-2 rounded-lg hover:bg-slate-800 text-sm transition-all overflow-hidden text-ellipsis whitespace-nowrap <?php echo $activeTab==='weekly_events'?'text-white font-medium':'text-slate-400'; ?>" data-target="weekly_events">Weekly Events</button>
                    <button onclick="switchTab('plaza_events')" class="nav-btn w-full text-left p-2 rounded-lg hover:bg-slate-800 text-sm transition-all overflow-hidden text-ellipsis whitespace-nowrap <?php echo $activeTab==='plaza_events'?'text-white font-medium':'text-slate-400'; ?>" data-target="plaza_events">Plaza Events</button>
                </div>

                <button onclick="toggleMenu('staff_submenu', 'staff_chevron')" class="nav-btn w-full flex items-center justify-between p-3 rounded-xl hover:bg-slate-800 transition-all mt-2">
                    <div class="flex items-center gap-3 min-w-0"><i data-lucide="shield-half" class="w-5 h-5 shrink-0 flex-none"></i> <span class="font-medium whitespace-nowrap overflow-hidden text-ellipsis">Staff</span></div>
                    <i data-lucide="chevron-down" id="staff_chevron" class="w-4 h-4 transition-transform shrink-0 flex-none <?php echo in_array($activeTab, ['staff', 'checklists']) ? 'rotate-180' : ''; ?>"></i>
                </button>
                <div id="staff_submenu" class="<?php echo in_array($activeTab, ['staff', 'checklists']) ? '' : 'hidden'; ?> pl-11 pr-4 py-2 space-y-1">
                    <button onclick="switchTab('staff')" class="nav-btn w-full text-left p-2 rounded-lg hover:bg-slate-800 text-sm transition-all overflow-hidden text-ellipsis whitespace-nowrap <?php echo $activeTab==='staff'?'text-white font-medium':'text-slate-400'; ?>" data-target="staff">Schedule</button>
                    <button onclick="switchTab('checklists')" class="nav-btn w-full text-left p-2 rounded-lg hover:bg-slate-800 text-sm transition-all overflow-hidden text-ellipsis whitespace-nowrap <?php echo $activeTab==='checklists'?'text-white font-medium':'text-slate-400'; ?>" data-target="checklists">Checklists</button>
                </div>
            </nav>
            <div class="p-4 border-t border-slate-800 space-y-3 shrink-0">
                <button onclick="location.reload()" class="w-full bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 p-2.5 rounded-xl font-medium text-sm transition-all border border-slate-700 flex items-center justify-center gap-2"><i data-lucide="refresh-cw" class="w-4 h-4 shrink-0 flex-none"></i> <span class="whitespace-nowrap overflow-hidden text-ellipsis">Refresh Data</span></button>
                <div class="flex gap-2">
                    <button onclick="setLanguage('en')" class="flex-1 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 py-2.5 rounded-xl text-xs font-bold transition-all border border-slate-700">EN</button>
                    <button onclick="setLanguage('es')" class="flex-1 bg-slate-800 text-slate-300 hover:text-white hover:bg-slate-700 py-2.5 rounded-xl text-xs font-bold transition-all border border-slate-700">ES</button>
                </div>
                <button onclick="location.href='logout.php'" class="w-full bg-teal-700 p-3 rounded-xl font-medium text-white text-sm shadow-md whitespace-nowrap overflow-hidden text-ellipsis">Logout</button>
            </div>
        </aside>

        <main class="flex-1 md:ml-64 flex flex-col pb-12 relative w-full md:max-w-[calc(100%-16rem)]">

            <div id="overview" class="tab-content h-full <?php echo $activeTab==='overview'?'active':''; ?>">
                <?php if($pendingCount > 0): ?>
                <div class="px-4 md:px-8 pt-8">
                    <div class="bg-rose-50 border border-rose-200 p-5 rounded-2xl flex flex-col md:flex-row items-start md:items-center justify-between shadow-sm gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-full bg-white shadow-sm flex items-center justify-center text-rose-500 shrink-0"><i data-lucide="bell-ring" class="w-6 h-6"></i></div>
                            <div>
                                <h3 class="text-rose-800 font-bold text-lg leading-tight">You have <?php echo $pendingCount; ?> pending reservations!</h3>
                                <p class="text-rose-600 text-sm font-medium">Head to the reservations tab to review and confirm them.</p>
                            </div>
                        </div>
                        <button onclick="switchTab('reservations')" class="px-6 py-3 w-full md:w-auto bg-rose-500 text-white text-sm font-bold rounded-xl shadow-md hover:bg-rose-600 transition-all">Review Now</button>
                    </div>
                </div>
                <?php endif; ?>

                <div class="px-4 md:px-8 pt-8 grid grid-cols-2 md:grid-cols-5 gap-4">
                    <?php foreach($revData as $lbl => $v): ?>
                    <div class="<?php echo $v[0]; ?> p-4 rounded-2xl border shadow-sm flex flex-col justify-center min-w-0"><p class="text-[10px] font-semibold uppercase truncate opacity-80"><?php echo $lbl; ?> (<?php echo $v[2]; ?>)</p><p class="text-sm font-semibold truncate mt-1">AR$ <?php echo number_format($v[1]*$exchangeRateARS, 0); ?></p></div>
                    <?php endforeach; ?>
                    <div class="bg-slate-900 p-4 rounded-2xl text-white flex flex-col justify-center shadow-lg col-span-2 md:col-span-1"><p class="text-[10px] uppercase opacity-60 font-semibold">Total Revenue</p><p class="text-lg font-semibold text-teal-300 truncate">AR$ <?php echo number_format($totalRev*$exchangeRateARS, 0); ?></p></div>
                </div>
                <div class="bg-[#3b82f6] text-white p-8 md:p-12 pb-24 mt-6 rounded-b-[3.5rem] shadow-lg relative mx-4"><div class="max-w-7xl mx-auto"><h2 class="text-4xl md:text-5xl font-semibold tracking-tight"><?php echo $greeting; ?>, Admin!</h2><p class="text-blue-100 mt-2 text-lg font-medium opacity-90 italic">Daily Stats.</p></div></div>
                <div class="max-w-7xl mx-auto px-4 md:px-8 -mt-16 relative z-10 grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12 items-start">
                    <div class="lg:col-span-2 space-y-8">
                        <div class="bg-white p-6 md:p-8 rounded-[3rem] shadow-xl border border-slate-100"><div class="grid grid-cols-2 md:grid-cols-5 gap-6 text-center">
                            <div class="space-y-4"><div class="p-4 md:p-6 rounded-[2.5rem] bg-slate-50/50 border border-slate-100 hover:bg-white transition-all"><p class="text-4xl md:text-5xl font-semibold text-slate-900"><?php echo $checkInsToday; ?></p><p class="text-[10px] font-medium uppercase text-slate-400 mt-1">Check-ins</p></div></div>
                            <div class="space-y-4"><div class="p-4 md:p-6 rounded-[2.5rem] bg-slate-50/50 border border-slate-100 hover:bg-white transition-all"><p class="text-4xl md:text-5xl font-semibold text-slate-900"><?php echo $checkOutsToday; ?></p><p class="text-[10px] font-medium uppercase text-slate-400 mt-1">Check-outs</p></div></div>
                            <div class="space-y-4 text-center"><div class="p-4 md:p-6 rounded-[2.5rem] bg-slate-50/50 border border-slate-100 hover:bg-white transition-all"><p class="text-4xl md:text-5xl font-semibold text-slate-900"><?php echo $currentlyStaying; ?></p><p class="text-[10px] font-medium uppercase text-slate-400 mt-1">Staying</p></div></div>
                            <div class="space-y-4 text-center"><div class="p-4 md:p-6 rounded-[2.5rem] bg-slate-50/50 border border-slate-100 hover:bg-white transition-all"><p class="text-4xl md:text-5xl font-semibold text-blue-600"><?php echo $bookingComCount; ?></p><p class="text-[10px] font-bold uppercase text-slate-400 mt-1">Booking.com</p></div></div>
                            <div class="space-y-4 text-center"><div class="p-4 md:p-6 rounded-[2.5rem] bg-slate-50/50 border border-slate-100 hover:bg-white transition-all"><p class="text-4xl md:text-5xl font-semibold text-orange-500"><?php echo $hostelworldCount; ?></p><p class="text-[10px] font-bold uppercase text-slate-400 mt-1">Hostelworld</p></div></div>
                        </div></div>
                        <div class="bg-white p-6 md:p-8 pb-16 rounded-[3rem] shadow-xl border h-[450px]"><h3 class="font-semibold text-slate-900 text-sm mb-6 uppercase tracking-wider">Weekly activity</h3><div class="h-full w-full"><canvas id="weeklyActivityChart"></canvas></div></div>
                    </div>
                    <div class="bg-white p-6 md:p-8 rounded-[3rem] shadow-xl border h-fit"><h3 class="text-xl font-semibold mb-8 flex items-center gap-2"><div class="w-2.5 h-2.5 rounded-full bg-amber-400 animate-pulse"></div> Needs attention</h3>
                        <div class="space-y-4"><div class="p-5 bg-slate-50/50 rounded-[2rem] border flex gap-4"><div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center shrink-0 text-amber-600 shadow-sm"><i data-lucide="alert-triangle" class="w-5 h-5"></i></div><div><p class="text-sm font-semibold text-slate-900"><?php echo $failedPayments; ?> failed payments</p></div></div></div>
                    </div>
                </div>
            </div>

            <div id="finances" class="tab-content h-full flex-1 p-6 md:p-10 <?php echo $activeTab==='finances'?'active':''; ?>">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
                    <div>
                        <h2 class="text-3xl font-semibold text-slate-900">Financial Ledger</h2>
                        <p class="text-sm font-medium text-slate-400 mt-1">Track your expenses, revenue, and net profit.</p>
                    </div>
                    <button onclick="openModal('addExpenseModal')" class="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-2xl font-semibold shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2 w-full md:w-auto">
                        <i data-lucide="plus" class="w-5 h-5"></i> Add Expense
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col justify-center">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Revenue</p>
                        <p class="text-2xl font-bold text-emerald-600">AR$ <?php echo number_format($totalRevARS); ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col justify-center">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Total Expenses</p>
                        <p class="text-2xl font-bold text-rose-500">AR$ <?php echo number_format($totalExpARS); ?></p>
                    </div>
                    <div class="bg-slate-900 p-6 rounded-[2rem] shadow-lg flex flex-col justify-center">
                        <p class="text-[10px] font-bold text-teal-400 uppercase tracking-widest mb-1">Net Profit</p>
                        <p class="text-2xl font-bold text-white">AR$ <?php echo number_format($netProfitARS); ?></p>
                    </div>
                    <div class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm flex flex-col justify-center relative overflow-hidden">
                        <div class="absolute right-0 bottom-0 opacity-5 text-amber-500"><i data-lucide="home" class="w-24 h-24 -mb-4 -mr-4"></i></div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Current Occupancy</p>
                        <p class="text-2xl font-bold text-amber-500"><?php echo $occRate; ?>%</p>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 w-full overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-auto min-w-[600px]">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-slate-100">
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Date</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Description</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Category</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right">Amount</th>
                                    <th class="px-6 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php foreach(array_reverse($expenses) as $e): ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group">
                                    <td class="px-6 py-4 text-sm font-semibold text-slate-600 whitespace-nowrap"><?php echo date('M j, Y', strtotime($e['date'])); ?></td>
                                    <td class="px-6 py-4 text-sm font-bold text-slate-900"><?php echo htmlspecialchars($e['description']); ?></td>
                                    <td class="px-6 py-4"><span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg text-[10px] font-bold uppercase tracking-widest border border-slate-200 whitespace-nowrap"><?php echo htmlspecialchars($e['category']); ?></span></td>
                                    <td class="px-6 py-4 text-right font-bold text-sm text-rose-500 whitespace-nowrap">- AR$ <?php echo number_format($e['amount']); ?></td>
                                    <td class="px-6 py-4 text-center">
                                        <form method="POST" class="inline" onsubmit="return confirm('Delete this expense?');">
                                            <input type="hidden" name="delete_expense" value="1"><input type="hidden" name="expense_id" value="<?php echo $e['id']; ?>">
                                            <button type="submit" class="p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-xl transition-colors"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($expenses)): ?>
                                <tr><td colspan="5" class="px-6 py-8 text-center text-sm font-medium text-slate-400">No expenses recorded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="calendar" class="tab-content h-full flex-1 p-6 md:p-8 <?php echo $activeTab==='calendar'?'active':''; ?>">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
                    <div>
                        <h2 class="text-3xl font-semibold text-slate-900">Booking Calendar</h2>
                        <p class="text-sm font-medium text-slate-400 mt-1">Drag and drop guests to change their dates or reassign rooms instantly.</p>
                    </div>
                    <div class="flex gap-2 bg-white border border-slate-200 p-2 rounded-2xl shadow-sm w-full md:w-auto justify-between">
                        <button onclick="moveCalendar(-7)" class="p-2 hover:bg-slate-50 text-slate-600 rounded-xl transition-all"><i data-lucide="chevron-left" class="w-5 h-5"></i></button>
                        <span id="calendar_date_range" class="text-sm font-semibold px-4 text-center flex items-center justify-center whitespace-nowrap"><?php echo $startDate->format('M d') . ' - ' . $endDate->format('M d, Y'); ?></span>
                        <button onclick="moveCalendar(7)" class="p-2 hover:bg-slate-50 text-slate-600 rounded-xl transition-all"><i data-lucide="chevron-right" class="w-5 h-5"></i></button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 md:gap-4 mb-6 px-2">
                    <div class="flex items-center gap-2 text-[10px] md:text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full bg-sky-400 shadow-sm shrink-0"></span>Un-confirmed</div>
                    <div class="flex items-center gap-2 text-[10px] md:text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full bg-emerald-500 shadow-sm shrink-0"></span>Confirmed</div>
                    <div class="flex items-center gap-2 text-[10px] md:text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full bg-purple-500 shadow-sm shrink-0"></span>Checked In</div>
                    <div class="flex items-center gap-2 text-[10px] md:text-xs font-semibold text-slate-600"><span class="w-3 h-3 rounded-full bg-pink-500 shadow-sm shrink-0"></span>Checked Out</div>
                    <div class="w-full md:w-auto md:ml-auto flex items-center gap-2 bg-white px-4 py-2 rounded-xl border border-slate-200 shadow-sm text-sm font-bold text-slate-700 justify-center">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
                        Currently in Hostel: <?php echo $currentlyStaying; ?>
                    </div>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl border overflow-x-auto flex flex-col">
                    <div class="w-full cal-scroll relative min-w-[1200px]" style="height: calc(100vh - 250px);">
                        <div class="flex border-b sticky top-0 bg-white z-40">
                            <div class="w-48 md:w-72 px-4 md:px-6 py-5 font-bold text-[10px] text-slate-400 uppercase tracking-widest border-r border-slate-200 bg-slate-50/50 shrink-0 flex items-center shadow-[1px_0_5px_rgba(0,0,0,0.02)]">Room</div>
                            <div class="flex-1 flex bg-slate-50/20">
                                <?php foreach($daysInView as $day): $isToday = $day->format('Y-m-d') === date('Y-m-d'); ?>
                                    <div class="py-3 text-center border-r border-slate-200 relative <?php echo $isToday ? 'bg-emerald-50/30' : ''; ?>" style="width: <?php echo $dayWidth; ?>%;">
                                        <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $day->format('D'); ?></p>
                                        <p class="text-base font-bold mt-0.5 <?php echo $isToday ? 'text-emerald-600' : 'text-slate-700'; ?>"><?php echo $day->format('d'); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="divide-y border-slate-200 flex flex-col">
                            <?php
                            $endDatePlusOne = clone $endDate; $endDatePlusOne->modify('+1 day');
                            $calendarRooms = array_merge([['id' => 'unassigned', 'name' => '⚠️ UNASSIGNED (Drag to Room)']], $rooms);

                            // Pre-calculate daily occupancy to drive the "X LEFT" counters
                            $occMatrix = [];
                            foreach($bookings as $b) {
                                $st = strtolower($b['status'] ?? '');
                                if($st === 'cancelled') continue;
                                if($b['roomId'] === 'unassigned' || empty($b['roomId'])) continue;

                                $ci = strtotime($b['checkIn']);
                                $co = strtotime($b['checkOut']);
                                for($d = $ci; $d < $co; $d += 86400) {
                                    $dStr = date('Y-m-d', $d);
                                    if(!isset($occMatrix[$b['roomId']][$dStr])) $occMatrix[$b['roomId']][$dStr] = 0;
                                    $occMatrix[$b['roomId']][$dStr]++;
                                }
                            }

                            foreach($calendarRooms as $room):
                                $roomBookings = [];
                                foreach($bookings as $res) {
                                    if($res['roomId'] != $room['id'] || strtolower($res['status']??'') == 'cancelled') continue;

                                    $ci = new DateTime($res['checkIn']); $co = new DateTime($res['checkOut']);
                                    if ($co <= $startDate || $ci >= $endDatePlusOne) continue;
                                    $roomBookings[] = $res;
                                }

                                // Sort by check-in date so earlier bookings claim the top lanes
                                usort($roomBookings, function($a, $b) {
                                    return strtotime($a['checkIn']) - strtotime($b['checkIn']);
                                });

                                // Stacking Logic (Lanes)
                                $lanes = [];
                                foreach ($roomBookings as &$res) {
                                    $ci = strtotime($res['checkIn'] . ' 00:00:00');
                                    $co = strtotime($res['checkOut'] . ' 23:59:59');
                                    $assignedLane = 0;

                                    while(true) {
                                        if (!isset($lanes[$assignedLane]) || $ci >= $lanes[$assignedLane]) {
                                            $lanes[$assignedLane] = $co;
                                            $res['lane'] = $assignedLane;
                                            break;
                                        }
                                        $assignedLane++;
                                    }
                                }

                                // Force exact tall row heights matching staff.php visual style
                                $maxLanes = max(1, count($lanes));
                                $rowHeight = max(180, 40 + ($maxLanes * 36));

                                // Determine Capacity for the "X LEFT" label
                                if ($room['type'] === 'Superior Private' || strpos(strtolower($room['name']), 'private') !== false) {
                                    $roomCap = 1;
                                } else {
                                    $roomCap = $room['capacity'] ?? 4;
                                }
                            ?>
                            <div class="flex group relative hover:bg-slate-50/40 transition-colors border-b border-slate-200" style="min-height: <?php echo $rowHeight; ?>px;">
                                <div class="w-48 md:w-72 px-4 md:px-6 py-6 border-r border-slate-200 sticky left-0 <?php echo $room['id'] === 'unassigned' ? 'bg-amber-50 text-amber-700 border-b border-amber-200' : 'bg-white text-slate-700 group-hover:bg-slate-50'; ?> z-30 font-semibold text-sm flex items-start shrink-0 shadow-[1px_0_5px_rgba(0,0,0,0.02)] transition-colors tracking-tight"><?php echo htmlspecialchars($room['name']); ?></div>
                                <div class="flex-1 flex relative <?php echo $room['id'] === 'unassigned' ? 'bg-amber-50/30' : ''; ?>" ondragover="allowDrop(event)" ondrop="drop(event, '<?php echo $room['id']; ?>')">

                                    <?php foreach($daysInView as $day):
                                        $dStr = $day->format('Y-m-d');
                                        $occ = $occMatrix[$room['id']][$dStr] ?? 0;
                                        $left = max(0, $roomCap - $occ);
                                    ?>
                                        <div class="border-r border-slate-200 h-full relative" style="width: <?php echo $dayWidth; ?>%;">
                                            <?php if ($room['id'] !== 'unassigned'): ?>
                                                <span class="absolute top-3 right-3 text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $left; ?> LEFT</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php foreach($roomBookings as $res):
                                        $ciDateStr = $res['checkIn'];
                                        $coDateStr = $res['checkOut'];

                                        $startOffset = (strtotime($ciDateStr) - strtotime($startDate->format('Y-m-d'))) / 86400;
                                        $endOffset = (strtotime($coDateStr) - strtotime($startDate->format('Y-m-d'))) / 86400;

                                        $clampedStart = max(0, $startOffset);
                                        $clampedEnd = min($totalDays, $endOffset);

                                        $widthDays = $clampedEnd - $clampedStart;
                                        if ($widthDays <= 0) continue;

                                        $leftPct = ($clampedStart / $totalDays) * 100;
                                        $widthPct = ($widthDays / $totalDays) * 100;

                                        $color = getStatusClass($res['status'] ?? '');
                                        // Placed firmly below the top margin where "X LEFT" resides
                                        $topPx = 40 + ($res['lane'] * 36);
                                    ?>
                                        <div id="pill_<?php echo $res['id']; ?>" draggable="true" ondragstart="drag(event, '<?php echo $res['id']; ?>')" onclick="openBookingSidebar('<?php echo $res['id']; ?>')" class="booking-pill <?php echo $color; ?>" style="left: calc(<?php echo $leftPct; ?>% + 2px); top: <?php echo $topPx; ?>px; width: calc(<?php echo $widthPct; ?>% - 4px); height: 28px;">
                                            <div class="flex items-center gap-1.5 overflow-hidden truncate pointer-events-none">
                                                <span class="truncate tracking-wide text-[10.5px] font-semibold text-white px-2"><?php echo htmlspecialchars($res['guestName']); ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div id="reservations" class="tab-content h-full flex-1 p-6 md:p-10 <?php echo $activeTab==='reservations'?'active':''; ?>">
                <div class="bg-white rounded-3xl shadow-sm border overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[600px]">
                            <thead><tr class="bg-slate-50/50"><th class="px-6 py-4 text-xs font-medium text-slate-500 uppercase tracking-widest whitespace-nowrap">Guest Details</th><th class="px-6 py-4 text-xs font-medium text-slate-500 uppercase tracking-widest text-center whitespace-nowrap">Stay</th><th class="px-6 py-4 text-xs font-medium text-slate-500 uppercase tracking-widest text-center whitespace-nowrap">Status</th><th class="px-6 py-4 text-xs font-medium text-slate-500 uppercase tracking-widest text-right whitespace-nowrap">Actions</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                            <?php foreach($bookings as $b):
                                $statusCheck = strtolower(trim($b['status'] ?? ''));
                                if ($statusCheck === 'checked out' || $statusCheck === 'cancelled') continue;
                                $badge = getStatusClass($b['status']);
                                $init = implode('', array_map(fn($n) => strtoupper($n[0]), explode(' ', $b['guestName'])));
                            ?>
                                <tr class="hover:bg-slate-50/50 group">
                                    <td class="px-6 py-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-10 h-10 min-w-[40px] min-h-[40px] flex-none rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-semibold text-sm shrink-0 notranslate" translate="no"><?php echo substr($init, 0, 2); ?></div>
                                            <p class="font-medium text-slate-900 leading-none whitespace-nowrap flex items-center gap-2"><?php echo htmlspecialchars($b['guestName']); ?> <?php echo getSourceBadge($b['source'] ?? ''); ?></p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 font-medium text-center"><div class="flex flex-col gap-1 items-center"><div class="flex items-center gap-2 text-xs bg-emerald-50 w-fit px-2 py-1 rounded-lg whitespace-nowrap"><i data-lucide="log-in" class="w-3 h-3 text-emerald-500"></i> <?php echo $b['checkIn']; ?></div><div class="flex items-center gap-2 text-xs bg-rose-50 w-fit px-2 py-1 rounded-lg whitespace-nowrap"><i data-lucide="log-out" class="w-3 h-3 text-rose-500"></i> <?php echo $b['checkOut']; ?></div></div></td>
                                    <td class="px-6 py-5 text-center"><span class="px-6 py-2 rounded-full text-[10px] font-medium uppercase <?php echo $badge; ?> text-white min-w-[140px] inline-flex items-center justify-center whitespace-nowrap shadow-sm shrink-0"><?php echo empty($b['status']) ? 'Un-confirmed' : $b['status']; ?></span></td>
                                    <td class="px-6 py-5 text-right relative">
                                        <button type="button" onclick="toggleActionMenu('menu_res_<?php echo $b['id']; ?>')" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg transition-all"><i data-lucide="more-vertical" class="w-5 h-5"></i></button>
                                        <div id="menu_res_<?php echo $b['id']; ?>" class="action-menu hidden absolute right-10 top-8 w-44 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden flex flex-col text-left">
                                            <button type="button" onclick="openBookingSidebar('<?php echo $b['id']; ?>'); switchSidebarTab('guest');" class="w-full text-left px-4 py-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2"><i data-lucide="eye" class="w-4 h-4 text-slate-400"></i> View Profile</button>
                                            <?php if(!empty($b['phone'])): $waNum = preg_replace('/[^0-9]/', '', $b['phone']); ?>
                                                <a href="https://wa.me/<?php echo $waNum; ?>" target="_blank" class="w-full text-left px-4 py-3 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 flex items-center gap-2 border-t border-slate-50"><i data-lucide="message-circle" class="w-4 h-4 text-emerald-500"></i> WhatsApp</a>
                                            <?php endif; ?>
                                            <form method="POST" class="m-0 w-full border-t border-slate-50" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this guest record? This cannot be undone.');">
                                                <input type="hidden" name="delete_guest" value="1">
                                                <input type="hidden" name="guest_id" value="<?php echo $b['id']; ?>">
                                                <button type="submit" class="w-full text-left px-4 py-3 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2"><i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i> Delete Record</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="guests" class="tab-content h-full flex-1 p-6 md:p-8 <?php echo $activeTab==='guests'?'active':''; ?>">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4 mb-6">
                    <div>
                        <h2 class="text-3xl font-semibold text-slate-900">Guest Directory</h2>
                        <p class="text-sm font-medium text-slate-400 mt-1">Full breakdown of everyone who owes money or stayed with you.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                        <a href="?export_csv=1" class="flex-1 md:flex-none bg-slate-900 text-white px-4 py-3 rounded-2xl font-semibold flex justify-center items-center gap-2 hover:bg-slate-800 shadow-sm transition-all whitespace-nowrap">
                            <i data-lucide="download" class="w-4 h-4"></i> Export CSV
                        </a>
                        <button onclick="document.getElementById('csvInput').click()" class="flex-1 md:flex-none bg-white border border-slate-200 px-4 py-3 rounded-2xl font-semibold text-slate-700 flex justify-center items-center gap-2 hover:bg-slate-50 shadow-sm transition-all whitespace-nowrap">
                            <i data-lucide="upload-cloud" class="w-4 h-4"></i> Import CSV
                        </button>
                        <form id="csvForm" method="POST" enctype="multipart/form-data" class="hidden">
                            <input type="file" name="csv_file" id="csvInput" accept=".csv" onchange="document.getElementById('csvForm').submit()">
                            <input type="hidden" name="import_csv" value="1">
                        </form>
                        <div class="relative w-full md:w-72 mt-2 md:mt-0">
                            <i data-lucide="search" class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                            <input type="text" id="guestSearchInput" onkeyup="searchGuests()" class="w-full pl-11 pr-4 py-3 rounded-2xl border border-slate-200 focus:outline-none focus:border-teal-500 text-sm font-medium" placeholder="Search names...">
                            <div id="guestSearchDropdown" class="absolute left-0 right-0 top-full mt-2 bg-white rounded-2xl shadow-xl border border-slate-100 z-50 hidden max-h-64 overflow-y-auto"></div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 w-full overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse table-auto min-w-[800px]">
                            <thead>
                                <tr class="bg-slate-50/80 border-b border-slate-100">
                                    <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest w-2/12">Name & Nat.</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest w-2/12">Contact</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest w-2/12">Room & Dates</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right whitespace-nowrap">Total Due</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-right whitespace-nowrap">Balance</th>
                                    <th class="px-4 py-4 text-[10px] font-bold text-slate-400 uppercase tracking-widest text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100" id="guestTableBody">
                                <?php
                                foreach($bookings as $g):
                                    $lFee = isset($g['luggage']) ? (float)$g['luggage']['fee'] : 0;
                                    $dFee = 0; if (!empty($g['damages'])) { foreach($g['damages'] as $dam) { $dFee += (float)$dam['fee']; } }

                                    $tPrice = (float)($g['totalPrice'] ?? 0) + $lFee + $dFee;
                                    $bal = ($tPrice - (float)($g['amountPaid'] ?? 0)) * $exchangeRateARS;
                                    $rName = 'Unassigned'; foreach($rooms as $r){ if($r['id']==$g['roomId']){ $rName = $r['name']; break; } }

                                    $colorClass = $avatarColors[strlen($g['guestName']) % 5];

                                    $stCheck = strtolower(trim($g['status'] ?? ''));
                                    $statusBorder = '';
                                    if ($stCheck === 'confirmed') $statusBorder = 'border-l-4 border-emerald-400';
                                    elseif ($stCheck === 'checked in' || $stCheck === 'checked-in') $statusBorder = 'border-l-4 border-purple-400';
                                    elseif ($stCheck === 'checked out' || $stCheck === 'checked-out') $statusBorder = 'border-l-4 border-pink-400';
                                    else $statusBorder = 'border-l-4 border-sky-400';
                                ?>
                                <tr class="hover:bg-slate-50/50 transition-colors group guest-row <?php echo $statusBorder; ?>" data-name="<?php echo htmlspecialchars($g['guestName']); ?>">
                                    <td class="px-4 py-4 pl-6">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 min-w-[36px] min-h-[36px] flex-none rounded-full <?php echo $colorClass; ?> flex items-center justify-center font-bold text-sm shrink-0 shadow-sm notranslate" translate="no"><?php echo strtoupper($g['guestName'][0]); ?></div>
                                            <div class="min-w-0">
                                                <p class="font-bold text-slate-900 text-xs whitespace-normal leading-tight flex items-center flex-wrap gap-1.5"><?php echo htmlspecialchars($g['guestName']); ?> <?php echo getSourceBadge($g['source'] ?? ''); ?></p>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase mt-0.5"><?php echo $g['nationality'] ?? 'Global'; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 space-y-1">
                                        <div class="flex items-center gap-1.5 text-[10px] font-semibold text-slate-600 truncate whitespace-normal"><i data-lucide="mail" class="w-3 h-3 text-slate-400 shrink-0"></i> <?php echo $g['email'] ?: 'No Email'; ?></div>
                                        <div class="flex items-center gap-1.5 text-[10px] font-semibold text-slate-600 truncate whitespace-normal"><i data-lucide="phone" class="w-3 h-3 text-slate-400 shrink-0"></i> <?php echo $g['phone'] ?: 'No Phone'; ?></div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-[10px] font-bold text-slate-800 leading-tight mb-1 truncate whitespace-normal"><?php echo htmlspecialchars($rName); ?></p>
                                        <div class="inline-flex items-center gap-1">
                                            <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md text-[9px] font-bold border border-slate-200 whitespace-nowrap"><?php echo date('M j', strtotime($g['checkIn'])); ?></span>
                                            <i data-lucide="arrow-right" class="w-2.5 h-2.5 text-slate-400 shrink-0"></i>
                                            <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-md text-[9px] font-bold border border-slate-200 whitespace-nowrap"><?php echo date('M j', strtotime($g['checkOut'])); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-right font-bold text-xs text-slate-800 whitespace-nowrap">AR$ <?php echo number_format($tPrice * $exchangeRateARS); ?></td>
                                    <td class="px-4 py-4 text-right whitespace-nowrap">
                                        <?php if ($bal <= 0): ?>
                                            <span class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200 inline-block shadow-sm">Settled</span>
                                        <?php else: ?>
                                            <span class="px-2 py-1 rounded-lg text-[10px] font-bold bg-rose-50 text-rose-600 border border-rose-100 inline-block shadow-sm">Owes AR$ <?php echo number_format($bal); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 text-center relative">
                                        <button type="button" onclick="toggleActionMenu('menu_<?php echo $g['id']; ?>')" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg transition-all"><i data-lucide="more-vertical" class="w-5 h-5"></i></button>
                                        <div id="menu_<?php echo $g['id']; ?>" class="action-menu hidden absolute right-10 top-8 w-44 bg-white rounded-xl shadow-xl border border-slate-100 z-50 overflow-hidden flex flex-col text-left">
                                            <button type="button" onclick="openBookingSidebar('<?php echo $g['id']; ?>'); switchSidebarTab('guest');" class="w-full text-left px-4 py-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 flex items-center gap-2"><i data-lucide="eye" class="w-4 h-4 text-slate-400"></i> View Profile</button>
                                            <?php if(!empty($g['phone'])): $waNum = preg_replace('/[^0-9]/', '', $g['phone']); ?>
                                                <a href="https://wa.me/<?php echo $waNum; ?>" target="_blank" class="w-full text-left px-4 py-3 text-xs font-semibold text-emerald-600 hover:bg-emerald-50 flex items-center gap-2 border-t border-slate-50"><i data-lucide="message-circle" class="w-4 h-4 text-emerald-500"></i> WhatsApp</a>
                                            <?php endif; ?>
                                            <?php if($g['stayCount'] > 2): ?>
                                                <button type="button" onclick="openLoyaltyModal('<?php echo $g['id']; ?>', '<?php echo htmlspecialchars(addslashes($g['guestName'])); ?>')" class="w-full text-left px-4 py-3 text-xs font-semibold text-amber-600 hover:bg-amber-50 flex items-center gap-2 border-t border-slate-50"><i data-lucide="award" class="w-4 h-4 text-amber-500"></i> Send Discount</button>
                                            <?php endif; ?>
                                            <form method="POST" class="m-0 w-full border-t border-slate-50" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this guest record? This cannot be undone.');">
                                                <input type="hidden" name="delete_guest" value="1">
                                                <input type="hidden" name="guest_id" value="<?php echo $g['id']; ?>">
                                                <button type="submit" class="w-full text-left px-4 py-3 text-xs font-semibold text-red-600 hover:bg-red-50 flex items-center gap-2"><i data-lucide="trash-2" class="w-4 h-4 text-red-500"></i> Delete Record</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="rooms" class="tab-content h-full flex-1 p-6 md:p-10 <?php echo $activeTab==='rooms'?'active':''; ?>">
                <div class="space-y-6 animate-in fade-in duration-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-3xl font-semibold text-slate-900">Edit Rooms</h2>
                            <p class="text-slate-500 mt-1 font-medium text-sm">Update photos, descriptions, and manage inventory.</p>
                        </div>
                        <button onclick="openModal('addRoomModal')" class="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-2xl font-medium shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-2">
                            <i data-lucide="plus" class="w-5 h-5"></i> Add Room
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach($rooms as $r): ?>
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col transition-all hover:shadow-md">
                            <div class="h-48 bg-slate-100 relative group">
                                <?php if(!empty($r['image'])): ?><img src="<?php echo htmlspecialchars($r['image']); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="Room"><?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-300"><i data-lucide="image" class="w-12 h-12"></i></div><?php endif; ?>
                            </div>
                            <div class="p-6 flex-1 flex flex-col">
                                <h3 class="text-lg font-semibold text-slate-900 mb-2"><?php echo htmlspecialchars($r['name']); ?></h3>
                                <p class="text-xs text-slate-500 mb-6 flex-1 line-clamp-3 leading-relaxed"><?php echo htmlspecialchars($r['description'] ?? 'No description provided.'); ?></p>
                                <div class="flex items-center gap-3 mt-auto pt-4 border-t border-slate-50">
                                    <button type="button" onclick="openEditRoomModal('<?php echo $r['id']; ?>', '<?php echo htmlspecialchars(addslashes($r['name'])); ?>', '<?php echo htmlspecialchars(addslashes($r['image'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes(preg_replace('/\r|\n/', ' ', $r['description'] ?? ''))); ?>')" class="flex-1 bg-slate-50 hover:bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-semibold transition-all border border-slate-200">Edit Details</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this room?');"><input type="hidden" name="delete_room" value="1"><input type="hidden" name="room_id" value="<?php echo $r['id']; ?>"><button type="submit" class="p-2.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all border border-transparent hover:border-red-100"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="weekly_events" class="tab-content h-full flex-1 p-6 md:p-10 <?php echo $activeTab==='weekly_events'?'active':''; ?>">
                <div class="space-y-6 animate-in fade-in duration-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-3xl font-semibold text-slate-900">Weekly Events</h2>
                            <p class="text-slate-500 mt-1 font-medium text-sm">Manage recurring weekly events shown on the website.</p>
                        </div>
                        <button onclick="openModal('addWeeklyEventModal')" class="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-2xl font-medium shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-2">
                            <i data-lucide="plus" class="w-5 h-5"></i> Add Weekly Event
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach($weeklyEvents as $e): ?>
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col transition-all hover:shadow-md">
                            <div class="h-48 bg-slate-100 relative group">
                                <div class="absolute top-4 left-4 z-10 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest text-slate-800 shadow-sm"><i data-lucide="calendar" class="w-3 h-3 inline mr-1 text-emerald-500"></i> <?php echo htmlspecialchars($e['day']); ?></div>
                                <?php if(!empty($e['image'])): ?><img src="<?php echo htmlspecialchars($e['image']); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="Event"><?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-300"><i data-lucide="image" class="w-12 h-12"></i></div><?php endif; ?>
                            </div>
                            <div class="p-6 flex-1 flex flex-col">
                                <h3 class="text-lg font-semibold text-slate-900 mb-2"><?php echo htmlspecialchars($e['name']); ?></h3>
                                <p class="text-xs text-slate-500 mb-6 flex-1 line-clamp-3 leading-relaxed"><?php echo htmlspecialchars($e['description'] ?? 'No description provided.'); ?></p>
                                <div class="flex items-center gap-3 mt-auto pt-4 border-t border-slate-50">
                                    <button type="button" onclick="openEditWeeklyEventModal('<?php echo $e['id']; ?>', '<?php echo htmlspecialchars(addslashes($e['name'])); ?>', '<?php echo htmlspecialchars(addslashes($e['day'])); ?>', '<?php echo htmlspecialchars(addslashes($e['image'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes(preg_replace('/\r|\n/', ' ', $e['description'] ?? ''))); ?>')" class="flex-1 bg-slate-50 hover:bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-semibold transition-all border border-slate-200">Edit Event</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this event?');"><input type="hidden" name="delete_weekly_event" value="1"><input type="hidden" name="we_id" value="<?php echo $e['id']; ?>"><button type="submit" class="p-2.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all border border-transparent hover:border-red-100"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="plaza_events" class="tab-content h-full flex-1 p-6 md:p-10 <?php echo $activeTab==='plaza_events'?'active':''; ?>">
                <div class="space-y-6 animate-in fade-in duration-500">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-3xl font-semibold text-slate-900">Plaza Events</h2>
                            <p class="text-slate-500 mt-1 font-medium text-sm">Manage one-off special events shown on the website.</p>
                        </div>
                        <button onclick="openModal('addPlazaEventModal')" class="bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-2xl font-medium shadow-lg shadow-emerald-500/20 transition-all flex items-center gap-2">
                            <i data-lucide="plus" class="w-5 h-5"></i> Add Plaza Event
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                        <?php foreach($plazaEvents as $e): ?>
                        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden flex flex-col transition-all hover:shadow-md">
                            <div class="h-48 bg-slate-100 relative group">
                                <div class="absolute top-4 left-4 z-10 bg-white/90 backdrop-blur-sm px-3 py-1.5 rounded-lg text-[10px] font-bold uppercase tracking-widest text-slate-800 shadow-sm"><i data-lucide="calendar-days" class="w-3 h-3 inline mr-1 text-purple-500"></i> <?php echo htmlspecialchars($e['date']); ?></div>
                                <?php if(!empty($e['image'])): ?><img src="<?php echo htmlspecialchars($e['image']); ?>" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" alt="Event"><?php else: ?><div class="w-full h-full flex items-center justify-center text-slate-300"><i data-lucide="image" class="w-12 h-12"></i></div><?php endif; ?>
                            </div>
                            <div class="p-6 flex-1 flex flex-col">
                                <h3 class="text-lg font-semibold text-slate-900 mb-2"><?php echo htmlspecialchars($e['name']); ?></h3>
                                <p class="text-xs text-slate-500 mb-6 flex-1 line-clamp-3 leading-relaxed"><?php echo htmlspecialchars($e['description'] ?? 'No description provided.'); ?></p>
                                <div class="flex items-center gap-3 mt-auto pt-4 border-t border-slate-50">
                                    <button type="button" onclick="openEditPlazaEventModal('<?php echo $e['id']; ?>', '<?php echo htmlspecialchars(addslashes($e['name'])); ?>', '<?php echo htmlspecialchars(addslashes($e['date'])); ?>', '<?php echo htmlspecialchars(addslashes($e['image'] ?? '')); ?>', '<?php echo htmlspecialchars(addslashes(preg_replace('/\r|\n/', ' ', $e['description'] ?? ''))); ?>')" class="flex-1 bg-slate-50 hover:bg-slate-100 text-slate-700 py-2.5 rounded-xl text-xs font-semibold transition-all border border-slate-200">Edit Event</button>
                                    <form method="POST" class="inline" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this event?');"><input type="hidden" name="delete_plaza_event" value="1"><input type="hidden" name="pe_id" value="<?php echo $e['id']; ?>"><button type="submit" class="p-2.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition-all border border-transparent hover:border-red-100"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div id="staff" class="tab-content h-full flex-1 p-6 md:p-10 <?php echo $activeTab==='staff'?'active':''; ?>">
                <div class="space-y-6 animate-in fade-in duration-500">
                  <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div>
                      <h2 class="text-3xl font-semibold text-slate-900">Staff Schedule</h2>
                      <p class="text-slate-500 mt-1 font-medium text-sm">Manage shifts and availability for your team</p>
                    </div>
                    <div class="flex gap-3 w-full md:w-auto">
                      <button onclick="openModal('addStaffModal')" class="flex-1 md:flex-none bg-white border border-slate-200 text-slate-700 px-6 py-3 rounded-2xl font-medium hover:bg-slate-50 transition-all flex items-center justify-center gap-2 shadow-sm whitespace-nowrap">
                        <i data-lucide="user-plus" class="w-5 h-5"></i> Add Staff
                      </button>
                      <button onclick="document.getElementById('schedule_form').submit();" class="flex-1 md:flex-none bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-2xl font-medium shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2 whitespace-nowrap">
                        <i data-lucide="save" class="w-5 h-5"></i> Save All
                      </button>
                    </div>
                  </div>

                  <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <form method="POST" id="schedule_form">
                      <input type="hidden" name="save_schedule" value="1">
                      <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse min-w-[800px]">
                          <thead>
                            <tr class="bg-slate-50/50">
                              <th class="px-6 py-6 text-xs font-semibold text-slate-400 uppercase tracking-widest border-r border-slate-100 w-64">Staff Name</th>
                              <?php foreach($daysOfWeek as $day): ?>
                                <th class="px-6 py-6 text-xs font-semibold text-slate-400 uppercase tracking-widest text-center"><?php echo $day; ?></th>
                              <?php endforeach; ?>
                            </tr>
                          </thead>
                          <tbody class="divide-y divide-slate-100">
                            <?php foreach($staffData as $staff): ?>
                              <tr class="group hover:bg-slate-50/30 transition-colors">
                                <td class="px-6 py-6 border-r border-slate-100 bg-slate-50/30">
                                  <div class="flex items-center justify-between">
                                      <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 min-w-[40px] min-h-[40px] rounded-full bg-white border border-slate-200 flex items-center justify-center font-semibold text-slate-700 shadow-sm shrink-0 flex-none notranslate" translate="no">
                                          <?php echo strtoupper($staff['name'][0]); ?>
                                        </div>
                                        <div class="min-w-0">
                                          <p class="font-semibold text-slate-900 text-sm truncate"><?php echo htmlspecialchars($staff['name']); ?></p>
                                          <p class="text-[10px] text-slate-400 font-medium uppercase mt-0.5 truncate"><?php echo htmlspecialchars($staff['email']); ?></p>
                                        </div>
                                      </div>
                                      <button type="button" onclick="if(confirm('Delete this staff member?')) { document.getElementById('delete_staff_id').value='<?php echo $staff['id']; ?>'; document.getElementById('delete_staff_form').submit(); }" class="p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors ml-2">
                                          <i data-lucide="trash-2" class="w-4 h-4"></i>
                                      </button>
                                  </div>
                                </td>
                                <?php foreach($daysOfWeek as $day):
                                  $shift = $staff['schedule'][$day] ?? 'OFF';
                                  $isOff = ($shift === 'OFF');
                                  $bgClass = $isOff ? "bg-slate-50 text-red-400 border-slate-100" : "bg-emerald-50 text-emerald-700 border-emerald-100 shadow-sm";
                                ?>
                                  <td class="px-4 py-6">
                                    <div class="mx-auto w-full max-w-[120px] p-2 rounded-2xl transition-all border <?php echo $bgClass; ?> focus-within:ring-2 focus-within:ring-emerald-200">
                                      <input type="text" name="schedule[<?php echo $staff['id']; ?>][<?php echo $day; ?>]" value="<?php echo htmlspecialchars($shift); ?>" class="w-full text-center bg-transparent font-semibold text-xs outline-none uppercase">
                                    </div>
                                  </td>
                                <?php endforeach; ?>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      </div>
                    </form>

                    <form method="POST" id="delete_staff_form" class="hidden">
                        <input type="hidden" name="delete_staff" value="1">
                        <input type="hidden" name="staff_id" id="delete_staff_id">
                    </form>
                  </div>
                </div>
            </div>

            <div id="checklists" class="tab-content h-full flex-1 p-6 md:p-10 <?php echo $activeTab==='checklists'?'active':''; ?>">
                <div class="space-y-8 animate-in fade-in duration-500">
                  <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                    <div>
                      <h2 class="text-3xl font-semibold text-slate-900 tracking-tight">Staff Checklists</h2>
                      <p class="text-slate-500 font-medium mt-1">Manage and track daily operations</p>
                    </div>
                    <button onclick="openModal('addChecklistModal')" class="w-full md:w-auto bg-emerald-500 hover:bg-emerald-600 text-white px-6 py-3 rounded-2xl font-semibold shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2">
                      <i data-lucide="plus" class="w-5 h-5"></i> Create Checklist
                    </button>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach($checklistsDB as $cl):
                        $tot = count($cl['tasks'] ?? []);
                        $dn = 0; if ($tot > 0) { foreach($cl['tasks'] as $tk) { if($tk['done'] ?? false) $dn++; } }
                        $pct = $tot > 0 ? round(($dn / $tot) * 100) : 0;
                    ?>
                    <div class="bg-white rounded-[2rem] p-6 shadow-xl shadow-slate-200/50 border border-slate-100 flex flex-col">
                      <div class="flex justify-between items-start mb-6">
                        <div class="flex items-center gap-3">
                          <div class="w-12 h-12 min-w-[48px] min-h-[48px] rounded-2xl bg-slate-100 flex items-center justify-center font-semibold text-slate-700 flex-none notranslate" translate="no">
                            <?php echo htmlspecialchars($cl['staffName'][0] ?? 'S'); ?>
                          </div>
                          <div class="min-w-0">
                            <h4 class="font-semibold text-slate-900 truncate"><?php echo htmlspecialchars($cl['staffName']); ?></h4>
                            <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest flex items-center gap-1 mt-0.5 truncate">
                              <i data-lucide="calendar" class="w-3 h-3"></i> <?php echo htmlspecialchars($cl['date']); ?>
                            </p>
                          </div>
                        </div>
                        <form method="POST" class="inline shrink-0" onsubmit="return confirm('Are you sure you want to permanently delete this entire checklist?');">
                            <input type="hidden" name="delete_checklist" value="1">
                            <input type="hidden" name="checklist_id" value="<?php echo $cl['id']; ?>">
                            <button type="submit" class="p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all">
                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                            </button>
                        </form>
                      </div>

                      <div class="flex-1 space-y-3">
                        <?php if (isset($cl['tasks']) && is_array($cl['tasks'])): ?>
                            <?php foreach($cl['tasks'] as $t):
                                $isDone = $t['done'] ?? false;
                                $bg = $isDone ? "bg-emerald-50/50 text-emerald-700" : "bg-slate-50 text-slate-600 hover:bg-slate-100";
                                $icon = $isDone ? '<i data-lucide="check-circle-2" class="w-5 h-5 text-emerald-500 shrink-0"></i>' : '<i data-lucide="circle" class="w-5 h-5 text-slate-300 shrink-0"></i>';
                                $line = $isDone ? 'line-through opacity-60' : '';
                            ?>
                            <form method="POST" class="w-full">
                                <input type="hidden" name="admin_toggle_task" value="1">
                                <input type="hidden" name="list_id" value="<?php echo $cl['id']; ?>">
                                <input type="hidden" name="task_id" value="<?php echo $t['id']; ?>">
                                <button type="submit" class="w-full flex items-center gap-3 p-3 rounded-xl transition-all text-left <?php echo $bg; ?>">
                                    <?php echo $icon; ?>
                                    <span class="text-sm font-medium <?php echo $line; ?>"><?php echo htmlspecialchars($t['text']); ?></span>
                                </button>
                            </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                      </div>

                      <div class="mt-6 pt-6 border-t border-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-2 w-full">
                          <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 transition-all duration-500" style="width: <?php echo $pct; ?>%"></div>
                          </div>
                          <span class="text-[10px] font-semibold text-slate-400 uppercase w-8 text-right"><?php echo $pct; ?>%</span>
                        </div>
                      </div>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
            </div>

        </main>

        <div id="guestHistoryModal" class="fixed inset-0 modal-overlay z-[250] hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-[2.5rem] max-w-4xl w-full flex flex-col md:flex-row overflow-hidden shadow-2xl h-[600px] border border-slate-200">
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

        <div id="sbOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[100] hidden transition-opacity" onclick="closeBookingSidebar()"></div>
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
                        <div class="grid grid-cols-4 gap-2 md:gap-3">
                            <button type="button" onclick="openModal('paymentModal')" class="flex flex-col items-center justify-center p-2 md:p-3 bg-white border border-slate-200 rounded-2xl shadow-sm hover:border-blue-300 transition-all group"><i data-lucide="banknote" class="w-4 h-4 text-slate-400 group-hover:text-blue-500 mb-2"></i><span class="text-[9px] uppercase text-slate-600 font-semibold">Payment</span></button>
                            <button type="button" onclick="openModal('extendModal')" class="flex flex-col items-center justify-center p-2 md:p-3 bg-white border border-slate-200 rounded-2xl shadow-sm hover:border-amber-300 transition-all group"><i data-lucide="calendar-plus" class="w-4 h-4 text-slate-400 group-hover:text-amber-500 mb-2"></i><span class="text-[9px] uppercase text-slate-600 font-semibold">Extend</span></button>
                            <button type="button" onclick="openLuggageModal()" class="flex flex-col items-center justify-center p-2 md:p-3 bg-white border border-slate-200 rounded-2xl shadow-sm hover:border-purple-300 transition-all group"><i data-lucide="briefcase" class="w-4 h-4 text-slate-400 group-hover:text-purple-500 mb-2"></i><span class="text-[9px] uppercase text-slate-600 font-semibold">Luggage</span></button>
                            <button type="button" onclick="openDamageModal()" class="flex flex-col items-center justify-center p-2 md:p-3 bg-white border border-slate-200 rounded-2xl shadow-sm hover:border-rose-300 transition-all group"><i data-lucide="alert-triangle" class="w-4 h-4 text-slate-400 group-hover:text-rose-500 mb-2"></i><span class="text-[9px] uppercase text-slate-600 font-semibold">Damage</span></button>
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
            <div class="p-6 border-t bg-white grid grid-cols-2 gap-4 sticky bottom-0 z-20">
                <button type="button" onclick="openEditModalFromSidebar()" class="py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl text-xs uppercase shadow-sm hover:bg-slate-100 font-semibold">Edit Details</button>
                <form method="POST" class="m-0"><input type="hidden" name="edit_guest_info" value="1"><input type="hidden" name="booking_id" id="quick_conf_id"><input type="hidden" name="edit_status" value="Confirmed"><input type="hidden" name="edit_guest_name" id="quick_conf_name"><input type="hidden" name="edit_check_out" id="quick_conf_out"><input type="hidden" name="edit_total_price" id="quick_conf_tot"><input type="hidden" name="edit_amount_paid" id="quick_conf_paid"><input type="hidden" name="tab_redirect" value="reservations"><button type="submit" class="w-full py-3 bg-emerald-500 text-white rounded-xl text-xs uppercase shadow-sm hover:bg-emerald-600 font-semibold">Confirm Stay</button></form>
            </div>
        </div>

        <div id="damageModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4">
            <div class="bg-white rounded-[2.5rem] max-w-sm w-full p-8 shadow-2xl">
                <h2 class="text-xl font-bold mb-4 text-rose-600">Add Damage Charge</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="save_damage" value="1">
                    <input type="hidden" name="booking_id" id="damage_booking_id">
                    <input type="hidden" name="tab_redirect" value="reservations">
                    <div>
                        <label class="text-[10px] font-bold uppercase text-slate-400">Item Broken / Issue</label>
                        <input type="text" name="damage_desc" class="w-full border p-3 rounded-xl mt-1 font-medium" placeholder="e.g. Broken Toilet Seat" required>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase text-slate-400">Charge Fee (ARS)</label>
                        <input type="number" name="damage_fee" class="w-full border p-3 rounded-xl mt-1 font-bold" required>
                    </div>
                    <p class="text-xs text-slate-500">This fee will be automatically added to the guest's total balance.</p>
                    <div class="flex gap-2 mt-4">
                        <button type="button" onclick="closeModal('damageModal')" class="flex-1 py-4 bg-slate-100 rounded-2xl font-bold text-slate-700">Cancel</button>
                        <button type="submit" class="flex-1 py-4 bg-rose-600 text-white rounded-2xl font-bold hover:bg-rose-700 transition-all">Add Charge</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="luggageModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4">
            <div class="bg-white rounded-[2.5rem] max-w-sm w-full p-8 shadow-2xl">
                <h2 class="text-xl font-bold mb-4 text-purple-600">Luggage Storage</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="save_luggage" value="1">
                    <input type="hidden" name="booking_id" id="luggage_booking_id">
                    <input type="hidden" name="tab_redirect" value="reservations">
                    <div>
                        <label class="text-[10px] font-bold uppercase text-slate-400">Number of Days</label>
                        <input type="number" name="luggage_days" id="luggage_days" class="w-full border p-3 rounded-xl mt-1 font-bold" min="1" required>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold uppercase text-slate-400">Storage Fee (ARS)</label>
                        <input type="number" name="luggage_fee" id="luggage_fee" class="w-full border p-3 rounded-xl mt-1 font-bold" required>
                    </div>
                    <p class="text-xs text-slate-500">This fee will be automatically added to the guest's total balance. Use 'Add Payment' when they pay.</p>
                    <div class="flex gap-2 mt-4">
                        <button type="button" onclick="closeModal('luggageModal')" class="flex-1 py-4 bg-slate-100 rounded-2xl font-bold">Cancel</button>
                        <button type="submit" class="flex-1 py-4 bg-purple-600 text-white rounded-2xl font-bold hover:bg-purple-700 transition-all">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="loyaltyModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4">
            <div class="bg-white rounded-[2.5rem] max-w-sm w-full p-8 shadow-2xl">
                <h2 class="text-xl font-bold mb-4">Send Loyalty Discount</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="apply_discount" value="1">
                    <input type="hidden" name="booking_id" id="loyalty_booking_id">
                    <input type="hidden" name="tab_redirect" value="guests">
                    <p class="text-sm font-medium text-slate-600 mb-4">Award discount to <span id="loyalty_guest_name" class="font-bold text-slate-900"></span>.</p>
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">Discount Type</label><select name="discount_type" class="w-full border p-3 rounded-xl mt-1 font-medium"><option value="percent">Percentage (%)</option><option value="fixed">Fixed Amount (ARS)</option></select></div>
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">Value</label><input type="number" name="discount_value" class="w-full border p-3 rounded-xl mt-1 font-bold" required></div>
                    <div class="flex gap-2 mt-4"><button type="button" onclick="closeModal('loyaltyModal')" class="flex-1 py-4 bg-slate-100 rounded-2xl font-bold">Cancel</button><button type="submit" class="flex-1 py-4 bg-amber-500 text-white rounded-2xl font-bold">Apply & Email</button></div>
                </form>
            </div>
        </div>

        <div id="editGuestModal" class="fixed inset-0 modal-overlay z-[200] hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-[2.5rem] max-w-lg w-full p-6 md:p-10 shadow-2xl overflow-y-auto max-h-[90vh]">
                <h2 class="text-2xl font-bold mb-6">Edit Guest Details</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="edit_guest_info" value="1">
                    <input type="hidden" name="booking_id" id="edit_id">
                    <input type="hidden" name="tab_redirect" value="reservations">
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">Guest Name</label><input type="text" name="edit_guest_name" id="edit_name" class="w-full border p-3 rounded-xl mt-1 font-medium"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="text-[10px] font-bold uppercase text-slate-400">Passport/ID</label><input type="text" name="edit_passport" id="edit_passport" class="w-full border p-3 rounded-xl mt-1"></div>
                        <div><label class="text-[10px] font-bold uppercase text-slate-400">Check-out</label><input type="date" name="edit_check_out" id="edit_out" class="w-full border p-3 rounded-xl mt-1"></div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div><label class="text-[10px] font-bold uppercase text-slate-400">Total Base Price (ARS)</label><input type="number" name="edit_total_price" id="edit_total" class="w-full border p-3 rounded-xl mt-1"></div>
                        <div><label class="text-[10px] font-bold uppercase text-slate-400">Paid (ARS)</label><input type="number" name="edit_amount_paid" id="edit_paid" class="w-full border p-3 rounded-xl mt-1"></div>
                    </div>
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">Status</label>
                        <select name="edit_status" id="edit_stat" class="w-full border p-3 rounded-xl mt-1 font-medium">
                            <option value="Confirmed">Confirmed</option><option value="Checked In">Checked In</option><option value="Checked Out">Checked Out</option><option value="Cancelled">Cancelled</option>
                            <option value="Pending">Pending</option>
                        </select>
                    </div>
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">Assign Room</label>
                        <select name="edit_room_id" id="edit_room" class="w-full border p-3 rounded-xl mt-1 font-medium">
                            <option value="unassigned">Unassigned</option><?php foreach($rooms as $rm): ?><option value="<?php echo $rm['id']; ?>"><?php echo $rm['name']; ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="flex gap-3 pt-6"><button type="button" onclick="closeModal('editGuestModal')" class="flex-1 py-4 bg-slate-100 rounded-2xl font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 py-4 bg-slate-900 text-white rounded-2xl font-bold">Save Changes</button></div>
                </form>
            </div>
        </div>

        <div id="paymentModal" class="fixed inset-0 modal-overlay z-[200] hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-[2.5rem] max-w-sm w-full p-8 shadow-2xl">
                <h2 class="text-xl font-bold mb-4">Add Payment</h2>
                <form method="POST" class="space-y-4"><input type="hidden" name="confirm_payment" value="1"><input type="hidden" name="booking_id" id="pay_booking_id"><input type="hidden" name="tab_redirect" value="reservations">
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">Amount (ARS)</label><input type="number" name="payment_amount" id="pay_amount" class="w-full border p-3 rounded-xl mt-1 font-bold"></div>
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">Method</label><select name="payment_method" class="w-full border p-3 rounded-xl mt-1 font-medium"><option>Cash</option><option>Visa</option><option>Mastercard</option><option>Mercado Pago</option></select></div>
                    <div class="flex gap-2 mt-4"><button type="button" onclick="closeModal('paymentModal')" class="flex-1 py-4 bg-slate-100 rounded-2xl font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 py-4 bg-emerald-500 text-white rounded-2xl font-bold">Confirm</button></div>
                </form>
            </div>
        </div>

        <div id="extendModal" class="fixed inset-0 modal-overlay z-[200] hidden flex items-center justify-center p-4">
            <div class="bg-white rounded-[2.5rem] max-w-sm w-full p-8 shadow-2xl">
                <h2 class="text-xl font-bold mb-4">Extend Stay</h2>
                <form method="POST" class="space-y-4"><input type="hidden" name="extend_stay" value="1"><input type="hidden" name="booking_id" id="ext_booking_id"><input type="hidden" name="tab_redirect" value="reservations">
                    <div><label class="text-[10px] font-bold uppercase text-slate-400">New Check-out Date</label><input type="date" name="new_check_out" id="ext_out" class="w-full border p-3 rounded-xl mt-1 font-bold" required></div>
                    <div class="flex gap-2 mt-4"><button type="button" onclick="closeModal('extendModal')" class="flex-1 py-4 bg-slate-100 rounded-2xl font-bold text-slate-700">Cancel</button><button type="submit" class="flex-1 py-4 bg-amber-500 text-white rounded-2xl font-bold">Update</button></div>
                </form>
            </div>
        </div>

        <div id="addExpenseModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4">
            <div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl">
                <div class="flex justify-between items-center mb-6"><h2 class="text-xl font-semibold">Add Expense</h2><button type="button" onclick="closeModal('addExpenseModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button></div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="add_expense" value="1">
                    <div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Date</label><input type="date" name="exp_date" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" value="<?php echo date('Y-m-d'); ?>" required></div>
                    <div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Description</label><input type="text" name="exp_desc" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" placeholder="e.g. Cleaning Supplies" required></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Category</label>
                        <select name="exp_category" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium">
                            <option value="Cleaning">Cleaning</option><option value="Maintenance">Maintenance</option><option value="Utilities">Utilities</option><option value="Salary">Salary</option><option value="Other">Other</option>
                        </select></div>
                        <div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Amount (ARS)</label><input type="number" name="exp_amount" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-bold" required></div>
                    </div>
                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Save Expense</button>
                </form>
            </div>
        </div>

        <div id="addRoomModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4"><div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl"><div class="flex justify-between items-center mb-6"><h2 class="text-xl font-semibold">Add Room</h2><button type="button" onclick="closeModal('addRoomModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button></div><form method="POST" class="space-y-4"><input type="hidden" name="add_room" value="1"><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Room Name</label><input type="text" name="room_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" placeholder="e.g. Standard 4-Bed Dorm" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Image Link (URL)</label><input type="url" name="room_image" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" placeholder="https://example.com/image.jpg"></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Description</label><textarea name="room_description" rows="3" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium text-sm resize-none" placeholder="Describe the room..."></textarea></div><button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Add Room</button></form></div></div>
        <div id="editRoomModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4"><div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl"><div class="flex justify-between items-center mb-6"><h2 class="text-xl font-semibold">Edit Room</h2><button type="button" onclick="closeModal('editRoomModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button></div><form method="POST" class="space-y-4"><input type="hidden" name="edit_room" value="1"><input type="hidden" name="room_id" id="edit_rm_id"><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Room Name</label><input type="text" name="room_name" id="edit_rm_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Image Link (URL)</label><input type="url" name="room_image" id="edit_rm_img" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium"></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Description</label><textarea name="room_description" id="edit_rm_desc" rows="3" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium text-sm resize-none"></textarea></div><button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Save Changes</button></form></div></div>

        <div id="addWeeklyEventModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4"><div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl"><div class="flex justify-between items-center mb-6"><h2 class="text-xl font-semibold">Add Weekly Event</h2><button type="button" onclick="closeModal('addWeeklyEventModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button></div><form method="POST" class="space-y-4"><input type="hidden" name="add_weekly_event" value="1"><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Event Name</label><input type="text" name="we_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" placeholder="e.g. Pasta Night" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Day of Week</label><input type="text" name="we_day" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" placeholder="e.g. Every Tuesday @ 8PM" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Image Link (URL)</label><input type="url" name="we_image" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium"></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Description</label><textarea name="we_description" rows="3" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium text-sm resize-none" placeholder="Describe the event..."></textarea></div><button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Add Event</button></form></div></div>
        <div id="editWeeklyEventModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4"><div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl"><div class="flex justify-between items-center mb-6"><h2 class="text-xl font-semibold">Edit Weekly Event</h2><button type="button" onclick="closeModal('editWeeklyEventModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button></div><form method="POST" class="space-y-4"><input type="hidden" name="edit_weekly_event" value="1"><input type="hidden" name="we_id" id="edit_we_id"><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Event Name</label><input type="text" name="we_name" id="edit_we_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Day of Week</label><input type="text" name="we_day" id="edit_we_day" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Image Link (URL)</label><input type="url" name="we_image" id="edit_we_img" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium"></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Description</label><textarea name="we_description" id="edit_we_desc" rows="3" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium text-sm resize-none"></textarea></div><button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Save Changes</button></form></div></div>

        <div id="addPlazaEventModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4"><div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl"><div class="flex justify-between items-center mb-6"><h2 class="text-xl font-semibold">Add Plaza Event</h2><button type="button" onclick="closeModal('addPlazaEventModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button></div><form method="POST" class="space-y-4"><input type="hidden" name="add_plaza_event" value="1"><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Event Name</label><input type="text" name="pe_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" placeholder="e.g. Full Moon Party" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Date / Time</label><input type="text" name="pe_date" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" placeholder="e.g. April 15, 2026 @ 9PM" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Image Link (URL)</label><input type="url" name="pe_image" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium"></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Description</label><textarea name="pe_description" rows="3" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium text-sm resize-none" placeholder="Describe the event..."></textarea></div><button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Add Event</button></form></div></div>
        <div id="editPlazaEventModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4"><div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl"><div class="flex justify-between items-center mb-6"><h2 class="text-xl font-semibold">Edit Plaza Event</h2><button type="button" onclick="closeModal('editPlazaEventModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button></div><form method="POST" class="space-y-4"><input type="hidden" name="edit_plaza_event" value="1"><input type="hidden" name="pe_id" id="edit_pe_id"><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Event Name</label><input type="text" name="pe_name" id="edit_pe_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Date / Time</label><input type="text" name="pe_date" id="edit_pe_date" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium" required></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Image Link (URL)</label><input type="url" name="pe_image" id="edit_pe_img" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium"></div><div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Description</label><textarea name="pe_description" id="edit_pe_desc" rows="3" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-blue-500 font-medium text-sm resize-none"></textarea></div><button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Save Changes</button></form></div></div>

        <div id="addStaffModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4">
            <div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold">Add Staff Member</h2>
                    <button type="button" onclick="closeModal('addStaffModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="add_staff" value="1">
                    <div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Staff Name</label><input type="text" name="staff_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" required></div>
                    <div><label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Email Address</label><input type="email" name="staff_email" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium" required></div>
                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white p-3.5 rounded-xl text-sm mt-2 shadow-sm font-semibold transition-all">Add Staff</button>
                </form>
            </div>
        </div>

        <div id="addChecklistModal" class="fixed inset-0 modal-overlay hidden z-[200] flex items-center justify-center p-4">
            <div class="bg-white rounded-[2rem] max-w-md w-full p-8 shadow-2xl">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold">Create Checklist</h2>
                    <button type="button" onclick="closeModal('addChecklistModal')" class="p-2 text-slate-400 hover:text-slate-800"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="create_checklist" value="1">
                    <div>
                        <label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Assign to Staff</label>
                        <select name="staff_name" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium">
                            <?php foreach($staffData as $s): ?><option value="<?php echo htmlspecialchars($s['name']); ?>"><?php echo htmlspecialchars($s['name']); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-400 uppercase font-bold tracking-widest block mb-2">Tasks</label>
                        <div id="task_fields_container" class="space-y-2">
                            <input type="text" name="task_texts[]" placeholder="Task 1" class="w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium text-sm" required>
                        </div>
                        <button type="button" onclick="addTaskField()" class="text-xs font-bold text-emerald-500 mt-2 hover:text-emerald-600">+ Add another task</button>
                    </div>
                    <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white p-3.5 rounded-xl text-sm mt-4 shadow-sm font-semibold transition-all">Send Checklist</button>
                </form>
            </div>
        </div>

    <div id="google_translate_element" style="display:none;"></div>
    <script type="text/javascript">function googleTranslateElementInit() { new google.translate.TranslateElement({pageLanguage: 'en', autoDisplay: false}, 'google_translate_element'); }</script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <script>
        lucide.createIcons();
        const bData = <?php echo json_encode($bookings); ?>;
        const exchangeRate = <?php echo $exchangeRateARS; ?>;
        const rmData = <?php echo json_encode($rooms); ?>;
        let currentBookingId = null;

        function safeSetVal(id, val) { const el = document.getElementById(id); if(el) el.value = val; }
        function safeSetText(id, text) { const el = document.getElementById(id); if(el) el.innerText = text; }

        function toggleMenu(mid, cid) {
            const m = document.getElementById(mid); if(m) m.classList.toggle('hidden');
            const c = document.getElementById(cid); if(c) c.classList.toggle('rotate-180');
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

        function toggleActionMenu(id) {
            document.querySelectorAll('.action-menu').forEach(m => { if(m.id !== id) m.classList.add('hidden'); });
            const menu = document.getElementById(id); if (menu) menu.classList.toggle('hidden');
        }

        // Close action menus if clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('td.relative')) {
                document.querySelectorAll('.action-menu').forEach(menu => menu.classList.add('hidden'));
            }
            if(!event.target.closest('.relative.w-full')) {
                const dd = document.getElementById('guestSearchDropdown');
                if(dd) dd.classList.add('hidden');
            }
        });

        function switchTab(id) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('bg-teal-700','text-white','font-medium'));
            const activeBtn = document.querySelector(`.nav-btn[data-target="${id}"]`);
            if(activeBtn) activeBtn.classList.add('bg-teal-700', 'text-white', 'font-medium');

            const url = new URL(window.location); url.searchParams.set('tab', id); window.history.pushState({}, '', url);

            if(window.innerWidth < 768) { toggleMobileMenu(); }
        }

        function switchSidebarTab(tab) {
            document.querySelectorAll('.sidebar-tab-view').forEach(v => v.classList.add('hidden'));
            const viewTab = document.getElementById('view_sb_' + tab); if(viewTab) viewTab.classList.remove('hidden');
            document.querySelectorAll('.sidebar-tab').forEach(b => { b.classList.remove('sidebar-tab-active','text-slate-900'); b.classList.add('text-slate-400'); });
            const btnTab = document.getElementById('btn_sb_'+tab);
            if(btnTab) { btnTab.classList.add('sidebar-tab-active','text-slate-900'); btnTab.classList.remove('text-slate-400'); }
        }

        // New Dropdown Search Feature
        function searchGuests() {
            let input = document.getElementById('guestSearchInput').value.toLowerCase();

            // 1. Filter the Table normally
            let rows = document.querySelectorAll('.guest-row');
            rows.forEach(row => {
                let name = row.getAttribute('data-name').toLowerCase();
                row.style.display = name.includes(input) ? '' : 'none';
            });

            // 2. Populate Dropdown for High-End History Pop-up
            let dropdown = document.getElementById('guestSearchDropdown');
            if(!dropdown) return;
            if (input.length < 2) { dropdown.classList.add('hidden'); return; }

            let uniqueNames = [...new Set(bData.map(b => b.guestName))];
            let matches = uniqueNames.filter(n => n.toLowerCase().includes(input));

            if (matches.length > 0) {
                dropdown.innerHTML = matches.map(m => {
                    return `<div onclick="openGuestHistory('${m.replace(/'/g, "\\'")}')" class="p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-50 flex items-center gap-3"><div class="w-8 h-8 min-w-[32px] min-h-[32px] flex-none rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-bold notranslate" translate="no"><i data-lucide="search" class="w-4 h-4 shrink-0 flex-none"></i></div><div><p class="text-sm font-bold text-slate-800">${m}</p><p class="text-[9px] text-slate-400 uppercase tracking-widest mt-0.5">View Stay History</p></div></div>`;
                }).join('');
                dropdown.classList.remove('hidden');
                lucide.createIcons();
            } else {
                dropdown.classList.add('hidden');
            }
        }

        // New Master Guest Profile Modal
        function openGuestHistory(guestName) {
            document.getElementById('guestSearchDropdown').classList.add('hidden');

            let stays = bData.filter(b => b.guestName.toLowerCase() === guestName.toLowerCase());
            stays.sort((a, b) => new Date(b.checkIn) - new Date(a.checkIn)); // Sort latest first

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
                let rm = rmData.find(r => r.id === s.roomId);
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
                let statusCol = s.status === 'Confirmed' ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : (s.status === 'Checked In' ? 'text-purple-600 bg-purple-50 border-purple-200' : 'text-slate-600 bg-slate-50 border-slate-200');

                let lugHtml = s.luggage && s.luggage.days > 0 ? `<div class="flex justify-between text-sm"><span class="text-slate-600 font-medium text-purple-600"><i data-lucide="briefcase" class="w-3 h-3 inline"></i> Luggage (${s.luggage.days} days)</span><span class="font-bold text-slate-900">AR$ ${Math.round(lFee * exchangeRate).toLocaleString()}</span></div>` : '';

                return `
                <div class="border border-slate-100 rounded-2xl mb-4 overflow-hidden shadow-sm bg-white">
                    <button type="button" onclick="toggleAccordion('stay_acc_${index}')" class="w-full flex items-center justify-between p-4 md:p-5 hover:bg-slate-50 transition-colors focus:outline-none">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center bg-slate-100 text-slate-500 font-bold text-xs shrink-0 flex-none"><i data-lucide="key" class="w-4 h-4"></i></div>
                            <div class="text-left">
                                <p class="font-bold text-slate-900 text-sm">${s.checkIn} to ${s.checkOut}</p>
                                <p class="text-[10px] text-slate-500 uppercase tracking-widest mt-0.5 truncate max-w-[150px] md:max-w-[200px]">${rName}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 shrink-0 flex-none">
                            <span class="px-3 py-1 rounded-lg text-[9px] font-bold uppercase tracking-widest border hidden md:inline-block ${statusCol}">${s.status}</span>
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
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Status</span><span class="font-bold uppercase tracking-widest text-[10px] px-2 py-1 rounded-md border ${statusCol}">${s.status}</span></div>
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Booking Source</span><span class="font-bold text-slate-900">${s.source || 'Direct'}</span></div>
                                    <div class="flex justify-between text-sm"><span class="text-slate-600 font-medium">Payment Method</span><span class="font-bold text-slate-900">${s.paymentMethod || 'None'}</span></div>
                                    ${stays.length > 2 && index === 0 ? `<div class="mt-4 p-2.5 bg-amber-50 border border-amber-200 rounded-lg text-amber-700 text-[10px] font-bold uppercase tracking-widest flex items-center justify-center gap-2 shadow-sm"><i data-lucide="award" class="w-3.5 h-3.5"></i> VIP Discount Sent</div>` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');

            document.getElementById('gh_stays_container').innerHTML = staysHtml;
            openModal('guestHistoryModal');
            lucide.createIcons();
        }

        function toggleAccordion(id) {
            const el = document.getElementById(id);
            if(el.classList.contains('hidden')) { el.classList.remove('hidden'); } else { el.classList.add('hidden'); }
        }

        // Instant Translator Fix
        function setLanguage(lang) {
            document.cookie = `googtrans=/en/${lang}; path=/`;
            document.cookie = `googtrans=/en/${lang}; domain=.${location.hostname}; path=/`;
            const select = document.querySelector('select.goog-te-combo');
            if(select) {
                select.value = lang;
                select.dispatchEvent(new Event('change'));
            } else {
                location.reload();
            }
        }

        function openModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('hidden'); } }
        function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.add('hidden'); } }

        function openLoyaltyModal(id, name) { safeSetVal('loyalty_booking_id', id); safeSetText('loyalty_guest_name', name); openModal('loyaltyModal'); }

        function openLuggageModal() {
            const b = bData.find(x => x.id === currentBookingId);
            safeSetVal('luggage_booking_id', b?.id);
            if (b?.luggage) {
                safeSetVal('luggage_days', b.luggage.days);
                safeSetVal('luggage_fee', Math.round(b.luggage.fee * exchangeRate));
            } else {
                safeSetVal('luggage_days', ''); safeSetVal('luggage_fee', '');
            }
            closeBookingSidebar(); openModal('luggageModal');
        }

        function openDamageModal() {
            const b = bData.find(x => x.id === currentBookingId);
            safeSetVal('damage_booking_id', b?.id);
            closeBookingSidebar(); openModal('damageModal');
        }

        function addTaskField() {
            const container = document.getElementById('task_fields_container');
            const input = document.createElement('input');
            input.type = 'text'; input.name = 'task_texts[]';
            input.placeholder = 'Next Task';
            input.className = 'w-full border-2 border-slate-100 p-3 rounded-xl outline-none focus:border-emerald-500 font-medium text-sm mt-2';
            container.appendChild(input);
        }

        function openEditRoomModal(id, name, img, desc) { safeSetVal('edit_rm_id', id); safeSetVal('edit_rm_name', name); safeSetVal('edit_rm_img', img); safeSetVal('edit_rm_desc', desc); openModal('editRoomModal'); }
        function openEditWeeklyEventModal(id, name, day, img, desc) { safeSetVal('edit_we_id', id); safeSetVal('edit_we_name', name); safeSetVal('edit_we_day', day); safeSetVal('edit_we_img', img); safeSetVal('edit_we_desc', desc); openModal('editWeeklyEventModal'); }
        function openEditPlazaEventModal(id, name, date, img, desc) { safeSetVal('edit_pe_id', id); safeSetVal('edit_pe_name', name); safeSetVal('edit_pe_date', date); safeSetVal('edit_pe_img', img); safeSetVal('edit_pe_desc', desc); openModal('editPlazaEventModal'); }

        function openBookingSidebar(id) {
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

            const s = (b.status || '').toLowerCase(); const statusDot = document.getElementById('sb_status_dot');
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
                    lugContainer.innerHTML = `<div class="p-4 bg-purple-50 border border-purple-200 rounded-2xl flex items-center justify-between shadow-sm">
                        <div class="flex items-center gap-2"><i data-lucide="briefcase" class="w-4 h-4 text-purple-600"></i><span class="text-sm font-medium text-purple-700">Luggage Storage</span></div>
                        <span class="text-sm font-bold text-purple-700">${b.luggage.days} Days (AR$ ${Math.round(b.luggage.fee * exchangeRate).toLocaleString()})</span>
                    </div>`;
                } else { lugContainer.innerHTML = ''; }
            }

            let damContainer = document.getElementById('sb_damages_container');
            if (damContainer) {
                if (dHtml !== '') {
                    damContainer.innerHTML = `<div class="space-y-3 mt-3">${dHtml}</div>`;
                } else { damContainer.innerHTML = ''; }
            }

            safeSetVal('pay_booking_id', id); safeSetVal('pay_amount', Math.round(bal));
            safeSetVal('ext_booking_id', id); safeSetVal('ext_out', b.checkOut);
            safeSetVal('quick_conf_id', id); safeSetVal('quick_conf_name', b.guestName); safeSetVal('quick_conf_out', b.checkOut);
            safeSetVal('quick_conf_tot', parseFloat(b.totalPrice) * exchangeRate); safeSetVal('quick_conf_paid', aPaid * exchangeRate);

            switchSidebarTab('operations');
            document.getElementById('sbOverlay').classList.remove('hidden');
            document.getElementById('bookingSidebar').classList.remove('translate-x-full');
            lucide.createIcons();
        }

        function closeBookingSidebar() { document.getElementById('bookingSidebar').classList.add('translate-x-full'); document.getElementById('sbOverlay').classList.add('hidden'); }

        function openEditModalFromSidebar() {
            const b = bData.find(x => x.id === currentBookingId);
            safeSetVal('edit_id', b?.id); safeSetVal('edit_name', b?.guestName); safeSetVal('edit_out', b?.checkOut);
            safeSetVal('edit_passport', b?.passport || ''); safeSetVal('edit_room', b?.roomId || 'unassigned');
            safeSetVal('edit_stat', b?.status || 'Confirmed');
            safeSetVal('edit_total', Math.round((b?.totalPrice || 0) * exchangeRate)); safeSetVal('edit_paid', Math.round((b?.amountPaid || 0) * exchangeRate));
            closeBookingSidebar(); openModal('editGuestModal');
        }

        function allowDrop(ev) { ev.preventDefault(); }
        function drag(ev, bookingId) {
            ev.dataTransfer.setData("bookingId", bookingId);
            ev.dataTransfer.setData("startX", ev.clientX);
        }
        function drop(ev, roomId) {
            ev.preventDefault();
            const bookingId = ev.dataTransfer.getData("bookingId"); if (!bookingId) return;
            const startX = parseFloat(ev.dataTransfer.getData("startX"));
            let rowContainer = ev.target.closest('.relative.flex-1'); if (!rowContainer) return;
            let rect = rowContainer.getBoundingClientRect();
            let totalDays = <?php echo count($daysInView); ?>;
            let colWidth = rect.width / totalDays;
            let dayDelta = Math.round((ev.clientX - startX) / colWidth);

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

        async function moveCalendar(delta) {
            const url = new URL(window.location); const curStart = url.searchParams.get('start_date') || '<?php echo $viewStart; ?>';
            const curDate = new Date(curStart); curDate.setDate(curDate.getDate() + delta);
            url.searchParams.set('start_date', curDate.toISOString().split('T')[0]); url.searchParams.set('tab', 'calendar');

            try {
                const response = await fetch(url.href);
                const html = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                document.querySelector('.cal-scroll').innerHTML = doc.querySelector('.cal-scroll').innerHTML;
                document.getElementById('calendar_date_range').innerText = doc.getElementById('calendar_date_range').innerText;
                window.history.pushState({}, '', url.href);
                lucide.createIcons();
            } catch(e) { window.location.href = url.href;  }
        }

        window.addEventListener('load', () => {
            const ctx = document.getElementById('weeklyActivityChart');
            if(ctx) { new Chart(ctx, { type: 'bar', data: { labels: <?php echo json_encode($chartDates); ?>, datasets: [ { label: 'Check-ins', data: <?php echo json_encode($weeklyCI); ?>, backgroundColor: '#10b981', borderRadius: 4 }, { label: 'Check-outs', data: <?php echo json_encode($weeklyCO); ?>, backgroundColor: '#a855f7', borderRadius: 4 } ]}, options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1, padding: 5, font: { weight: '500' } } }, x: { grid: { display: false }, ticks: { padding: 15, font: { size: 12, weight: '500' } } } }, layout: { padding: { bottom: 20 } } } }); }
        });
    </script>
</body>
</html>