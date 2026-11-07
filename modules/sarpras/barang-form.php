<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';
require_role(['sarpras']);
require_permission('kelola_barang');

$list = url('/modules/sarpras/barang.php');
$id   = (int) ($_GET['id'] ?? 0);
$edit = null;
if ($id) {
    $edit = fetch_one('SELECT * FROM barang WHERE id = ?', [$id]);
    if (!$edit) abort(404, 'Barang tidak ditemukan.');
}
$kategori = fetch_all('SELECT id, nama FROM kategori_barang ORDER BY nama');
$ruangRows = fetch_all('SELECT r.id, r.nama, g.nama AS gedung FROM ruang r JOIN gedung g ON g.id = r.gedung_id ORDER BY g.nama, r.nama');
$ruangPerGedung = [];
foreach ($ruangRows as $r) $ruangPerGedung[$r['gedung']][] = $r;

$f = $edit
    ? ['kode_barang' => (string) $edit['kode_barang'], 'nama' => $edit['nama'], 'kategori_id' => (int) $edit['kategori_id'],
       'ruang_id' => (int) $edit['ruang_id'], 'jumlah' => (string) $edit['jumlah'], 'kondisi' => $edit['kondisi'],
       'status' => $edit['status'], 'keterangan' => (string) $edit['keterangan']]
    : ['kode_barang' => '', 'nama' => '', 'kategori_id' => 0, 'ruang_id' => 0, 'jumlah' => '1', 'kondisi' => 'baik',
       'status' => 'tersedia', 'keterangan' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = [
        'kode_barang' => trim($_POST['kode_barang'] ?? ''),
        'nama'        => trim($_POST['nama'] ?? ''),
        'kategori_id' => (int) ($_POST['kategori_id'] ?? 0),
        'ruang_id'    => (int) ($_POST['ruang_id'] ?? 0),
        'jumlah'      => trim($_POST['jumlah'] ?? ''),
        'kondisi'     => $_POST['kondisi'] ?? '',
        'status'      => $_POST['status'] ?? '',
        'keterangan'  => trim($_POST['keterangan'] ?? ''),
    ];
    if ($f['kode_barang'] !== '' && !preg_match('#^[A-Za-z0-9._/-]{2,30}$#', $f['kode_barang']))
        $errors['kode_barang'] = 'Kode 2–30 karakter: huruf, angka, titik, strip, garis miring.';
    elseif ($f['kode_barang'] !== '' && fetch_value('SELECT id FROM barang WHERE kode_barang = ? AND id <> ?', [$f['kode_barang'], $id]))
        $errors['kode_barang'] = 'Kode barang sudah digunakan.';
    if (mb_strlen($f['nama']) < 2 || mb_strlen($f['nama']) > 120) $errors['nama'] = 'Nama barang 2–120 karakter.';
    if (!in_array($f['kategori_id'], array_map('intval', array_column($kategori, 'id')), true)) $errors['kategori_id'] = 'Pilih kategori.';
    if ($f['ruang_id'] && !in_array($f['ruang_id'], array_map('intval', array_column($ruangRows, 'id')), true)) $errors['ruang_id'] = 'Ruang tidak valid.';
    if (!ctype_digit($f['jumlah']) || (int) $f['jumlah'] < 1 || (int) $f['jumlah'] > 100000) $errors['jumlah'] = 'Jumlah berupa angka 1–100000.';
    if (!in_array($f['kondisi'], KONDISI_BARANG, true)) $errors['kondisi'] = 'Kondisi tidak valid.';
    if (!in_array($f['status'], STATUS_BARANG, true))   $errors['status']  = 'Status tidak valid.';
    if (mb_strlen($f['keterangan']) > 255) $errors['keterangan'] = 'Keterangan maksimal 255 karakter.';

    if (!$errors) {
        $par = [$f['kode_barang'] ?: null, $f['nama'], $f['kategori_id'], $f['ruang_id'] ?: null,
                (int) $f['jumlah'], $f['kondisi'], $f['status'], $f['keterangan'] ?: null];
        try {
            if ($edit) {
                query('UPDATE barang SET kode_barang=?, nama=?, kategori_id=?, ruang_id=?, jumlah=?, kondisi=?, status=?, keterangan=? WHERE id=?',
                      array_merge($par, [$id]));
                catat_log('Mengubah barang ' . $f['nama'], 'barang');
                flash_set('ok', 'Data barang berhasil diperbarui.');
            } else {
                query('INSERT INTO barang (kode_barang, nama, kategori_id, ruang_id, jumlah, kondisi, status, keterangan) VALUES (?,?,?,?,?,?,?,?)', $par);
                catat_log('Menambah barang ' . $f['nama'], 'barang');
                flash_set('ok', 'Barang "' . $f['nama'] . '" berhasil ditambahkan.');
            }
            redirect('/modules/sarpras/barang.php');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $errors['kode_barang'] = 'Kode barang sudah digunakan.';
        }
    }
}

