<?php

/** Catat aktivitas ke tabel log_aktivitas. Contoh: catat_log('Menambah barang X', 'barang'); */
function catat_log(string $aktivitas, string $modul, ?int $userId = null): void
{
    try {
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);
        query(
            'INSERT INTO log_aktivitas (user_id, aktivitas, modul, ip_address) VALUES (?, ?, ?, ?)',
            [$userId, mb_substr($aktivitas, 0, 255), $modul, $_SERVER['REMOTE_ADDR'] ?? null]
        );
    } catch (Throwable $e) {
        // Kegagalan log tidak boleh mematikan aplikasi.
        if (APP_DEBUG) error_log('catat_log gagal: ' . $e->getMessage());
    }
}
