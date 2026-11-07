<?php
// Helper akun: foto profil (PNG/JPG maks 2 MB, disajikan lewat modules/akun/foto.php).

const FOTO_PROFIL_MAKS = 2 * 1024 * 1024;

function simpan_foto_profil(?array $file, ?string &$error): ?string
{
    $error = null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { $error = 'Pilih foto terlebih dahulu.'; return null; }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'Ukuran foto melebihi batas.' : 'Foto gagal diunggah, coba lagi.';
        return null;
    }
    if ($file['size'] > FOTO_PROFIL_MAKS) { $error = 'Ukuran foto maksimal 2 MB.'; return null; }
    $info = @getimagesize($file['tmp_name']);
    $tipe = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (!$info || !isset($tipe[$info[2]])) { $error = 'Foto harus berformat PNG atau JPG.'; return null; }
    if ($info[0] > 6000 || $info[1] > 6000) { $error = 'Dimensi foto terlalu besar (maks 6000 px).'; return null; }

    $dir = __DIR__ . '/../uploads/profil';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { $error = 'Folder penyimpanan tidak dapat dibuat.'; return null; }
    $nama = bin2hex(random_bytes(16)) . '.' . $tipe[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nama)) { $error = 'Foto gagal disimpan.'; return null; }
    return $nama;
}

function path_foto_profil(?string $nama): ?string
{
    if (!$nama || !preg_match('/^[a-f0-9]{32}\.(jpg|png)$/', $nama)) return null;
    $p = __DIR__ . '/../uploads/profil/' . $nama;
    return is_file($p) ? $p : null;
}

function hapus_foto_profil(?string $nama): void
{
    if ($p = path_foto_profil($nama)) @unlink($p);
}
