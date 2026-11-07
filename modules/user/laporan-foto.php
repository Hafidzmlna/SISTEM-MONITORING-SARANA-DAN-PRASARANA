<?php
// Menyajikan foto laporan kerusakan setelah cek hak akses (folder uploads/laporan diblokir langsung).
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require_login();

$r = fetch_one('SELECT pelapor_id, foto FROM laporan_kerusakan WHERE id = ?', [(int) ($_GET['id'] ?? 0)]);
if (!$r || !$r['foto']) abort(404, 'Foto tidak ditemukan.');
if (!boleh_lihat_laporan('kerusakan', (int) $r['pelapor_id'])) abort(403);

$path = path_foto_laporan($r['foto']);
if (!$path) abort(404, 'Foto tidak ditemukan.');

header('Content-Type: ' . (str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;
