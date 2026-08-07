<?php
error_reporting(0);
session_start();

// --- 1. DATABASES ---
$bookingsFile = 'bookings.json';
$roomsFile = 'rooms.json';
$messagesFile = 'messages.json';
$configFile = 'config.json';

$bookings = json_decode(@file_get_contents($bookingsFile), true) ?: [];
$rooms = json_decode(@file_get_contents($roomsFile), true) ?: [];
$config = json_decode(@file_get_contents($configFile), true) ?: ["exchangeRateARS" => 1050];
$messagesDB = json_decode(@file_get_contents($messagesFile), true) ?: [];
$exchangeRateARS = (float)$config['exchangeRateARS'];

// --- 2. LOGOUT LOGIC ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: guest.php");
    exit;
}

// --- 3. POST HANDLERS ---
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // LOGIN LOGIC
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $resId = trim($_POST['reservation_id']);
        $email = trim(strtolower($_POST['email']));
        
        $guestFound = false;
        foreach ($bookings as $b) {
            if ($b['id'] === $resId && strtolower(trim($b['email'])) === $email) {
                $_SESSION['guest_logged_in'] = true;
                $_SESSION['guest_res_id'] = $b['id'];
                $_SESSION['guest_name'] = $b['guestName'];
                $_SESSION['guest_email'] = $email;
                $guestFound = true;
                break;
            }
        }
        if ($guestFound) {
            header("Location: guest.php"); exit;
        } else {
            $error = "Invalid Reservation ID or Email Address.";
        }
    }

    // SEND MESSAGE LOGIC
    if (isset($_POST['action']) && $_POST['action'] === 'send_message' && isset($_SESSION['guest_logged_in'])) {
        $text = trim($_POST['message_text']);
        if (!empty($text)) {
            $name = $_SESSION['guest_name'];
            $email = $_SESSION['guest_email'];
            $found = false;
            
            // Find existing conversation
            foreach ($messagesDB as &$conv) {
                if (isset($conv['guestEmail']) && strtolower($conv['guestEmail']) === strtolower($email)) {
                    $conv['messages'][] = [
                        'id' => uniqid(), 'sender' => 'guest', 'text' => htmlspecialchars($text), 'timestamp' => date('h:i A')
                    ];
                    $conv['lastMessage'] = htmlspecialchars($text);
                    $conv['timestamp'] = date('h:i A');
                    $conv['unreadCount'] = (isset($conv['unreadCount']) ? $conv['unreadCount'] + 1 : 1); // Alert admin
                    $found = true; break;
                }
            }
            
            // Or create a new one
            if (!$found) {
                $messagesDB[] = [
                    'id' => uniqid(), 'guestName' => $name, 'guestEmail' => $email, 'timestamp' => date('h:i A'), 'lastMessage' => htmlspecialchars($text), 'unreadCount' => 1,
                    'messages' => [['id' => uniqid(), 'sender' => 'guest', 'text' => htmlspecialchars($text), 'timestamp' => date('h:i A')]]
                ];
            }
            @file_put_contents($messagesFile, json_encode($messagesDB, JSON_PRETTY_PRINT));
        }
        header("Location: guest.php"); exit;
    }

    // PHOTO UPLOAD LOGIC (Base64 embedded for zero-server-config)
    if (isset($_POST['action']) && $_POST['action'] === 'upload_photo' && isset($_SESSION['guest_logged_in'])) {
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['photo']['tmp_name'];
            $type = mime_content_type($tmpName);
            if (in_array($type, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
                $base64 = 'data:' . $type . ';base64,' . base64_encode(file_get_contents($tmpName));
                
                // Update booking with photo
                foreach ($bookings as &$b) {
                    if ($b['id'] === $_SESSION['guest_res_id']) {
                        $b['guestPhoto'] = $base64;
                        break;
                    }
                }
                @file_put_contents($bookingsFile, json_encode($bookings, JSON_PRETTY_PRINT));
            }
        }
        header("Location: guest.php"); exit;
    }
}

// --- 4. VIEW RENDERING ---
$isLoggedIn = isset($_SESSION['guest_logged_in']) && $_SESSION['guest_logged_in'] === true;

