<?php
// Konfigurasi aplikasi SIMSAR
define('APP_NAME', 'SIMSAR');
define('APP_DEBUG', true);          // set false saat production

// Kosongkan jika diakses lewat http://simsar.test (virtual host otomatis Laragon).
define('BASE_URL', '');

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'simsar');
define('DB_USER', 'root');
define('DB_PASS', '');              // Laragon default: kosong
define('DB_CHARSET', 'utf8mb4');

// Login: batas percobaan gagal sebelum dikunci sementara
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCK_SECONDS', 300);

date_default_timezone_set('Asia/Jakarta');
