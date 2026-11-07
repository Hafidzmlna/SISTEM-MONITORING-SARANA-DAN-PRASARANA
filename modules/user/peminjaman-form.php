<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';      // err_msg()
require __DIR__ . '/../../core/laporan.php';     // kode_laporan()
require __DIR__ . '/../../core/peminjaman.php';
$u = require_role(['mahasiswa', 'dosen']);

$jenis = ($_GET['jenis'] ?? '') === 'barang' ? 'barang' : 'ruang';
require_permission($jenis === 'barang' ? 'pinjam_barang' : 'pinjam_ruang');
peminjaman_sinkron_waktu();

$id    = (int) ($_GET['id'] ?? 0);
$back  = url('/modules/user/peminjaman.php?tab=' . $jenis);
$today = date('Y-m-d');
$maks  = date('Y-m-d', strtotime('+' . PINJAM_MAJU_MAKS_HARI . ' days'));
$errors = [];

if ($jenis === 'ruang') {
    $item = fetch_one('SELECT r.id, r.nama, r.kondisi, r.kapasitas, g.nama AS gedung FROM ruang r JOIN gedung g ON g.id = r.gedung_id WHERE r.id = ?', [$id]);
    if (!$item) abort(404, 'Ruang tidak ditemukan.');
    if ($item['kondisi'] !== 'baik') { flash_set('warn', 'Ruang ini sedang tidak tersedia untuk dipinjam.'); redirect('/modules/user/peminjaman.php?tab=ruang'); }
    $f = ['tanggal' => $today, 'mulai' => '', 'durasi' => 0, 'keperluan' => ''];
} else {
    $item = fetch_one('SELECT b.id, b.nama, b.kondisi, b.jumlah, r.nama AS ruang, g.nama AS gedung
                       FROM barang b LEFT JOIN ruang r ON r.id = b.ruang_id LEFT JOIN gedung g ON g.id = r.gedung_id WHERE b.id = ?', [$id]);
    if (!$item) abort(404, 'Barang tidak ditemukan.');
    if ($item['kondisi'] !== 'baik') { flash_set('warn', 'Barang ini sedang tidak tersedia untuk dipinjam.'); redirect('/modules/user/peminjaman.php?tab=barang'); }
    $f = ['pinjam' => $today, 'kembali' => $today, 'jumlah' => '1', 'keperluan' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $kep = trim($_POST['keperluan'] ?? '');
    if (mb_strlen($kep) < 5 || mb_strlen($kep) > 255) $errors['keperluan'] = 'Keperluan 5–255 karakter.';

    $hariIni = (int) fetch_value(
        "SELECT (SELECT COUNT(*) FROM peminjaman_ruang WHERE user_id = ? AND created_at >= ?) + (SELECT COUNT(*) FROM peminjaman_barang WHERE user_id = ? AND created_at >= ?)",
        [$u['id'], date('Y-m-d H:i:s', strtotime('-1 day')), $u['id'], date('Y-m-d H:i:s', strtotime('-1 day'))]);
    if ($hariIni >= PINJAM_MAKS_PER_HARI) $errors['form'] = 'Batas pengajuan hari ini sudah tercapai. Coba lagi besok.';

    if ($jenis === 'ruang') {
        $f = ['tanggal' => trim($_POST['tanggal'] ?? ''), 'mulai' => trim($_POST['mulai'] ?? ''), 'durasi' => (int) ($_POST['durasi'] ?? 0), 'keperluan' => $kep];
        if (!tanggal_dalam_rentang($f['tanggal'], $today, $maks)) $errors['tanggal'] = 'Pilih tanggal dari hari ini sampai ' . PINJAM_MAJU_MAKS_HARI . ' hari ke depan.';
        if (!jam_valid($f['mulai'])) $errors['mulai'] = 'Pilih jam mulai (06.00–21.30).';
        if (!isset(DURASI_MENIT[$f['durasi']])) $errors['durasi'] = 'Pilih durasi.';

        $selesai = null;
        if (!isset($errors['mulai']) && !isset($errors['durasi'])) {
            $selesai = jam_selesai($f['mulai'], $f['durasi']);
            if (!$selesai) $errors['durasi'] = 'Peminjaman harus selesai paling lambat pukul 22.00.';
            elseif (!isset($errors['tanggal']) && $f['tanggal'] === $today && $f['mulai'] <= date('H:i')) $errors['mulai'] = 'Jam mulai sudah lewat.';
        }
        if (!$errors && $selesai) {
            if ($b = ruang_bentrok((int) $item['id'], $f['tanggal'], $f['mulai'], $selesai))
                $errors['form'] = 'Ruang sudah dipesan pada ' . waktu_label($b['jam_mulai'], $b['jam_selesai']) . ' (' . $b['kode'] . '). Pilih waktu lain.';
            elseif ($d = fetch_value("SELECT kode FROM peminjaman_ruang WHERE user_id = ? AND tanggal = ? AND status IN ('diajukan','disetujui','digunakan') AND jam_mulai < ? AND jam_selesai > ? LIMIT 1",
                    [$u['id'], $f['tanggal'], $selesai . ':00', $f['mulai'] . ':00']))
                $errors['form'] = "Anda sudah memiliki pengajuan ruangan pada jam tersebut ($d).";
        }
        if (!$errors) {
            $pdo = db(); $pdo->beginTransaction();
            try {
                query('INSERT INTO peminjaman_ruang (user_id, ruang_id, tanggal, jam_mulai, jam_selesai, keperluan) VALUES (?,?,?,?,?,?)',
                      [$u['id'], $item['id'], $f['tanggal'], $f['mulai'] . ':00', $selesai . ':00', $kep]);
                $nid = (int) $pdo->lastInsertId(); $kode = kode_laporan('PR', $nid);
                query('UPDATE peminjaman_ruang SET kode = ? WHERE id = ?', [$kode, $nid]);
                $pdo->commit();
            } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
            catat_log("Mengajukan peminjaman ruang $kode ({$item['nama']})", 'peminjaman');
            flash_set('ok', "Pengajuan $kode berhasil dikirim. Tunggu persetujuan Sarpras.");
            redirect('/modules/user/peminjaman.php?tab=saya');
        }
    } else {
        $f = ['pinjam' => trim($_POST['pinjam'] ?? ''), 'kembali' => trim($_POST['kembali'] ?? ''), 'jumlah' => trim($_POST['jumlah'] ?? '1'), 'keperluan' => $kep];
        if (!tanggal_dalam_rentang($f['pinjam'], $today, $maks)) $errors['pinjam'] = 'Tanggal pinjam tidak valid.';
        $batasKembali = !isset($errors['pinjam']) ? date('Y-m-d', strtotime($f['pinjam'] . ' +' . PINJAM_BARANG_MAKS_HARI . ' days')) : $maks;
        if (!tanggal_dalam_rentang($f['kembali'], $f['pinjam'] ?: $today, $batasKembali)) $errors['kembali'] = 'Tanggal kembali tidak boleh sebelum tanggal pinjam dan maksimal ' . PINJAM_BARANG_MAKS_HARI . ' hari.';
        if (!ctype_digit($f['jumlah']) || (int) $f['jumlah'] < 1 || (int) $f['jumlah'] > (int) $item['jumlah']) $errors['jumlah'] = 'Jumlah 1–' . (int) $item['jumlah'] . ' unit.';

        if (!$errors) {
            $sisa = stok_tersedia((int) $item['id'], $f['pinjam'], $f['kembali']);
            if ($sisa < (int) $f['jumlah']) $errors['form'] = $sisa > 0
                ? "Stok pada tanggal tersebut hanya tersisa $sisa unit."
                : 'Barang tidak tersedia pada tanggal tersebut. Pilih tanggal lain.';
            elseif ($d = fetch_value("SELECT kode FROM peminjaman_barang WHERE user_id = ? AND barang_id = ? AND status IN ('diajukan','disetujui','dipinjam') AND tgl_pinjam <= ? AND tgl_kembali >= ? LIMIT 1",
                    [$u['id'], $item['id'], $f['kembali'], $f['pinjam']]))
                $errors['form'] = "Anda sudah memiliki pengajuan untuk barang ini pada rentang tanggal tersebut ($d).";
        }
        if (!$errors) {
            $pdo = db(); $pdo->beginTransaction();
            try {
                query('INSERT INTO peminjaman_barang (user_id, barang_id, jumlah, tgl_pinjam, tgl_kembali, keperluan) VALUES (?,?,?,?,?,?)',
                      [$u['id'], $item['id'], (int) $f['jumlah'], $f['pinjam'], $f['kembali'], $kep]);
                $nid = (int) $pdo->lastInsertId(); $kode = kode_laporan('PB', $nid);
                query('UPDATE peminjaman_barang SET kode = ? WHERE id = ?', [$kode, $nid]);
                $pdo->commit();
            } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
            catat_log("Mengajukan peminjaman barang $kode ({$item['nama']})", 'peminjaman');
            flash_set('ok', "Pengajuan $kode berhasil dikirim. Tunggu persetujuan Sarpras.");
            redirect('/modules/user/peminjaman.php?tab=saya');
        }
    }
}

$page_title = 'Peminjaman'; $active = 'peminjaman';
require __DIR__ . '/../../layouts/header.php';
?>
<h2 class="title" style="margin:0;font-size:26px"><?= $jenis === 'ruang' ? 'Form Peminjaman Ruang' : 'Form Peminjaman Barang' ?></h2>
<p class="lead"><?= $jenis === 'ruang' ? 'Isi data berikut untuk mengajukan peminjaman ruangan.' : 'Isi data berikut untuk mengajukan peminjaman barang.' ?></p>
<div style="height:20px"></div>

<?php if (isset($errors['form'])): ?><div class="alert bad"><?= e($errors['form']) ?></div><?php endif; ?>

<form method="post" class="card-form" novalidate>
  <?= csrf_field() ?>
  <div class="lap-grid">
    <div class="lap-col">
    <?php if ($jenis === 'ruang'): ?>
      <div class="fld"><label>Nama Ruang</label><input class="in" type="text" readonly value="<?= e($item['nama'] . ' – ' . $item['gedung']) ?>"></div>
      <div class="fld">
        <label for="tanggal">Tanggal</label>
        <input class="in<?= isset($errors['tanggal']) ? ' invalid' : '' ?>" id="tanggal" name="tanggal" type="date" min="<?= e($today) ?>" max="<?= e($maks) ?>" value="<?= e($f['tanggal']) ?>" required>
        <?= err_msg($errors, 'tanggal') ?>
      </div>
      <div class="fld">
        <label for="mulai">Jam Mulai</label>
        <select class="in<?= isset($errors['mulai']) ? ' invalid' : '' ?>" id="mulai" name="mulai" required><?= jam_opsi($f['mulai']) ?></select>
        <?= err_msg($errors, 'mulai') ?>
      </div>
      <div class="fld">
        <label for="durasi">Durasi</label>
        <select class="in<?= isset($errors['durasi']) ? ' invalid' : '' ?>" id="durasi" name="durasi" required><?= durasi_opsi($f['durasi']) ?></select>
        <?= err_msg($errors, 'durasi') ?>
      </div>
    <?php else: ?>
      <div class="fld"><label>Nama Barang</label><input class="in" type="text" readonly value="<?= e($item['nama']) ?>"></div>
      <?php if ((int) $item['jumlah'] > 1): ?>
        <div class="fld">
          <label for="jumlah">Jumlah Unit <span class="hint">(maksimal <?= (int) $item['jumlah'] ?>)</span></label>
          <input class="in<?= isset($errors['jumlah']) ? ' invalid' : '' ?>" id="jumlah" name="jumlah" inputmode="numeric" value="<?= e($f['jumlah']) ?>">
          <?= err_msg($errors, 'jumlah') ?>
        </div>
      <?php else: ?><input type="hidden" name="jumlah" value="1"><?php endif; ?>
      <div class="fld">
        <label for="pinjam">Tanggal Pinjam</label>
        <input class="in<?= isset($errors['pinjam']) ? ' invalid' : '' ?>" id="pinjam" name="pinjam" type="date" min="<?= e($today) ?>" max="<?= e($maks) ?>" value="<?= e($f['pinjam']) ?>" required>
        <?= err_msg($errors, 'pinjam') ?>
      </div>
      <div class="fld">
        <label for="kembali">Tanggal Kembali</label>
        <input class="in<?= isset($errors['kembali']) ? ' invalid' : '' ?>" id="kembali" name="kembali" type="date" min="<?= e($today) ?>" value="<?= e($f['kembali']) ?>" required>
        <?= err_msg($errors, 'kembali') ?>
      </div>
    <?php endif; ?>
    </div>
    <div class="lap-col">
      <div class="fld grow">
        <label for="keperluan">Keperluan</label>
        <textarea class="in<?= isset($errors['keperluan']) ? ' invalid' : '' ?>" id="keperluan" name="keperluan" maxlength="255"
                  placeholder="<?= $jenis === 'ruang' ? 'Jelaskan keperluan peminjaman ruangan' : 'Jelaskan keperluan peminjaman barang' ?>" required><?= e($f['keperluan']) ?></textarea>
        <?= err_msg($errors, 'keperluan') ?>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <a class="btn sec" href="<?= e($back) ?>">Batal</a>
    <button class="btn" type="submit">Ajukan Peminjaman</button>
  </div>
</form>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
