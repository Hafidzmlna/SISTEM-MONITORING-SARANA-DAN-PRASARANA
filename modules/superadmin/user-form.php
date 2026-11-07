<?php
require __DIR__ . '/../../core/bootstrap.php';
$me = require_role(['superadmin']);
require_permission('kelola_user_role');

$self = url('/modules/superadmin');
$id   = (int) ($_GET['id'] ?? 0);
$edit = null;
if ($id) {
    $edit = fetch_one('SELECT * FROM users WHERE id = ?', [$id]);
    if (!$edit) abort(404, 'User tidak ditemukan.');
}
$isSelf = $edit && (int) $edit['id'] === (int) $me['id'];
$roles  = fetch_all('SELECT id, kode, nama FROM roles ORDER BY id');

$f = $edit
    ? ['nim_nip' => $edit['nim_nip'], 'nama' => $edit['nama'], 'email' => $edit['email'], 'no_hp' => (string) $edit['no_hp'],
       'role_id' => (int) $edit['role_id'], 'status' => $edit['status']]
    : ['nim_nip' => '', 'nama' => '', 'email' => '', 'no_hp' => '', 'role_id' => 0, 'status' => 'aktif'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $f = [
        'nim_nip' => trim($_POST['nim_nip'] ?? ''),
        'nama'    => trim($_POST['nama'] ?? ''),
        'email'   => strtolower(trim($_POST['email'] ?? '')),
        'no_hp'   => trim($_POST['no_hp'] ?? ''),
        'role_id' => (int) ($_POST['role_id'] ?? 0),
        'status'  => $_POST['status'] ?? 'aktif',
    ];
    $pass = (string) ($_POST['password'] ?? '');

    // Akun sendiri: role & status tidak boleh diubah (cegah mengunci diri)
    if ($isSelf) { $f['role_id'] = (int) $edit['role_id']; $f['status'] = 'aktif'; }

    if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $f['nim_nip']))
        $errors['nim_nip'] = 'NIM/NIP 3–30 karakter: huruf, angka, titik, strip, atau garis bawah.';
    if (mb_strlen($f['nama']) < 2 || mb_strlen($f['nama']) > 100)
        $errors['nama'] = 'Nama wajib diisi (2–100 karakter).';
    if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || strlen($f['email']) > 120)
        $errors['email'] = 'Format email tidak valid.';
    if ($f['no_hp'] !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $f['no_hp']))
        $errors['no_hp'] = 'No HP hanya angka (8–20 digit).';
    if (!in_array($f['role_id'], array_map('intval', array_column($roles, 'id')), true))
        $errors['role_id'] = 'Pilih role yang valid.';
    if (!in_array($f['status'], ['aktif', 'nonaktif'], true))
        $errors['status'] = 'Status tidak valid.';
    if (!$edit || $pass !== '') {
        if (strlen($pass) < 8)  $errors['password'] = 'Kata sandi minimal 8 karakter.';
        elseif (strlen($pass) > 72) $errors['password'] = 'Kata sandi maksimal 72 karakter.';
    }

    $exclude = $edit ? (int) $edit['id'] : 0;
    if (!isset($errors['nim_nip']) && fetch_value('SELECT id FROM users WHERE nim_nip = ? AND id <> ?', [$f['nim_nip'], $exclude]))
        $errors['nim_nip'] = 'NIM/NIP sudah digunakan.';
    if (!isset($errors['email']) && fetch_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$f['email'], $exclude]))
        $errors['email'] = 'Email sudah digunakan.';

    // Jaga minimal satu Superadmin aktif
    if ($edit && !$errors) {
        $superId = (int) fetch_value("SELECT id FROM roles WHERE kode = 'superadmin'");
        $editSuperAktif = (int) $edit['role_id'] === $superId && $edit['status'] === 'aktif';
        $berubah = $f['role_id'] !== (int) $edit['role_id'] || $f['status'] !== 'aktif';
        if ($editSuperAktif && $berubah) {
            $lain = (int) fetch_value(
                "SELECT COUNT(*) FROM users WHERE role_id = ? AND status = 'aktif' AND id <> ?", [$superId, $edit['id']]);
            if ($lain === 0) $errors['role_id'] = 'Superadmin aktif terakhir tidak boleh diubah role atau statusnya.';
        }
    }

    if (!$errors) {
        try {
            if ($edit) {
                $sql = 'UPDATE users SET nim_nip=?, nama=?, email=?, no_hp=?, role_id=?, status=?';
                $par = [$f['nim_nip'], $f['nama'], $f['email'], $f['no_hp'] ?: null, $f['role_id'], $f['status']];
                if ($pass !== '') { $sql .= ', password=?'; $par[] = password_hash($pass, PASSWORD_DEFAULT); }
                $par[] = $edit['id'];
                query($sql . ' WHERE id=?', $par);
                catat_log('Mengubah user ' . $f['nama'], 'user');
                flash_set('ok', 'Data user berhasil diperbarui.');
            } else {
                query(
                    'INSERT INTO users (nim_nip, nama, email, no_hp, password, role_id, status) VALUES (?,?,?,?,?,?,?)',
                    [$f['nim_nip'], $f['nama'], $f['email'], $f['no_hp'] ?: null, password_hash($pass, PASSWORD_DEFAULT), $f['role_id'], $f['status']]
                );
                query('INSERT INTO user_settings (user_id) VALUES (?)', [db()->lastInsertId()]);
                catat_log('Menambah user ' . $f['nama'], 'user');
                flash_set('ok', 'User "' . $f['nama'] . '" berhasil ditambahkan.');
            }
            redirect('/modules/superadmin/user.php');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            $errors['nim_nip'] = 'NIM/NIP atau email sudah digunakan.';
        }
    }
}

