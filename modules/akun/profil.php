<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';      // err_msg()
require __DIR__ . '/../../core/akun.php';
$me = require_login();

$row = fetch_one('SELECT id, nim_nip, nama, email, no_hp, jabatan, unit_kerja, foto FROM users WHERE id = ?', [$me['id']]);
$self = url('/modules/akun/profil.php');
$edit = isset($_GET['edit']);
$errors = [];
$f = ['nama' => $row['nama'], 'email' => $row['email'], 'no_hp' => (string) $row['no_hp'],
      'jabatan' => (string) $row['jabatan'], 'unit_kerja' => (string) $row['unit_kerja']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'foto') {
        $err = null;
        $baru = simpan_foto_profil($_FILES['foto'] ?? null, $err);
        if ($baru) {
            query('UPDATE users SET foto = ? WHERE id = ?', [$baru, $me['id']]);
            hapus_foto_profil($row['foto']);
            catat_log('Mengganti foto profil', 'akun');
            flash_set('ok', 'Foto profil berhasil diperbarui.');
        } else {
            flash_set('bad', $err);
        }
        redirect('/modules/akun/profil.php');
    }

    if ($aksi === 'simpan') {
        $edit = true;
        $f = [
            'nama' => trim($_POST['nama'] ?? ''), 'email' => strtolower(trim($_POST['email'] ?? '')),
            'no_hp' => trim($_POST['no_hp'] ?? ''), 'jabatan' => trim($_POST['jabatan'] ?? ''), 'unit_kerja' => trim($_POST['unit_kerja'] ?? ''),
        ];
        if (mb_strlen($f['nama']) < 2 || mb_strlen($f['nama']) > 100) $errors['nama'] = 'Nama 2–100 karakter.';
        if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 120) $errors['email'] = 'Format email tidak valid.';
        elseif (fetch_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$f['email'], $me['id']])) $errors['email'] = 'Email sudah digunakan.';
        if ($f['no_hp'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $f['no_hp'])) $errors['no_hp'] = 'Nomor telepon hanya angka (8–20 digit).';
        if (mb_strlen($f['jabatan']) > 100)    $errors['jabatan'] = 'Maksimal 100 karakter.';
        if (mb_strlen($f['unit_kerja']) > 100) $errors['unit_kerja'] = 'Maksimal 100 karakter.';

        if (!$errors) {
            try {
                query('UPDATE users SET nama=?, email=?, no_hp=?, jabatan=?, unit_kerja=? WHERE id=?',
                      [$f['nama'], $f['email'], $f['no_hp'] ?: null, $f['jabatan'] ?: null, $f['unit_kerja'] ?: null, $me['id']]);
                catat_log('Memperbarui profil', 'akun');
                flash_set('ok', 'Profil berhasil diperbarui.');
                redirect('/modules/akun/profil.php');
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                $errors['email'] = 'Email sudah digunakan.';
            }
        }
    }
}

$labelId = $me['role_kode'] === 'mahasiswa' ? 'NIM' : 'NIP';
$page_title = 'Profil'; $active = 'profil';
require __DIR__ . '/../../layouts/header.php';
$kosong = fn($v) => ($v !== null && $v !== '') ? e($v) : '<span class="muted">-</span>';
?>
<div class="page-head">
  <div>
    <h2 class="title" style="font-size:26px">Profil</h2>
    <p class="lead">Informasi akun <?= e($me['role_nama']) ?></p>
  </div>
  <?php if (!$edit): ?><a class="btn" href="<?= e($self) ?>?edit=1"><?= icon('edit') ?>Edit Profil</a><?php endif; ?>
</div>

<div class="prof-grid">
  <section class="cardx prof-side">
    <div class="prof-av">
      <?php if (path_foto_profil($row['foto'])): ?><img alt="Foto profil" src="<?= e(url('/modules/akun/foto.php') . '?v=' . substr($row['foto'], 0, 8)) ?>">
      <?php else: ?><?= e(inisial($row['nama'])) ?><?php endif; ?>
    </div>
    <div class="prof-name"><?= e($row['nama']) ?></div>
    <span class="tag"><?= e($me['role_nama']) ?></span>
    <form method="post" enctype="multipart/form-data" style="margin-top:20px">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="foto">
      <label class="btn sec sm" for="foto-in" style="cursor:pointer">Ganti Foto</label>
      <input id="foto-in" type="file" name="foto" accept="image/png,image/jpeg" hidden data-autosubmit>
      <p class="hint" style="margin:10px 0 0">PNG atau JPG, maksimal 2 MB.</p>
    </form>
  </section>

  <section class="cardx">
    <h2 style="margin-bottom:16px">Data Pribadi</h2>
  <?php if (!$edit): ?>
    <div class="pf">
      <div class="fl">Nama Lengkap<div class="val"><?= $kosong($row['nama']) ?></div></div>
      <div class="fl"><?= e($labelId) ?><div class="val"><?= $kosong($row['nim_nip']) ?></div></div>
      <div class="fl">Email<div class="val"><?= $kosong($row['email']) ?></div></div>
      <div class="fl">Nomor Telepon<div class="val"><?= $kosong($row['no_hp']) ?></div></div>
      <div class="fl">Jabatan<div class="val"><?= $kosong($row['jabatan']) ?></div></div>
      <div class="fl">Unit Kerja<div class="val"><?= $kosong($row['unit_kerja']) ?></div></div>
    </div>
  <?php else: ?>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
      <div class="pf">
        <div class="fl"><label for="nama">Nama Lengkap</label>
          <input class="in<?= isset($errors['nama']) ? ' invalid' : '' ?>" id="nama" name="nama" value="<?= e($f['nama']) ?>" required><?= err_msg($errors, 'nama') ?></div>
        <div class="fl"><?= e($labelId) ?><div class="val"><?= e($row['nim_nip']) ?></div><span class="hint">Hanya dapat diubah oleh administrator.</span></div>
        <div class="fl"><label for="email">Email</label>
          <input class="in<?= isset($errors['email']) ? ' invalid' : '' ?>" id="email" name="email" type="email" value="<?= e($f['email']) ?>" required><?= err_msg($errors, 'email') ?></div>
        <div class="fl"><label for="no_hp">Nomor Telepon</label>
          <input class="in<?= isset($errors['no_hp']) ? ' invalid' : '' ?>" id="no_hp" name="no_hp" value="<?= e($f['no_hp']) ?>" placeholder="08xxxxxxxxxx"><?= err_msg($errors, 'no_hp') ?></div>
        <div class="fl"><label for="jabatan">Jabatan</label>
          <input class="in<?= isset($errors['jabatan']) ? ' invalid' : '' ?>" id="jabatan" name="jabatan" value="<?= e($f['jabatan']) ?>"><?= err_msg($errors, 'jabatan') ?></div>
        <div class="fl"><label for="unit_kerja">Unit Kerja</label>
          <input class="in<?= isset($errors['unit_kerja']) ? ' invalid' : '' ?>" id="unit_kerja" name="unit_kerja" value="<?= e($f['unit_kerja']) ?>"><?= err_msg($errors, 'unit_kerja') ?></div>
      </div>
      <div class="form-actions">
        <a class="btn sec" href="<?= e($self) ?>">Batal</a>
        <button class="btn" type="submit">Simpan Perubahan</button>
      </div>
    </form>
  <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
