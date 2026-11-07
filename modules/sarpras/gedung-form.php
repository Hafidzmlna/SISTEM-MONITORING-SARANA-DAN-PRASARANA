<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';
require_role(['sarpras']);
require_permission('kelola_gedung');

$list = url('/modules/sarpras/gedung.php');
$id   = (int) ($_GET['id'] ?? 0);
$edit = null;
if ($id) {
    $edit = fetch_one('SELECT * FROM gedung WHERE id = ?', [$id]);
    if (!$edit) abort(404, 'Gedung tidak ditemukan.');
}
$f = $edit
    ? ['nama' => $edit['nama'], 'kondisi' => $edit['kondisi'], 'keterangan' => (string) $edit['keterangan']]
    : ['nama' => '', 'kondisi' => 'baik', 'keterangan' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = [
        'nama'       => trim($_POST['nama'] ?? ''),
        'kondisi'    => $_POST['kondisi'] ?? '',
        'keterangan' => trim($_POST['keterangan'] ?? ''),
    ];
    if (mb_strlen($f['nama']) < 2 || mb_strlen($f['nama']) > 100) $errors['nama'] = 'Nama gedung 2–100 karakter.';
    elseif (fetch_value('SELECT id FROM gedung WHERE nama = ? AND id <> ?', [$f['nama'], $id])) $errors['nama'] = 'Nama gedung sudah ada.';
    if (!in_array($f['kondisi'], KONDISI_GEDUNG, true)) $errors['kondisi'] = 'Pilih kondisi yang valid.';
    if (mb_strlen($f['keterangan']) > 255) $errors['keterangan'] = 'Keterangan maksimal 255 karakter.';

    if (!$errors) {
        try {
            if ($edit) {
                query('UPDATE gedung SET nama=?, kondisi=?, keterangan=? WHERE id=?',
                      [$f['nama'], $f['kondisi'], $f['keterangan'] ?: null, $id]);
                catat_log('Mengubah gedung ' . $f['nama'], 'gedung');
                flash_set('ok', 'Data gedung berhasil diperbarui.');
            } else {
                query('INSERT INTO gedung (nama, kondisi, keterangan) VALUES (?,?,?)',
                      [$f['nama'], $f['kondisi'], $f['keterangan'] ?: null]);
                catat_log('Menambah gedung ' . $f['nama'], 'gedung');
                flash_set('ok', 'Gedung "' . $f['nama'] . '" berhasil ditambahkan.');
            }
            redirect('/modules/sarpras/gedung.php');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $errors['nama'] = 'Nama gedung sudah ada.';
        }
    }
}

$page_title = 'Master Data'; $active = 'master-data';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title"><?= $edit ? 'Ubah Gedung' : 'Tambah Gedung' ?></h2>
  <a class="btn sec sm" href="<?= e($list) ?>">Kembali</a>
</div>
<section class="panel">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="fld">
        <label for="nama">Nama Gedung</label>
        <input class="in<?= isset($errors['nama']) ? ' invalid' : '' ?>" id="nama" name="nama" value="<?= e($f['nama']) ?>" placeholder="Contoh: Gedung A" required autofocus>
        <?= err_msg($errors, 'nama') ?>
      </div>
      <div class="fld">
        <label for="kondisi">Kondisi</label>
        <select class="in" id="kondisi" name="kondisi"><?= opt_enum(KONDISI_GEDUNG, $f['kondisi']) ?></select>
        <?= err_msg($errors, 'kondisi') ?>
      </div>
    </div>
    <div class="fld">
      <label for="keterangan">Keterangan <span class="hint">(opsional)</span></label>
      <input class="in<?= isset($errors['keterangan']) ? ' invalid' : '' ?>" id="keterangan" name="keterangan" value="<?= e($f['keterangan']) ?>">
      <?= err_msg($errors, 'keterangan') ?>
    </div>
    <div class="form-actions">
      <a class="btn sec" href="<?= e($list) ?>">Batal</a>
      <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Simpan Gedung' ?></button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
