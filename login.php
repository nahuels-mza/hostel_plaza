<?php
session_start();

// If already logged in, send them directly to their dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin.php");
    exit;
} elseif (isset($_SESSION['staff_logged_in']) && $_SESSION['staff_logged_in'] === true) {
    header("Location: staff.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // ==========================================
    // UPDATED USERNAMES AND PASSWORDS
    // ==========================================
    $adminUser = 'admin';
    $adminPass = 'admin123';

    $staffUser = 'staff';
    $staffPass = 'staff123';
    // ==========================================

    if (strtolower($username) === $adminUser && $password === $adminPass) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } elseif (strtolower($username) === $staffUser && $password === $staffPass) {
        $_SESSION['staff_logged_in'] = true;
        header("Location: staff.php");
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Hostel Plaza</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } },
                    fontFamily: { serif: ['"Playfair Display"', 'serif'], sans: ['"Inter"', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        .hero-gradient {
            background: linear-gradient(to bottom, rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.4));
        }
    </style>
</head>
<body class="font-sans text-slate-900 min-h-screen flex items-center justify-center p-6 relative">

    <div class="absolute inset-0 z-0 overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center bg-no-repeat bg-fixed" style="background-image: url('https://hostelplaza.com.ar/static/media/home2.44870ef3b7a6358c6e4f.jpg'); filter: blur(2px);"></div>
        <div class="absolute inset-0 hero-gradient"></div>
    </div>

    <div class="max-w-md w-full bg-white/95 backdrop-blur-md rounded-3xl shadow-2xl border border-white/20 p-8 md:p-10 relative z-10">
        
        <div class="text-center mb-8">
            <div class="inline-block mb-4">
                <img src="hostel.png" alt="Hostel Plaza Logo" class="h-20 w-auto object-contain mx-auto" onerror="this.style.display='none';">
            </div>
            <h1 class="text-3xl font-serif font-bold text-slate-900">Welcome Back</h1>
            <p class="text-slate-500 mt-2 text-sm">Please sign in to access the dashboard.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 border border-red-200 rounded-xl p-4 mb-6 text-sm font-bold flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-6">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i data-lucide="user" class="w-5 h-5 text-slate-400"></i>
                    </div>
                    <input type="text" name="username" required class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-12 pr-4 py-3 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all" placeholder="Enter your username">
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-2 block">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                        <i data-lucide="lock" class="w-5 h-5 text-slate-400"></i>
                    </div>
                    <input type="password" name="password" required class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-12 pr-4 py-3 text-slate-900 outline-none focus:bg-white focus:ring-2 focus:ring-teal transition-all" placeholder="••••••••">
                </div>
            </div>

            <button type="submit" class="w-full bg-teal text-white py-4 rounded-xl font-bold hover:bg-teal-hover transition-all shadow-md text-lg flex justify-center items-center gap-2">
                Sign In <i data-lucide="arrow-right" class="w-5 h-5"></i>
            </button>
        </form>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>