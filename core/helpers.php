<?php

/** Escape output (cegah XSS). Pakai di SEMUA data yang ditampilkan. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** URL file statis + versi (waktu ubah file), supaya CSS/JS baru tidak tertahan cache browser. */
function asset(string $path): string
{
    $file = __DIR__ . '/..' . $path;
    return url($path) . '?v=' . (is_file($file) ? filemtime($file) : '1');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [403 => 'Akses ditolak', 404 => 'Halaman tidak ditemukan', 419 => 'Sesi kedaluwarsa'];
    $title  = $titles[$code] ?? 'Terjadi kesalahan';
    $message = $message ?: 'Anda tidak memiliki izin untuk membuka halaman ini.';
    echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title>'
       . '<link rel="stylesheet" href="' . e(asset('/assets/css/app.css')) . '">'
       . '<div class="error-page"><h1>' . $code . '</h1><h2>' . e($title) . '</h2>'
       . '<p>' . e($message) . '</p><a class="btn" href="' . e(url('/')) . '">Kembali</a></div>';
    exit;
}

function hari_tanggal(?DateTimeInterface $dt = null): string
{
    $dt   = $dt ?? new DateTime();
    $hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    $bln  = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return $hari[(int) $dt->format('w')] . ', ' . $dt->format('j') . ' ' . $bln[(int) $dt->format('n')] . ' ' . $dt->format('Y');
}

/** Format tanggal pilihan user (Pengaturan -> Tampilan). Default d/m/Y. */
function pref_format_tanggal(): string
{
    static $f = null;
    if ($f === null) {
        $f = 'd/m/Y';
        if (!empty($_SESSION['user_id'])) {
            $v = fetch_value('SELECT format_tanggal FROM user_settings WHERE user_id = ?', [$_SESSION['user_id']]);
            if (in_array($v, ['d/m/Y', 'd-m-Y', 'Y-m-d'], true)) $f = $v;
        }
    }
    return $f;
}

/** Format tanggal; bagian "d/m/Y" otomatis diganti sesuai preferensi user. */
function tgl(?string $date, ?string $format = null): string
{
    if (!$date) return '-';
    $format = str_replace('d/m/Y', pref_format_tanggal(), $format ?? 'd/m/Y');
    return date($format, strtotime($date));
}

function inisial(string $nama): string
{
    $parts = preg_split('/\s+/', trim($nama));
    $out = mb_substr($parts[0] ?? '', 0, 1);
    if (count($parts) > 1) $out .= mb_substr(end($parts), 0, 1);
    return mb_strtoupper($out);
}

/** Badge status berwarna (dipakai di semua tabel). */
function badge(string $status, ?string $label = null): string
{
    $map = [
        'diajukan' => 'warn', 'baru' => 'warn', 'dijadwalkan' => 'warn', 'rusak_ringan' => 'warn',
        'diproses' => 'info', 'disetujui' => 'info', 'dipinjam' => 'info', 'digunakan' => 'info',
        'perbaikan' => 'info', 'dipakai' => 'info',
        'selesai' => 'ok', 'dikembalikan' => 'ok', 'baik' => 'ok', 'tersedia' => 'ok', 'aktif' => 'ok',
        'ditolak' => 'bad', 'rusak' => 'bad', 'terlambat' => 'bad', 'nonaktif' => 'bad',
    ];
    $cls = $map[$status] ?? 'info';
    return '<span class="badge ' . $cls . '">' . e($label ?? ucfirst(str_replace('_', ' ', $status))) . '</span>';
}
