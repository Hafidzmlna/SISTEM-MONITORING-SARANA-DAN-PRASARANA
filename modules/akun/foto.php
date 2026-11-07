<?php
// Menyajikan foto profil milik user yang sedang login.
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/akun.php';
$me = require_login();

$path = path_foto_profil(fetch_value('SELECT foto FROM users WHERE id = ?', [$me['id']]));
if (!$path) abort(404, 'Foto tidak ditemukan.');

header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');   // nama file unik per unggahan; URL memakai ?v=
readfile($path);
exit;
