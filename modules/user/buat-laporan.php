<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';     // err_msg()
require __DIR__ . '/../../core/laporan.php';
$u = require_role(['mahasiswa', 'dosen']);

$bisaRusak  = has_permission('buat_laporan_kerusakan');
$bisaMutasi = has_permission('buat_laporan_mutasi');
if (!$bisaRusak && !$bisaMutasi) abort(403);

$tab = $_GET['tab'] ?? ($bisaRusak ? 'kerusakan' : 'mutasi');
if (!in_array($tab, ['kerusakan', 'mutasi'], true)) $tab = 'kerusakan';
if ($tab === 'kerusakan' && !$bisaRusak)  $tab = 'mutasi';
if ($tab === 'mutasi' && !$bisaMutasi)    $tab = 'kerusakan';

$hariIni = date('Y-m-d');
$errors  = [];

function tanggal_ok(string $s, string $min, string $max): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s && $s >= $min && $s <= $max;
}

/* =====================================================
   LAPORAN KERUSAKAN
   ===================================================== */
$f = ['nama_barang' => '', 'lokasi' => '', 'tanggal' => $hariIni, 'kategori' => '', 'deskripsi' => ''];

if ($tab === 'kerusakan' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = [
        'nama_barang' => trim($_POST['nama_barang'] ?? ''),
        'lokasi'      => trim($_POST['lokasi'] ?? ''),
        'tanggal'     => trim($_POST['tanggal'] ?? ''),
        'kategori'    => $_POST['kategori'] ?? '',
        'deskripsi'   => trim($_POST['deskripsi'] ?? ''),
    ];

    if (mb_strlen($f['nama_barang']) < 2 || mb_strlen($f['nama_barang']) > 120) $errors['nama_barang'] = 'Nama barang 2–120 karakter.';
    if (mb_strlen($f['lokasi']) < 2 || mb_strlen($f['lokasi']) > 150)           $errors['lokasi'] = 'Lokasi 2–150 karakter.';
    if (!tanggal_ok($f['tanggal'], date('Y-m-d', strtotime('-365 days')), $hariIni)) $errors['tanggal'] = 'Pilih tanggal yang valid (tidak boleh di masa depan).';
    if (!isset(KATEGORI_FASILITAS[$f['kategori']])) $errors['kategori'] = 'Pilih kategori fasilitas.';
    if (mb_strlen($f['deskripsi']) < 5 || mb_strlen($f['deskripsi']) > 1000)    $errors['deskripsi'] = 'Deskripsi 5–1000 karakter.';

    if (!$errors) {
        $dup = fetch_value(
            "SELECT kode FROM laporan_kerusakan
             WHERE pelapor_id = ? AND nama_barang = ? AND lokasi = ? AND status IN ('diajukan','diproses') LIMIT 1",
            [$u['id'], $f['nama_barang'], $f['lokasi']]
        );
        if ($dup) $errors['form'] = "Anda sudah melaporkan barang ini di lokasi yang sama dan laporannya masih berjalan ($dup).";
        elseif ((int) fetch_value('SELECT COUNT(*) FROM laporan_kerusakan WHERE pelapor_id = ? AND created_at >= NOW() - INTERVAL 1 DAY', [$u['id']]) >= LAPORAN_MAKS_PER_HARI)
            $errors['form'] = 'Batas pengiriman laporan hari ini sudah tercapai. Coba lagi besok.';
    }

    $foto = null;
    if (!$errors) {
        $fotoErr = null;
        $foto = simpan_foto_laporan($_FILES['foto'] ?? null, $fotoErr);
        if ($fotoErr) $errors['foto'] = $fotoErr;
    }

    if (!$errors) {
        $ruangId  = cari_ruang_dari_lokasi($f['lokasi']);
        $barangId = null;
        if ($ruangId) {
            $cocok = fetch_all('SELECT id FROM barang WHERE ruang_id = ? AND nama = ?', [$ruangId, $f['nama_barang']]);
            if (count($cocok) === 1) $barangId = (int) $cocok[0]['id'];
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            query(
                'INSERT INTO laporan_kerusakan (pelapor_id, barang_id, ruang_id, nama_barang, lokasi, kategori_fasilitas, deskripsi, foto, tanggal_lapor)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$u['id'], $barangId, $ruangId, $f['nama_barang'], $f['lokasi'], $f['kategori'], $f['deskripsi'], $foto, $f['tanggal']]
            );
            $id   = (int) $pdo->lastInsertId();
            $kode = kode_laporan('LP', $id);
            query('UPDATE laporan_kerusakan SET kode = ? WHERE id = ?', [$kode, $id]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            hapus_foto_laporan($foto);
            throw $e;
        }
        catat_log("Membuat laporan kerusakan $kode ({$f['nama_barang']})", 'laporan');
        flash_set('ok', "Laporan $kode berhasil dikirim. Pantau statusnya di halaman ini.");
        redirect('/modules/user/status-laporan.php');
    }
}

/* =====================================================
   LAPORAN MUTASI
   ===================================================== */
$m = ['barang_id' => 0, 'ke_ruang_id' => 0, 'tanggal' => $hariIni, 'keterangan' => ''];

if ($tab === 'mutasi' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $m = [
        'barang_id'   => (int) ($_POST['barang_id'] ?? 0),
        'ke_ruang_id' => (int) ($_POST['ke_ruang_id'] ?? 0),
        'tanggal'     => trim($_POST['tanggal'] ?? ''),
        'keterangan'  => trim($_POST['keterangan'] ?? ''),
    ];
    $barang = $m['barang_id'] ? fetch_one('SELECT id, nama, ruang_id FROM barang WHERE id = ?', [$m['barang_id']]) : null;
    $tujuan = $m['ke_ruang_id'] ? fetch_one('SELECT id FROM ruang WHERE id = ?', [$m['ke_ruang_id']]) : null;

    if (!$barang) $errors['barang_id'] = 'Pilih barang yang akan dipindahkan.';
    if (!$tujuan) $errors['ke_ruang_id'] = 'Pilih ruang tujuan.';
    elseif ($barang && (int) $barang['ruang_id'] === $m['ke_ruang_id']) $errors['ke_ruang_id'] = 'Ruang tujuan sama dengan lokasi barang saat ini.';
    if (!tanggal_ok($m['tanggal'], date('Y-m-d', strtotime('-365 days')), date('Y-m-d', strtotime('+365 days')))) $errors['tanggal'] = 'Pilih tanggal yang valid.';
    if (mb_strlen($m['keterangan']) < 5 || mb_strlen($m['keterangan']) > 255) $errors['keterangan'] = 'Keterangan 5–255 karakter.';

    if (!$errors) {
        $dup = fetch_value("SELECT kode FROM laporan_mutasi WHERE barang_id = ? AND status = 'diajukan' LIMIT 1", [$barang['id']]);
        if ($dup) $errors['form'] = "Barang ini sudah memiliki pengajuan mutasi yang menunggu persetujuan ($dup).";
        elseif ((int) fetch_value('SELECT COUNT(*) FROM laporan_mutasi WHERE pelapor_id = ? AND created_at >= NOW() - INTERVAL 1 DAY', [$u['id']]) >= LAPORAN_MAKS_PER_HARI)
            $errors['form'] = 'Batas pengiriman laporan hari ini sudah tercapai. Coba lagi besok.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            query(
                'INSERT INTO laporan_mutasi (pelapor_id, barang_id, dari_ruang_id, ke_ruang_id, tanggal, keterangan) VALUES (?,?,?,?,?,?)',
                [$u['id'], $barang['id'], $barang['ruang_id'], $tujuan['id'], $m['tanggal'], $m['keterangan']]
            );
            $id   = (int) $pdo->lastInsertId();
            $kode = kode_laporan('LM', $id);
            query('UPDATE laporan_mutasi SET kode = ? WHERE id = ?', [$kode, $id]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        catat_log("Membuat laporan mutasi $kode ({$barang['nama']})", 'laporan');
        flash_set('ok', "Laporan mutasi $kode berhasil dikirim dan menunggu persetujuan Sarpras.");
        redirect('/modules/user/status-laporan.php?tab=mutasi');
    }
}

/* ---------- Data pendukung form ---------- */
$self = url('/modules/user/buat-laporan.php');
if ($tab === 'kerusakan') {
    $saranBarang = array_column(fetch_all('SELECT DISTINCT nama FROM barang ORDER BY nama LIMIT 500'), 'nama');
    $saranLokasi = array_map(fn($r) => $r['gedung'] . ' – ' . $r['nama'],
        fetch_all('SELECT r.nama, g.nama AS gedung FROM ruang r JOIN gedung g ON g.id = r.gedung_id ORDER BY g.nama, r.nama LIMIT 500'));
} else {
    $daftarBarang = fetch_all(
        'SELECT b.id, b.nama, b.kode_barang, r.nama AS ruang, g.nama AS gedung
         FROM barang b LEFT JOIN ruang r ON r.id = b.ruang_id LEFT JOIN gedung g ON g.id = r.gedung_id
         ORDER BY b.nama LIMIT 2000');
    $ruangTujuan = [];
    foreach (fetch_all('SELECT r.id, r.nama, g.nama AS gedung FROM ruang r JOIN gedung g ON g.id = r.gedung_id ORDER BY g.nama, r.nama') as $r)
        $ruangTujuan[$r['gedung']][] = $r;
}

$page_title = 'Buat Laporan'; $active = 'buat-laporan';
require __DIR__ . '/../../layouts/header.php';
?>
<h2 class="title" style="margin:0;font-size:26px">Buat Laporan</h2>
<p class="lead">Laporkan kerusakan atau perpindahan sarana dan prasarana kampus.</p>

<div class="seg">
  <?php if ($bisaRusak): ?><a class="<?= $tab === 'kerusakan' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=kerusakan">Buat Laporan Kerusakan</a><?php endif; ?>
  <?php if ($bisaMutasi): ?><a class="<?= $tab === 'mutasi' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=mutasi">Buat Laporan Mutasi Barang</a><?php endif; ?>
</div>

<?php if (isset($errors['form'])): ?><div class="alert bad"><?= e($errors['form']) ?></div><?php endif; ?>

<?php if ($tab === 'kerusakan'): ?>
<form method="post" enctype="multipart/form-data" class="card-form" novalidate>
  <?= csrf_field() ?>
  <div class="lap-grid">
    <div class="lap-col">
      <div class="fld">
        <label for="nama_barang">Nama Barang</label>
        <input class="in<?= isset($errors['nama_barang']) ? ' invalid' : '' ?>" id="nama_barang" name="nama_barang" list="dl-barang" autocomplete="off"
               value="<?= e($f['nama_barang']) ?>" placeholder="Contoh: Proyektor" required>
        <datalist id="dl-barang"><?php foreach ($saranBarang as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
        <?= err_msg($errors, 'nama_barang') ?>
      </div>
      <div class="fld">
        <label for="lokasi">Lokasi</label>
        <input class="in<?= isset($errors['lokasi']) ? ' invalid' : '' ?>" id="lokasi" name="lokasi" list="dl-lokasi" autocomplete="off"
               value="<?= e($f['lokasi']) ?>" placeholder="Contoh: Gedung A – Ruang 204" required>
        <datalist id="dl-lokasi"><?php foreach ($saranLokasi as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
        <?= err_msg($errors, 'lokasi') ?>
      </div>
      <div class="fld">
        <label for="tanggal">Tanggal</label>
        <input class="in<?= isset($errors['tanggal']) ? ' invalid' : '' ?>" id="tanggal" name="tanggal" type="date" max="<?= e($hariIni) ?>" value="<?= e($f['tanggal']) ?>" required>
        <?= err_msg($errors, 'tanggal') ?>
      </div>
      <div class="fld grow">
        <label for="deskripsi">Deskripsi Singkat</label>
        <textarea class="in<?= isset($errors['deskripsi']) ? ' invalid' : '' ?>" id="deskripsi" name="deskripsi" maxlength="1000" placeholder="Jelaskan kerusakan secara singkat" required><?= e($f['deskripsi']) ?></textarea>
        <?= err_msg($errors, 'deskripsi') ?>
      </div>
    </div>
    <div class="lap-col">
      <div class="fld">
        <label for="kategori">Kategori Fasilitas</label>
        <select class="in<?= isset($errors['kategori']) ? ' invalid' : '' ?>" id="kategori" name="kategori" required><?= opsi_kategori_fasilitas($f['kategori']) ?></select>
        <?= err_msg($errors, 'kategori') ?>
      </div>
      <div class="fld grow">
        <span class="lbl" style="font-size:14px;font-weight:600">Foto Kerusakan <span class="hint">(opsional)</span></span>
        <label class="drop" for="foto" style="flex:1">
          <input type="file" id="foto" name="foto" accept="image/png,image/jpeg" data-preview>
          <img class="drop-prev" alt="Pratinjau foto" hidden>
          <?= icon('upload') ?>
          <strong style="font-size:15px">Unggah foto kerusakan</strong>
          <span>PNG atau JPG, maksimal 5 MB</span>
          <span class="drop-name"></span>
        </label>
        <?= err_msg($errors, 'foto') ?>
        <?php if ($errors && $tab === 'kerusakan'): ?><span class="hint">Jika sebelumnya memilih foto, pilih ulang setelah memperbaiki isian.</span><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <a class="btn sec" href="<?= e(url('/modules/user/dashboard.php')) ?>">Batal</a>
    <button class="btn" type="submit">Kirim Laporan</button>
  </div>
</form>

<?php else: ?>
<form method="post" class="card-form" novalidate>
  <?= csrf_field() ?>
  <div class="lap-grid">
    <div class="lap-col">
      <div class="fld">
        <label for="barang_id">Nama Barang</label>
        <select class="in<?= isset($errors['barang_id']) ? ' invalid' : '' ?>" id="barang_id" name="barang_id" data-fill="#dari" required>
          <option value="0" data-val="">Pilih barang</option>
          <?php foreach ($daftarBarang as $b):
              $lok = $b['ruang'] ? $b['ruang'] . ', ' . $b['gedung'] : ''; ?>
            <option value="<?= (int) $b['id'] ?>" data-val="<?= e($lok) ?>"<?= $m['barang_id'] === (int) $b['id'] ? ' selected' : '' ?>>
              <?= e($b['nama'] . ($b['kode_barang'] ? ' (' . $b['kode_barang'] . ')' : '') . ' — ' . ($lok ?: 'belum ditempatkan')) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= err_msg($errors, 'barang_id') ?>
      </div>
      <div class="fld">
        <label for="dari">Dari</label>
        <input class="in" id="dari" type="text" readonly tabindex="-1" placeholder="Otomatis sesuai lokasi barang saat ini">
      </div>
      <div class="fld">
        <label for="ke_ruang_id">Ke</label>
        <select class="in<?= isset($errors['ke_ruang_id']) ? ' invalid' : '' ?>" id="ke_ruang_id" name="ke_ruang_id" required>
          <option value="0">Pilih ruang tujuan</option>
          <?php foreach ($ruangTujuan as $gedung => $items): ?>
            <optgroup label="<?= e($gedung) ?>">
              <?php foreach ($items as $r): ?>
                <option value="<?= (int) $r['id'] ?>"<?= $m['ke_ruang_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nama']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <?= err_msg($errors, 'ke_ruang_id') ?>
      </div>
      <div class="fld">
        <label for="tanggal">Tanggal</label>
        <input class="in<?= isset($errors['tanggal']) ? ' invalid' : '' ?>" id="tanggal" name="tanggal" type="date" value="<?= e($m['tanggal']) ?>" required>
        <?= err_msg($errors, 'tanggal') ?>
      </div>
    </div>
    <div class="lap-col">
      <div class="fld grow">
        <label for="keterangan">Keterangan</label>
        <textarea class="in<?= isset($errors['keterangan']) ? ' invalid' : '' ?>" id="keterangan" name="keterangan" maxlength="255" placeholder="Jelaskan alasan atau keterangan mutasi barang" required><?= e($m['keterangan']) ?></textarea>
        <?= err_msg($errors, 'keterangan') ?>
      </div>
    </div>
  </div>
  <div class="form-actions">
    <a class="btn sec" href="<?= e(url('/modules/user/dashboard.php')) ?>">Batal</a>
    <button class="btn" type="submit">Kirim Laporan</button>
  </div>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
