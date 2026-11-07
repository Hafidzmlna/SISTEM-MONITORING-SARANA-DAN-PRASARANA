<?php
require __DIR__ . '/../../core/bootstrap.php';
$me = require_role(['superadmin']);
require_permission('kelola_user_role');

const ROLE_BAWAAN = ['mahasiswa', 'dosen', 'sarpras', 'rektorat', 'superadmin'];
// Permission yang wajib dimiliki Superadmin (cegah terkunci dari sistem)
const PERM_WAJIB_SUPERADMIN = ['kelola_user_role', 'lihat_log_aktivitas'];

$self      = url('/modules/superadmin/role.php');
$permsAll  = fetch_all('SELECT id, kode, label FROM permissions ORDER BY id');
$roles     = fetch_all(
    'SELECT r.id, r.kode, r.nama, COUNT(u.id) AS total FROM roles r
     LEFT JOIN users u ON u.role_id = r.id GROUP BY r.id, r.kode, r.nama ORDER BY r.id'
);
$roleById = [];
foreach ($roles as $r) $roleById[(int) $r['id']] = $r;

$errTambah = [];
$formTambah = ['nama' => '', 'kode' => '', 'salin' => 0];
$showTambah = isset($_GET['tambah']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = $_POST['aksi'] ?? '';

    /* ----- Simpan permission ----- */
    if ($aksi === 'simpan') {
        $rid  = (int) ($_POST['role_id'] ?? 0);
        $role = $roleById[$rid] ?? null;
        if (!$role) { flash_set('bad', 'Role tidak ditemukan.'); redirect('/modules/superadmin/role.php'); }

        $valid = array_map('intval', array_column($permsAll, 'id'));
        $pilih = array_values(array_unique(array_intersect(array_map('intval', (array) ($_POST['perms'] ?? [])), $valid)));
        if ($role['kode'] === 'superadmin') {
            foreach ($permsAll as $p) {
                if (in_array($p['kode'], PERM_WAJIB_SUPERADMIN, true)) $pilih[] = (int) $p['id'];
            }
            $pilih = array_values(array_unique($pilih));
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            query('DELETE FROM role_permissions WHERE role_id = ?', [$rid]);
            $ins = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
            foreach ($pilih as $pid) $ins->execute([$rid, $pid]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

        catat_log('Mengubah permission role ' . $role['nama'] . ' (' . count($pilih) . ' hak akses)', 'role');
        flash_set('ok', 'Permission role "' . $role['nama'] . '" berhasil disimpan.');
        redirect('/modules/superadmin/role.php?role=' . $rid);
    }

    /* ----- Tambah role ----- */
    if ($aksi === 'tambah') {
        $showTambah = true;
        $formTambah = [
            'nama'  => trim($_POST['nama'] ?? ''),
            'kode'  => strtolower(trim($_POST['kode'] ?? '')),
            'salin' => (int) ($_POST['salin'] ?? 0),
        ];
        $kode = $formTambah['kode'] !== '' ? $formTambah['kode'] : strtolower($formTambah['nama']);
        $kode = trim(preg_replace('/[^a-z0-9]+/', '_', $kode), '_');

        if (mb_strlen($formTambah['nama']) < 2 || mb_strlen($formTambah['nama']) > 50) $errTambah['nama'] = 'Nama role 2–50 karakter.';
        if ($kode === '' || strlen($kode) > 30) $errTambah['kode'] = 'Kode role tidak valid (maks. 30 karakter).';
        elseif (fetch_value('SELECT id FROM roles WHERE kode = ?', [$kode])) $errTambah['kode'] = 'Kode role sudah dipakai.';
        if (!$errTambah && fetch_value('SELECT id FROM roles WHERE LOWER(nama) = ?', [mb_strtolower($formTambah['nama'])]))
            $errTambah['nama'] = 'Nama role sudah dipakai.';

        if (!$errTambah) {
            query('INSERT INTO roles (kode, nama) VALUES (?, ?)', [$kode, $formTambah['nama']]);
            $newId = (int) db()->lastInsertId();
            if ($formTambah['salin'] && isset($roleById[$formTambah['salin']])) {
                query('INSERT INTO role_permissions (role_id, permission_id)
                       SELECT ?, permission_id FROM role_permissions WHERE role_id = ?', [$newId, $formTambah['salin']]);
            }
            catat_log('Menambah role ' . $formTambah['nama'], 'role');
            flash_set('ok', 'Role "' . $formTambah['nama'] . '" berhasil dibuat. Atur permission-nya di bawah.');
            redirect('/modules/superadmin/role.php?role=' . $newId);
        }
    }

    /* ----- Hapus role ----- */
    if ($aksi === 'hapus') {
        $rid  = (int) ($_POST['role_id'] ?? 0);
        $role = $roleById[$rid] ?? null;
        if (!$role) flash_set('bad', 'Role tidak ditemukan.');
        elseif (in_array($role['kode'], ROLE_BAWAAN, true)) flash_set('bad', 'Role bawaan sistem tidak dapat dihapus.');
        elseif ((int) $role['total'] > 0) flash_set('bad', 'Role masih dipakai oleh ' . (int) $role['total'] . ' user.');
        else {
            query('DELETE FROM roles WHERE id = ?', [$rid]);
            catat_log('Menghapus role ' . $role['nama'], 'role');
            flash_set('ok', 'Role "' . $role['nama'] . '" berhasil dihapus.');
        }
        redirect('/modules/superadmin/role.php');
    }
}

/* ---------- Role terpilih ---------- */
$selId = (int) ($_GET['role'] ?? 0);
if (!isset($roleById[$selId])) $selId = (int) $roles[0]['id'];
$sel = $roleById[$selId];
$punya = array_map('intval', array_column(
    fetch_all('SELECT permission_id FROM role_permissions WHERE role_id = ?', [$selId]), 'permission_id'));

// Kelompok permission untuk tampilan
$kelompok = [
    'Pelapor & Peminjam'  => ['buat_laporan_kerusakan', 'buat_laporan_mutasi', 'pinjam_barang', 'pinjam_ruang', 'lihat_riwayat_sendiri'],
    'Pengelolaan Sarpras' => ['kelola_gedung', 'kelola_ruang', 'kelola_barang', 'proses_laporan_kerusakan', 'proses_mutasi_barang', 'setujui_peminjaman'],
    'Laporan & Monitoring'=> ['lihat_dashboard_statistik', 'lihat_laporan_kerusakan', 'lihat_laporan_mutasi', 'lihat_laporan_peminjaman', 'lihat_data_aset', 'unduh_rekap_laporan'],
    'Administrasi'        => ['kelola_user_role', 'lihat_log_aktivitas'],
];
$grup = [];
foreach ($permsAll as $p) {
    $nama = 'Lainnya';
    foreach ($kelompok as $g => $kodes) if (in_array($p['kode'], $kodes, true)) { $nama = $g; break; }
    $grup[$nama][] = $p;
}
uksort($grup, fn($a, $b) => (array_search($a, array_keys($kelompok)) ?: 99) <=> (array_search($b, array_keys($kelompok)) ?: 99));
$bisaHapus = !in_array($sel['kode'], ROLE_BAWAAN, true) && (int) $sel['total'] === 0;

$page_title = 'Manajemen Role'; $active = 'role';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Manajemen Role</h2>
  <a class="btn sm" href="<?= e($self) ?>?tambah=1">+ Tambah Role</a>
</div>

<div class="role-layout">
  <section class="panel">
    <div class="ph"><h2>Daftar Role</h2></div>
    <?php foreach ($roles as $r): ?>
      <a class="role-item<?= !$showTambah && (int) $r['id'] === $selId ? ' on' : '' ?>" href="<?= e($self) ?>?role=<?= (int) $r['id'] ?>">
        <b><?= e($r['nama']) ?></b><small><?= (int) $r['total'] ?> user</small>
      </a>
    <?php endforeach; ?>
  </section>

  <?php if ($showTambah): ?>
  <section class="panel">
    <div class="ph"><h2>Tambah Role</h2></div>
    <p class="muted">Role baru belum memiliki dashboard sendiri. Pengguna dengan role ini akan melihat halaman awal sederhana sampai modulnya dibuat.</p>
    <form method="post" novalidate>
      <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
      <div class="fld">
        <label for="nama">Nama Role</label>
        <input class="in<?= isset($errTambah['nama']) ? ' invalid' : '' ?>" id="nama" name="nama" value="<?= e($formTambah['nama']) ?>" required>
        <?php if (isset($errTambah['nama'])): ?><span class="err"><?= e($errTambah['nama']) ?></span><?php endif; ?>
      </div>
      <div class="fld">
        <label for="kode">Kode Role <span class="hint">(opsional, dibuat otomatis dari nama)</span></label>
        <input class="in<?= isset($errTambah['kode']) ? ' invalid' : '' ?>" id="kode" name="kode" value="<?= e($formTambah['kode']) ?>" placeholder="contoh: staf_keuangan">
        <?php if (isset($errTambah['kode'])): ?><span class="err"><?= e($errTambah['kode']) ?></span><?php endif; ?>
      </div>
      <div class="fld">
        <label for="salin">Salin permission dari</label>
        <select class="in" id="salin" name="salin">
          <option value="0">Tidak ada (kosong)</option>
          <?php foreach ($roles as $r): ?>
            <option value="<?= (int) $r['id'] ?>"<?= $formTambah['salin'] === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-actions">
        <a class="btn sec" href="<?= e($self) ?>">Batal</a>
        <button class="btn" type="submit">Simpan Role</button>
      </div>
    </form>
  </section>

  <?php else: ?>
  <section class="panel">
    <div class="ph"><h2>Permission: <?= e($sel['nama']) ?></h2><span class="tag"><?= (int) $sel['total'] ?> user</span></div>
    <p class="muted" style="margin-top:0">Centang hak akses yang diberikan untuk role ini.</p>

    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="role_id" value="<?= $selId ?>">
      <?php foreach ($grup as $nama => $items): ?>
        <div class="perm-title"><?= e($nama) ?></div>
        <?php foreach ($items as $p):
          $wajib = $sel['kode'] === 'superadmin' && in_array($p['kode'], PERM_WAJIB_SUPERADMIN, true);
          $cek   = $wajib || in_array((int) $p['id'], $punya, true); ?>
          <label class="chk<?= $wajib ? ' lock' : '' ?>">
            <input type="checkbox" name="perms[]" value="<?= (int) $p['id'] ?>"<?= $cek ? ' checked' : '' ?><?= $wajib ? ' disabled' : '' ?>>
            <span><?= e($p['label']) ?><?= $wajib ? ' <span class="hint">(wajib untuk Superadmin)</span>' : '' ?></span>
          </label>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <div class="form-actions">
        <a class="btn sec" href="<?= e($self) ?>?role=<?= $selId ?>">Batal</a>
        <button class="btn" type="submit">Simpan Perubahan</button>
      </div>
    </form>

    <?php if ($bisaHapus): ?>
      <form method="post" class="inline" style="margin-top:12px" data-confirm="Hapus role &quot;<?= e($sel['nama']) ?>&quot;?">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="role_id" value="<?= $selId ?>">
        <button type="submit" class="link-del">Hapus role ini</button>
      </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