if ($isLoggedIn) {
    // Load specific guest data
    $activeBooking = null;
    foreach ($bookings as $b) {
        if ($b['id'] === $_SESSION['guest_res_id']) {
            $activeBooking = $b; break;
        }
    }
    
    // Safety check if booking was deleted
    if (!$activeBooking) { session_destroy(); header("Location: guest.php"); exit; }

    $roomName = 'Unassigned';
    foreach ($rooms as $r) { if ($r['id'] == $activeBooking['roomId']) { $roomName = $r['name']; break; } }
    
    $bal = ($activeBooking['totalPrice'] - $activeBooking['amountPaid']) * $exchangeRateARS;
    $initials = strtoupper($activeBooking['guestName'][0]);
    $photoUrl = $activeBooking['guestPhoto'] ?? null;

    // Load Chat
    $activeChat = [];
    foreach ($messagesDB as $conv) {
        if (isset($conv['guestEmail']) && strtolower($conv['guestEmail']) === strtolower($_SESSION['guest_email'])) {
            $activeChat = $conv['messages'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Portal | Hostel Plaza</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457' } }, fontFamily: { serif: ['"Playfair Display"', 'serif'], sans: ['"Inter"', 'sans-serif'] } } } }</script>
</head>
<body class="bg-[#f8fafc] font-sans text-slate-800 min-h-screen flex flex-col">

    <?php $hasHero = false; include __DIR__ . '/header.php'; ?>

    <?php if (!$isLoggedIn): ?>
    <div class="flex-1 flex items-center justify-center p-6 pt-24">
        <div class="max-w-md w-full bg-white rounded-[3rem] shadow-2xl border border-slate-100 p-10 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-2 bg-emerald-500"></div>
            
            <div class="text-center mb-10 mt-4">
                <h1 class="text-3xl font-serif font-bold text-slate-900">Hostel Plaza</h1>
                <p class="text-xs font-bold text-emerald-600 uppercase tracking-widest mt-2">Guest Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="bg-rose-50 text-rose-600 text-sm font-bold p-4 rounded-2xl mb-6 text-center border border-rose-100">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="login">
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2 px-2">Reservation ID</label>
                    <div class="relative">
                        <i data-lucide="hash" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="text" name="reservation_id" required class="w-full bg-slate-50 border-2 border-transparent focus:border-emerald-500 focus:bg-white transition-all rounded-2xl py-4 pl-12 pr-4 font-bold text-slate-900 outline-none placeholder:text-slate-300" placeholder="e.g. 123456">
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2 px-2">Email Address</label>
                    <div class="relative">
                        <i data-lucide="mail" class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400"></i>
                        <input type="email" name="email" required class="w-full bg-slate-50 border-2 border-transparent focus:border-emerald-500 focus:bg-white transition-all rounded-2xl py-4 pl-12 pr-4 font-bold text-slate-900 outline-none placeholder:text-slate-300" placeholder="your@email.com">
                    </div>
                </div>
                <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-600 text-white font-black text-lg py-4 rounded-2xl shadow-lg shadow-emerald-500/20 transition-all active:scale-95 mt-4">
                    Access My Stay
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <nav class="bg-white border-b border-slate-100 py-4 px-6 md:px-10 flex justify-between items-center mt-20">
        <div>
            <h1 class="text-2xl font-serif font-bold text-slate-900 leading-none">Hostel Plaza</h1>
            <p class="text-[10px] font-black text-emerald-600 uppercase tracking-widest mt-1">Guest Portal</p>
        </div>
        <a href="?logout=1" class="flex items-center gap-2 text-sm font-bold text-slate-400 hover:text-rose-500 transition-colors bg-slate-50 hover:bg-rose-50 px-4 py-2 rounded-xl">
            Logout <i data-lucide="log-out" class="w-4 h-4"></i>
        </a>
    </nav>

    <div class="flex-1 max-w-7xl w-full mx-auto p-6 md:p-10 grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <div class="space-y-8">
            
            <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-8 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-24 bg-gradient-to-b from-emerald-50 to-white"></div>
                
                <div class="relative z-10 flex flex-col items-center">
                    <div class="relative group cursor-pointer mb-6">
                        <?php if ($photoUrl): ?>
                            <img src="<?php echo $photoUrl; ?>" class="w-28 h-28 rounded-full object-cover border-4 border-white shadow-lg">
                        <?php else: ?>
                            <div class="w-28 h-28 rounded-full bg-slate-900 text-white flex items-center justify-center text-4xl font-black border-4 border-white shadow-lg">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="absolute inset-0 bg-slate-900/60 rounded-full flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity" onclick="document.getElementById('photoInput').click()">
                            <i data-lucide="camera" class="w-8 h-8 text-white mb-1"></i>
                            <span class="text-[9px] font-black text-white uppercase tracking-widest">Upload</span>
                        </div>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" id="photoForm" class="hidden">
                        <input type="hidden" name="action" value="upload_photo">
                        <input type="file" id="photoInput" name="photo" accept="image/*" onchange="document.getElementById('photoForm').submit()">
                    </form>

                    <h2 class="text-2xl font-black text-slate-900"><?php echo htmlspecialchars($activeBooking['guestName']); ?></h2>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">Reservation #<?php echo $activeBooking['id']; ?></p>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-8">
                <div class="flex justify-between items-center mb-8">
                    <h3 class="font-black text-slate-900 uppercase tracking-widest text-xs">Stay Summary</h3>
                    <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase tracking-widest rounded-full">
                        <?php echo empty($activeBooking['status']) ? 'Pending' : $activeBooking['status']; ?>
                    </span>
                </div>

                <div class="space-y-6">
                    <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <div class="w-12 h-12 bg-white rounded-xl shadow-sm flex items-center justify-center text-emerald-600 shrink-0"><i data-lucide="bed-double" class="w-6 h-6"></i></div>
                        <div>
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Room Type</p>
                            <p class="font-black text-slate-900 leading-tight mt-0.5"><?php echo $roomName; ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Check In</p>
                            <p class="font-black text-slate-900 mt-1"><?php echo $activeBooking['checkIn']; ?></p>
                        </div>
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                            <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Check Out</p>
                            <p class="font-black text-slate-900 mt-1"><?php echo $activeBooking['checkOut']; ?></p>
                        </div>
                    </div>

                    <div class="p-5 bg-slate-900 rounded-2xl text-white">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-xs font-bold text-slate-400">Total Price</span>
                            <span class="font-black">AR$ <?php echo number_format($activeBooking['totalPrice'] * $exchangeRateARS); ?></span>
                        </div>
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-xs font-bold text-slate-400">Amount Paid</span>
                            <span class="font-black text-emerald-400">AR$ <?php echo number_format($activeBooking['amountPaid'] * $exchangeRateARS); ?></span>
                        </div>
                        <div class="pt-4 border-t border-slate-700 flex justify-between items-center">
                            <span class="text-sm font-black uppercase tracking-widest text-slate-300">Balance Due</span>
                            <span class="text-xl font-black <?php echo $bal > 0 ? 'text-rose-400' : 'text-emerald-400'; ?>">
                                AR$ <?php echo number_format(max(0, $bal)); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 flex flex-col bg-white rounded-[3rem] shadow-xl border border-slate-100 overflow-hidden h-[800px]">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-teal-100 text-teal-700 rounded-xl flex items-center justify-center font-black">
                        <i data-lucide="message-square" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-slate-900">Front Desk</h3>
                        <p class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest">Online • Replies instantly</p>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-8 space-y-6 bg-slate-50/30" id="chatContainer">
                
                <?php if (empty($activeChat)): ?>
                    <div class="flex flex-col items-center justify-center h-full text-slate-400">
                        <i data-lucide="waves" class="w-12 h-12 mb-4 opacity-50"></i>
                        <p class="font-bold text-sm">No messages yet.</p>
                        <p class="text-xs">Say hello or ask a question about your stay!</p>
                    </div>
                <?php else: ?>
                    <?php foreach($activeChat as $m): 
                        // Guest is 'sender' = 'guest', so it aligns right. Staff/Auto aligns left.
                        $isGuest = ($m['sender'] === 'guest');
                    ?>
                        <div class="flex flex-col <?php echo $isGuest ? 'items-end' : 'items-start'; ?>">
                            <div class="max-w-[85%] p-4 rounded-2xl text-sm shadow-sm <?php echo $isGuest ? 'bg-emerald-500 text-white rounded-tr-none' : ($m['sender'] === 'automated' ? 'bg-white border border-slate-200 text-slate-600 rounded-tl-none italic' : 'bg-slate-900 text-white rounded-tl-none'); ?>">
                                <?php if ($m['sender'] === 'automated'): ?>
                                    <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 flex items-center gap-1"><i data-lucide="zap" class="w-3 h-3"></i> System</p>
                                <?php endif; ?>
                                <?php echo $m['text']; ?>
                            </div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-2 px-1"><?php echo $m['timestamp']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

            <div class="p-6 border-t border-slate-100 bg-white">
                <form method="POST" class="flex gap-4">
                    <input type="hidden" name="action" value="send_message">
                    <div class="flex-1 relative">
                        <textarea name="message_text" placeholder="Type your message to the hostel..." required class="w-full p-4 pl-5 pr-12 bg-slate-50 rounded-2xl text-sm border-none focus:ring-2 focus:ring-emerald-500/20 shadow-inner h-[60px] resize-none outline-none font-medium"></textarea>
                    </div>
                    <button type="submit" class="bg-slate-900 hover:bg-emerald-500 text-white px-8 rounded-2xl font-black shadow-lg transition-all active:scale-95 flex flex-col items-center justify-center gap-1">
                        <i data-lucide="send" class="w-5 h-5"></i>
                        <span class="text-[9px] uppercase tracking-widest">Send</span>
                    </button>
                </form>
            </div>
        </div>

    </div>
    
    <script>
        window.addEventListener('load', () => {
            const chat = document.getElementById('chatContainer');
            if (chat) chat.scrollTop = chat.scrollHeight;
        });
    </script>
    <?php endif; ?>

    <script>lucide.createIcons();</script>
</body>
</html>