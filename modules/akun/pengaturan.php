<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';      // err_msg()
$me = require_login();

const FORMAT_TANGGAL = ['d/m/Y' => 'dd/mm/yyyy', 'd-m-Y' => 'dd-mm-yyyy', 'Y-m-d' => 'yyyy-mm-dd'];
$self = url('/modules/akun/pengaturan.php');
$errSandi = [];

query('INSERT IGNORE INTO user_settings (user_id) VALUES (?)', [$me['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'pref') {
        $fmt = $_POST['format_tanggal'] ?? 'd/m/Y';
        if (!isset(FORMAT_TANGGAL[$fmt])) $fmt = 'd/m/Y';
        $cb = fn(string $k) => empty($_POST[$k]) ? 0 : 1;
        query('UPDATE user_settings SET notif_laporan_baru = ?, notif_laporan_selesai = ?, notif_peminjaman_telat = ?,
               ringkasan_mingguan = ?, bahasa = ?, format_tanggal = ? WHERE user_id = ?',
              [$cb('notif_laporan_baru'), $cb('notif_laporan_selesai'), $cb('notif_peminjaman_telat'), $cb('ringkasan_mingguan'), 'id', $fmt, $me['id']]);
        catat_log('Memperbarui pengaturan', 'akun');
        flash_set('ok', 'Pengaturan berhasil disimpan.');
        redirect('/modules/akun/pengaturan.php');
    }

    if ($aksi === 'sandi') {
        $lama = (string) ($_POST['sandi_lama'] ?? '');
        $baru = (string) ($_POST['sandi_baru'] ?? '');
        if (($_SESSION['pw_lock'] ?? 0) > time()) {
            $errSandi['sandi_lama'] = 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.';
        } else {
            $hash = (string) fetch_value('SELECT password FROM users WHERE id = ?', [$me['id']]);
            if (!password_verify($lama, $hash)) {
                $_SESSION['pw_fail'] = ($_SESSION['pw_fail'] ?? 0) + 1;
                if ($_SESSION['pw_fail'] >= 5) { $_SESSION['pw_lock'] = time() + 300; $_SESSION['pw_fail'] = 0; }
                $errSandi['sandi_lama'] = 'Kata sandi lama salah.';
            } elseif (strlen($baru) < 8)   $errSandi['sandi_baru'] = 'Kata sandi baru minimal 8 karakter.';
            elseif (strlen($baru) > 72)    $errSandi['sandi_baru'] = 'Kata sandi baru maksimal 72 karakter.';
            elseif ($baru === $lama)       $errSandi['sandi_baru'] = 'Kata sandi baru harus berbeda dari yang lama.';
            else {
                query('UPDATE users SET password = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $me['id']]);
                unset($_SESSION['pw_fail'], $_SESSION['pw_lock']);
                session_regenerate_id(true);
                catat_log('Mengubah kata sandi', 'akun');
                flash_set('ok', 'Kata sandi berhasil diubah.');
                redirect('/modules/akun/pengaturan.php');
            }
        }
    }
}

$s = fetch_one('SELECT * FROM user_settings WHERE user_id = ?', [$me['id']]);
$notif = [
    ['notif_laporan_baru',     'Laporan kerusakan baru', 'Terima notifikasi saat ada laporan masuk'],
    ['notif_laporan_selesai',  'Laporan selesai',        'Terima notifikasi saat perbaikan selesai'],
    ['notif_peminjaman_telat', 'Peminjaman terlambat',   'Peringatan barang belum dikembalikan'],
    ['ringkasan_mingguan',     'Ringkasan mingguan',     'Kirim rekap ke email setiap minggu'],
];

$page_title = 'Pengaturan'; $active = 'pengaturan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head"><div>
  <h2 class="title" style="font-size:26px">Pengaturan</h2>
  <p class="lead">Atur notifikasi, tampilan, dan keamanan akun</p>
</div></div>

<form id="f-pref" method="post"><?= csrf_field() ?><input type="hidden" name="aksi" value="pref"></form>

<div class="set-grid">
  <section class="cardx">
    <h2 style="margin-bottom:8px">Notifikasi</h2>
    <?php foreach ($notif as [$k, $judul, $ket]): ?>
      <label class="tgl">
        <span><b><?= e($judul) ?></b><small><?= e($ket) ?></small></span>
        <span class="sw"><input type="checkbox" name="<?= e($k) ?>" value="1" form="f-pref"<?= !empty($s[$k]) ? ' checked' : '' ?>><i></i></span>
      </label>
    <?php endforeach; ?>
    <p class="hint" style="margin-bottom:0">Pilihan ini disimpan di akun Anda. Pengiriman notifikasi dan email belum diaktifkan pada versi ini.</p>
  </section>

  <div class="set-stack">
    <section class="cardx">
      <h2 style="margin-bottom:16px">Tampilan</h2>
      <div class="fld">
        <label for="bahasa">Bahasa</label>
        <select class="in" id="bahasa" name="bahasa" form="f-pref"><option value="id" selected>Bahasa Indonesia</option></select>
      </div>
      <div class="fld">
        <label for="format_tanggal">Format Tanggal</label>
        <select class="in" id="format_tanggal" name="format_tanggal" form="f-pref">
          <?php foreach (FORMAT_TANGGAL as $v => $lbl): ?>
            <option value="<?= e($v) ?>"<?= $s['format_tanggal'] === $v ? ' selected' : '' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn" type="submit" form="f-pref">Simpan Pengaturan</button>
    </section>

    <section class="cardx">
      <h2 style="margin-bottom:16px">Keamanan</h2>
      <form method="post" novalidate>
        <?= csrf_field() ?><input type="hidden" name="aksi" value="sandi">
        <div class="fld">
          <label for="sandi_lama">Kata Sandi Lama</label>
          <input class="in<?= isset($errSandi['sandi_lama']) ? ' invalid' : '' ?>" id="sandi_lama" name="sandi_lama" type="password" autocomplete="current-password" placeholder="••••••••" required>
          <?= err_msg($errSandi, 'sandi_lama') ?>
        </div>
        <div class="fld">
          <label for="sandi_baru">Kata Sandi Baru</label>
          <input class="in<?= isset($errSandi['sandi_baru']) ? ' invalid' : '' ?>" id="sandi_baru" name="sandi_baru" type="password" autocomplete="new-password" placeholder="••••••••" required>
          <?= err_msg($errSandi, 'sandi_baru') ?>
          <span class="hint">Minimal 8 karakter.</span>
        </div>
        <button class="btn" type="submit">Simpan Kata Sandi</button>
      </form>
    </section>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