$page_title = $edit ? 'Edit User' : 'Tambah User'; $active = 'user';
require __DIR__ . '/../../layouts/header.php';

function field_err(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<span class="err">' . e($errors[$k]) . '</span>' : '';
}
?>
<div class="page-head">
  <h2 class="title"><?= $edit ? 'Edit User' : 'Tambah User' ?></h2>
  <a class="btn sec sm" href="<?= e($self) ?>/user.php">Kembali</a>
</div>

<section class="panel">
  <form method="post" novalidate>
    <?= csrf_field() ?>
    <div class="form-grid">
      <div class="fld">
        <label for="nim_nip">NIM/NIP</label>
        <input class="in<?= isset($errors['nim_nip']) ? ' invalid' : '' ?>" id="nim_nip" name="nim_nip" value="<?= e($f['nim_nip']) ?>" required>
        <?= field_err($errors, 'nim_nip') ?>
      </div>
      <div class="fld">
        <label for="nama">Nama Lengkap</label>
        <input class="in<?= isset($errors['nama']) ? ' invalid' : '' ?>" id="nama" name="nama" value="<?= e($f['nama']) ?>" required>
        <?= field_err($errors, 'nama') ?>
      </div>
      <div class="fld">
        <label for="email">Email</label>
        <input class="in<?= isset($errors['email']) ? ' invalid' : '' ?>" id="email" name="email" type="email" value="<?= e($f['email']) ?>" placeholder="nama@kampus.ac.id" required>
        <?= field_err($errors, 'email') ?>
      </div>
      <div class="fld">
        <label for="no_hp">No HP</label>
        <input class="in<?= isset($errors['no_hp']) ? ' invalid' : '' ?>" id="no_hp" name="no_hp" value="<?= e($f['no_hp']) ?>" placeholder="08xxxxxxxxxx">
        <?= field_err($errors, 'no_hp') ?>
      </div>
      <div class="fld">
        <label for="role_id">Role</label>
        <select class="in<?= isset($errors['role_id']) ? ' invalid' : '' ?>" id="role_id" name="role_id" <?= $isSelf ? 'disabled' : 'required' ?>>
          <option value="">Pilih role</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>"<?= $f['role_id'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nama']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= field_err($errors, 'role_id') ?>
        <?php if ($isSelf): ?><span class="hint">Role akun Anda sendiri tidak dapat diubah.</span><?php endif; ?>
      </div>
      <div class="fld">
        <label for="status">Status</label>
        <select class="in" id="status" name="status" <?= $isSelf ? 'disabled' : '' ?>>
          <option value="aktif"<?= $f['status'] === 'aktif' ? ' selected' : '' ?>>Aktif</option>
          <option value="nonaktif"<?= $f['status'] === 'nonaktif' ? ' selected' : '' ?>>Nonaktif</option>
        </select>
        <?= field_err($errors, 'status') ?>
      </div>
      <div class="fld">
        <label for="password"><?= $edit ? 'Kata Sandi Baru' : 'Kata Sandi' ?></label>
        <input class="in<?= isset($errors['password']) ? ' invalid' : '' ?>" id="password" name="password" type="password" autocomplete="new-password"
               <?= $edit ? '' : 'required' ?>>
        <?= field_err($errors, 'password') ?>
        <span class="hint"><?= $edit ? 'Kosongkan jika tidak ingin mengubah kata sandi.' : 'Minimal 8 karakter.' ?></span>
      </div>
    </div>
    <div class="form-actions">
      <a class="btn sec" href="<?= e($self) ?>/user.php">Batal</a>
      <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Simpan User' ?></button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
