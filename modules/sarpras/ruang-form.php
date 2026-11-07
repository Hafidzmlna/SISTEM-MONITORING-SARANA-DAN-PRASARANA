<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';
require_role(['sarpras']);
require_permission('kelola_ruang');

$list = url('/modules/sarpras/ruang.php');
$id   = (int) ($_GET['id'] ?? 0);
$edit = null;
if ($id) {
    $edit = fetch_one('SELECT * FROM ruang WHERE id = ?', [$id]);
    if (!$edit) abort(404, 'Ruang tidak ditemukan.');
}
$gedung = fetch_all('SELECT id, nama FROM gedung ORDER BY nama');

$f = $edit
    ? ['gedung_id' => (int) $edit['gedung_id'], 'nama' => $edit['nama'], 'kapasitas' => (string) $edit['kapasitas'],
       'kondisi' => $edit['kondisi'], 'status' => $edit['status'], 'keterangan' => (string) $edit['keterangan']]
    : ['gedung_id' => (int) ($_GET['gedung'] ?? 0), 'nama' => '', 'kapasitas' => '', 'kondisi' => 'baik', 'status' => 'tersedia', 'keterangan' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = [
        'gedung_id'  => (int) ($_POST['gedung_id'] ?? 0),
        'nama'       => trim($_POST['nama'] ?? ''),
        'kapasitas'  => trim($_POST['kapasitas'] ?? ''),
        'kondisi'    => $_POST['kondisi'] ?? '',
        'status'     => $_POST['status'] ?? '',
        'keterangan' => trim($_POST['keterangan'] ?? ''),
    ];
    if (!in_array($f['gedung_id'], array_map('intval', array_column($gedung, 'id')), true)) $errors['gedung_id'] = 'Pilih gedung.';
    if (mb_strlen($f['nama']) < 1 || mb_strlen($f['nama']) > 100) $errors['nama'] = 'Nama ruang 1–100 karakter.';
    elseif (!isset($errors['gedung_id']) && fetch_value(
        'SELECT id FROM ruang WHERE gedung_id = ? AND nama = ? AND id <> ?', [$f['gedung_id'], $f['nama'], $id]))
        $errors['nama'] = 'Nama ruang sudah ada di gedung ini.';
    if ($f['kapasitas'] !== '' && (!ctype_digit($f['kapasitas']) || (int) $f['kapasitas'] < 1 || (int) $f['kapasitas'] > 10000))
        $errors['kapasitas'] = 'Kapasitas berupa angka 1–10000.';
    if (!in_array($f['kondisi'], KONDISI_GEDUNG, true)) $errors['kondisi'] = 'Kondisi tidak valid.';
    if (!in_array($f['status'], STATUS_RUANG, true))    $errors['status']  = 'Status tidak valid.';
    if (mb_strlen($f['keterangan']) > 255) $errors['keterangan'] = 'Keterangan maksimal 255 karakter.';

    if (!$errors) {
        $kap = $f['kapasitas'] === '' ? null : (int) $f['kapasitas'];
        try {
            if ($edit) {
                query('UPDATE ruang SET gedung_id=?, nama=?, kapasitas=?, kondisi=?, status=?, keterangan=? WHERE id=?',
                      [$f['gedung_id'], $f['nama'], $kap, $f['kondisi'], $f['status'], $f['keterangan'] ?: null, $id]);
                catat_log('Mengubah ruang ' . $f['nama'], 'ruang');
                flash_set('ok', 'Data ruang berhasil diperbarui.');
            } else {
                query('INSERT INTO ruang (gedung_id, nama, kapasitas, kondisi, status, keterangan) VALUES (?,?,?,?,?,?)',
                      [$f['gedung_id'], $f['nama'], $kap, $f['kondisi'], $f['status'], $f['keterangan'] ?: null]);
                catat_log('Menambah ruang ' . $f['nama'], 'ruang');
                flash_set('ok', 'Ruang "' . $f['nama'] . '" berhasil ditambahkan.');
            }
            redirect('/modules/sarpras/ruang.php');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $errors['nama'] = 'Nama ruang sudah ada di gedung ini.';
        }
    }
}

$page_title = 'Master Data'; $active = 'master-data';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title"><?= $edit ? 'Ubah Ruang' : 'Tambah Ruang' ?></h2>
  <a class="btn sec sm" href="<?= e($list) ?>">Kembali</a>
</div>

<?php if (!$gedung): ?>
  <div class="alert warn">Belum ada data gedung. <a href="<?= e(url('/modules/sarpras/gedung-form.php')) ?>">Tambahkan gedung</a> terlebih dahulu.</div>
<?php else: ?>
<section class="panel">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="fld">
        <label for="gedung_id">Gedung</label>
        <select class="in<?= isset($errors['gedung_id']) ? ' invalid' : '' ?>" id="gedung_id" name="gedung_id" required>
          <option value="">Pilih gedung</option>
          <?php foreach ($gedung as $g): ?>
            <option value="<?= (int) $g['id'] ?>"<?= $f['gedung_id'] === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= err_msg($errors, 'gedung_id') ?>
      </div>
      <div class="fld">
        <label for="nama">Nama Ruang</label>
        <input class="in<?= isset($errors['nama']) ? ' invalid' : '' ?>" id="nama" name="nama" value="<?= e($f['nama']) ?>" placeholder="Contoh: Ruang Kelas A-204" required>
        <?= err_msg($errors, 'nama') ?>
      </div>
      <div class="fld">
        <label for="kapasitas">Kapasitas <span class="hint">(opsional)</span></label>
        <input class="in<?= isset($errors['kapasitas']) ? ' invalid' : '' ?>" id="kapasitas" name="kapasitas" inputmode="numeric" value="<?= e($f['kapasitas']) ?>" placeholder="Jumlah orang">
        <?= err_msg($errors, 'kapasitas') ?>
      </div>
      <div class="fld">
        <label for="kondisi">Kondisi</label>
        <select class="in" id="kondisi" name="kondisi"><?= opt_enum(KONDISI_GEDUNG, $f['kondisi']) ?></select>
        <?= err_msg($errors, 'kondisi') ?>
      </div>
      <div class="fld">
        <label for="status">Status</label>
        <select class="in" id="status" name="status"><?= opt_enum(STATUS_RUANG, $f['status']) ?></select>
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
      <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Simpan Ruang' ?></button>
    </div>
  </form>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
