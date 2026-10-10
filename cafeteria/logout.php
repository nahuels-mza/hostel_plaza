<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['cafeteria_logged_in']);
header('Location: /cafeteriagatonegro/login');
exit;
