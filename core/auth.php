<?php

/**
 * User yang sedang login (dibaca dari DB tiap request, jadi akun yang
 * dinonaktifkan / diganti role langsung berlaku). Null jika belum login.
 */
function current_user(bool $refresh = false): ?array
{
    static $loaded = false, $user = null;
    if ($refresh) $loaded = false;
    if ($loaded) return $user;

    $loaded = true;
    $user = null;
    if (!empty($_SESSION['user_id'])) {
        $user = fetch_one(
            'SELECT u.id, u.nim_nip, u.nama, u.email, u.status, u.foto,
                    r.id AS role_id, r.kode AS role_kode, r.nama AS role_nama
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? LIMIT 1',
            [$_SESSION['user_id']]
        );
        if (!$user || $user['status'] !== 'aktif') {
            $user = null;
            unset($_SESSION['user_id']);
        }
    }
    return $user;
}

/** Halaman awal untuk tiap role. */
function role_home(string $roleKode): string
{
    return match ($roleKode) {
        'mahasiswa', 'dosen' => '/modules/user/dashboard.php',
        'sarpras'            => '/modules/sarpras/dashboard.php',
        'rektorat'           => '/modules/rektorat/dashboard.php',
        'superadmin'         => '/modules/superadmin/ringkasan.php',
        default              => '/modules/auth/halaman-awal.php',
    };
}

function require_login(): array
{
    $user = current_user();
    if (!$user) redirect('/modules/auth/login.php');
    return $user;
}

/** Batasi halaman untuk role tertentu. Contoh: require_role(['sarpras']); */
function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role_kode'], $roles, true)) {
        abort(403);
    }
    return $user;
}

/** @return array [bool berhasil, string pesan] */
function login_attempt(string $identity, string $password): array
{
    $gagal = 'NIM/NIP/email atau kata sandi salah.';

    if (($_SESSION['lock_until'] ?? 0) > time()) {
        $menit = (int) ceil(($_SESSION['lock_until'] - time()) / 60);
        return [false, "Terlalu banyak percobaan. Coba lagi dalam {$menit} menit."];
    }
    if ($identity === '' || $password === '') {
        return [false, 'Isi NIM/NIP/email dan kata sandi.'];
    }

    $user = fetch_one(
        'SELECT id, password, status FROM users WHERE email = ? OR nim_nip = ? LIMIT 1',
        [$identity, $identity]
    );

    // Tetap hitung hash walau user tidak ada, supaya waktu respons seragam.
    $hash  = $user['password'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
    $valid = password_verify($password, $hash) && $user !== null;

    if (!$valid) {
        $_SESSION['login_fail'] = ($_SESSION['login_fail'] ?? 0) + 1;
        if ($_SESSION['login_fail'] >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION['lock_until'] = time() + LOGIN_LOCK_SECONDS;
            $_SESSION['login_fail'] = 0;
        }
        catat_log('Login gagal untuk "' . mb_substr($identity, 0, 60) . '"', 'auth', $user['id'] ?? null);
        return [false, $gagal];
    }

    if ($user['status'] !== 'aktif') {
        return [false, 'Akun Anda nonaktif. Hubungi administrator.'];
    }

    session_regenerate_id(true);            // cegah session fixation
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['login_fail'], $_SESSION['lock_until']);

    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        query('UPDATE users SET password = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    query('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    catat_log('Login berhasil', 'auth', (int) $user['id']);
    current_user(true);

    return [true, ''];
}

function logout_user(): void
{
    if (!empty($_SESSION['user_id'])) {
        catat_log('Logout', 'auth', (int) $_SESSION['user_id']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
