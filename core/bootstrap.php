<?php
// Di-include paling awal oleh setiap halaman.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

if (session_status() === PHP_SESSION_NONE) {
    session_name('SIMSARSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permission.php';
require_once __DIR__ . '/menu.php';
