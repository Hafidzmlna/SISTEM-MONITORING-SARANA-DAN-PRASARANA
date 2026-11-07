<?php

/** Semua kode permission milik role user saat ini (di-cache per request). */
function user_permissions(): array
{
    static $perms = null;
    if ($perms === null) {
        $user  = current_user();
        $perms = [];
        if ($user) {
            $rows = fetch_all(
                'SELECT p.kode FROM role_permissions rp
                 JOIN permissions p ON p.id = rp.permission_id
                 WHERE rp.role_id = ?',
                [$user['role_id']]
            );
            $perms = array_column($rows, 'kode');
        }
    }
    return $perms;
}

function has_permission(string $kode): bool
{
    return in_array($kode, user_permissions(), true);
}

/** Dipakai di halaman DAN di handler POST. Contoh: require_permission('kelola_barang'); */
function require_permission(string $kode): void
{
    require_login();
    if (!has_permission($kode)) abort(403);
}
