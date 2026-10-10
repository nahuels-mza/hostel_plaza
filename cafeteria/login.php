<?php
require __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['cafeteria_logged_in'])) {
    header('Location: /cafeteriagatonegro');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $secrets = hp_cafeteria_secrets();

    $accounts = [
        'admin' => [
            'user' => (string)($secrets['cafeteria_admin_user'] ?? ''),
            'pass' => (string)($secrets['cafeteria_admin_pass'] ?? ''),
        ],
        'general' => [
            'user' => (string)($secrets['cafeteria_user'] ?? ''),
            'pass' => (string)($secrets['cafeteria_pass'] ?? ''),
        ],
    ];

    $matchedRole = null;
    foreach ($accounts as $role => $creds) {
        if ($creds['user'] !== '' && $creds['pass'] !== ''
            && hash_equals($creds['user'], $username)
            && hash_equals($creds['pass'], $password)
        ) {
            $matchedRole = $role;
            break;
        }
    }

    if ($matchedRole !== null) {
        $_SESSION['cafeteria_logged_in'] = true;
        $_SESSION['cafeteria_role'] = $matchedRole;
        $_SESSION['cafeteria_username'] = $username;
        header('Location: /cafeteriagatonegro');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ingresar | Gato Negro</title>
    <link rel="icon" href="/iconwhite.ico" sizes="any">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { teal: { DEFAULT: '#1c5457', hover: '#144042' } } } } }
    </script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-6 font-sans">
    <div class="max-w-sm w-full bg-white rounded-2xl shadow-xl p-8">
        <h1 class="text-2xl font-bold text-center mb-1">Gato Negro</h1>
        <p class="text-slate-500 text-center text-sm mb-6">Cafetería · Comanda</p>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-600 border border-red-200 rounded-lg p-3 mb-4 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Usuario</label>
                <input type="text" name="username" required autofocus class="w-full border border-slate-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-teal">
            </div>
            <div>
                <label class="text-xs font-bold text-slate-500 uppercase block mb-1">Contraseña</label>
                <input type="password" name="password" required class="w-full border border-slate-200 rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-teal">
            </div>
            <button type="submit" class="w-full bg-teal text-white py-3 rounded-lg font-bold hover:bg-teal-hover transition">
                Ingresar
            </button>
        </form>
    </div>
</body>
</html>