$page_title = 'Master Data'; $active = 'master-data';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title"><?= $edit ? 'Ubah Barang' : 'Tambah Barang' ?></h2>
  <a class="btn sec sm" href="<?= e($list) ?>">Kembali</a>
</div>
<section class="panel">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="fld">
        <label for="nama">Nama Barang</label>
        <input class="in<?= isset($errors['nama']) ? ' invalid' : '' ?>" id="nama" name="nama" value="<?= e($f['nama']) ?>" placeholder="Contoh: Proyektor Epson EB-X06" required autofocus>
        <?= err_msg($errors, 'nama') ?>
      </div>
      <div class="fld">
        <label for="kode_barang">Kode Barang <span class="hint">(opsional)</span></label>
        <input class="in<?= isset($errors['kode_barang']) ? ' invalid' : '' ?>" id="kode_barang" name="kode_barang" value="<?= e($f['kode_barang']) ?>" placeholder="Contoh: INV-ELK-001">
        <?= err_msg($errors, 'kode_barang') ?>
      </div>
      <div class="fld">
        <label for="kategori_id">Kategori</label>
        <select class="in<?= isset($errors['kategori_id']) ? ' invalid' : '' ?>" id="kategori_id" name="kategori_id" required>
          <option value="">Pilih kategori</option>
          <?php foreach ($kategori as $k): ?>
            <option value="<?= (int) $k['id'] ?>"<?= $f['kategori_id'] === (int) $k['id'] ? ' selected' : '' ?>><?= e($k['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= err_msg($errors, 'kategori_id') ?>
      </div>
      <div class="fld">
        <label for="ruang_id">Lokasi (Ruang)</label>
        <select class="in<?= isset($errors['ruang_id']) ? ' invalid' : '' ?>" id="ruang_id" name="ruang_id">
          <option value="0">Belum ditempatkan</option>
          <?php foreach ($ruangPerGedung as $gedung => $items): ?>
            <optgroup label="<?= e($gedung) ?>">
              <?php foreach ($items as $r): ?>
                <option value="<?= (int) $r['id'] ?>"<?= $f['ruang_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nama']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <?= err_msg($errors, 'ruang_id') ?>
      </div>
      <div class="fld">
        <label for="jumlah">Jumlah Unit</label>
        <input class="in<?= isset($errors['jumlah']) ? ' invalid' : '' ?>" id="jumlah" name="jumlah" inputmode="numeric" value="<?= e($f['jumlah']) ?>">
        <?= err_msg($errors, 'jumlah') ?>
      </div>
      <div class="fld">
        <label for="kondisi">Kondisi</label>
        <select class="in" id="kondisi" name="kondisi"><?= opt_enum(KONDISI_BARANG, $f['kondisi']) ?></select>
        <?= err_msg($errors, 'kondisi') ?>
      </div>
      <div class="fld">
        <label for="status">Status</label>
        <select class="in" id="status" name="status"><?= opt_enum(STATUS_BARANG, $f['status']) ?></select>
        <?= err_msg($errors, 'status') ?>
      </div>
      <div class="fld">
        <label for="keterangan">Keterangan <span class="hint">(opsional)</span></label>
        <input class="in<?= isset($errors['keterangan']) ? ' invalid' : '' ?>" id="keterangan" name="keterangan" value="<?= e($f['keterangan']) ?>">
        <?= err_msg($errors, 'keterangan') ?>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn sec" href="<?= e($list) ?>">Batal</a>
      <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Simpan Barang' ?></button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
