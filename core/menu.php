<?php

/** Menu sidebar per role: [key, label, path]. */
function menu_for_role(string $role): array
{
    switch ($role) {
        case 'mahasiswa':
        case 'dosen':
            return [
                ['dashboard',         'Dashboard',         '/modules/user/dashboard.php'],
                ['buat-laporan',      'Buat Laporan',      '/modules/user/buat-laporan.php'],
                ['status-laporan',    'Status Laporan',    '/modules/user/status-laporan.php'],
                ['peminjaman',        'Peminjaman',        '/modules/user/peminjaman.php'],
                ['profil',            'Profil',            '/modules/akun/profil.php'],
                ['pengaturan',        'Pengaturan',        '/modules/akun/pengaturan.php'],
            ];
        case 'sarpras':
            return [
                ['dashboard',   'Dashboard',   '/modules/sarpras/dashboard.php'],
                ['master-data', 'Master Data', '/modules/sarpras/barang.php'],
                ['laporan',     'Laporan',     '/modules/sarpras/laporan-kerusakan.php'],
                ['profil',      'Profil',      '/modules/akun/profil.php'],
                ['pengaturan',  'Pengaturan',  '/modules/akun/pengaturan.php'],
            ];
        case 'rektorat':
            return [
                ['dashboard',  'Dashboard',  '/modules/rektorat/dashboard.php'],
                ['monitoring', 'Monitoring', '/modules/rektorat/monitoring.php'],
                ['laporan',    'Laporan',    '/modules/rektorat/laporan.php'],
                ['rekap',      'Rekap',      '/modules/rektorat/rekap.php'],
                ['profil',     'Profil',     '/modules/akun/profil.php'],
                ['pengaturan', 'Pengaturan', '/modules/akun/pengaturan.php'],
            ];
        case 'superadmin':
            return [
                ['ringkasan', 'Ringkasan',      '/modules/superadmin/ringkasan.php'],
                ['user',      'Manajemen User', '/modules/superadmin/user.php'],
                ['role',      'Manajemen Role', '/modules/superadmin/role.php'],
                ['log',       'Log Aktivitas',  '/modules/superadmin/log.php'],
            ];
    }
    return [];
}
