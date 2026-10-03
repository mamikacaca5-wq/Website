<?php

// filepath: c:\xampp\htdocs\quizmaster\logout.php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Hapus data sesi.
$_SESSION = [];

// Hapus cookie sesi jika digunakan.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]
    );
}

session_destroy();

header('Location: login.php?logged_out=1');
exit;