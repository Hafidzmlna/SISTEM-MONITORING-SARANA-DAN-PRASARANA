<?php
require __DIR__ . '/../../core/bootstrap.php';
$me = require_role(['superadmin']);
require_permission('kelola_user_role');

$self = url('/modules/superadmin');

/* ---------- Hapus user (POST) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $target = fetch_one(
        'SELECT u.id, u.nama, r.kode AS role_kode FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?',
        [$id]
    );

    if (!$target) {
        flash_set('bad', 'User tidak ditemukan.');
    } elseif ((int) $target['id'] === (int) $me['id']) {
        flash_set('bad', 'Anda tidak dapat menghapus akun yang sedang digunakan.');
    } elseif ($target['role_kode'] === 'superadmin' && (int) fetch_value(
        "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.kode = 'superadmin'") <= 1) {
        flash_set('bad', 'Superadmin terakhir tidak dapat dihapus.');
    } else {
        try {
            query('DELETE FROM users WHERE id = ?', [$id]);
            catat_log('Menghapus user ' . $target['nama'], 'user');
            flash_set('ok', 'User "' . $target['nama'] . '" berhasil dihapus.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                flash_set('warn', 'User tidak bisa dihapus karena sudah memiliki riwayat laporan/peminjaman. Nonaktifkan saja akunnya.');
            } else {
                throw $e;
            }
        }
    }
    redirect('/modules/superadmin/user.php');
}

/* ---------- Filter & daftar ---------- */
$q       = trim($_GET['q'] ?? '');
$roleF   = (int) ($_GET['role'] ?? 0);
$statusF = $_GET['status'] ?? '';
if (!in_array($statusF, ['aktif', 'nonaktif'], true)) $statusF = '';

$where = []; $params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(u.nim_nip LIKE ? OR u.nama LIKE ? OR u.email LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($roleF)   { $where[] = 'u.role_id = ?'; $params[] = $roleF; }
if ($statusF) { $where[] = 'u.status = ?';  $params[] = $statusF; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) fetch_value("SELECT COUNT(*) FROM users u $w", $params);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT u.id, u.nim_nip, u.nama, u.email, u.no_hp, u.status, r.nama AS role_nama
     FROM users u JOIN roles r ON r.id = u.role_id $w
     ORDER BY u.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);
$roles = fetch_all('SELECT id, nama FROM roles ORDER BY id');
$adaFilter = ($q !== '' || $roleF || $statusF !== '');

$page_title = 'Manajemen User'; $active = 'user';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Manajemen User</h2>
  <div class="head-actions">
    <a class="btn sec sm" href="<?= e($self) ?>/user-import.php">Import Excel</a>
    <a class="btn sm" href="<?= e($self) ?>/user-form.php">+ Tambah User</a>
  </div>
</div>

<section class="panel">
  <form method="get" class="filters">
    <input class="in q" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari NIM/NIP, nama, atau email">
    <select class="in" name="role" data-autosubmit>
      <option value="0">Semua Role</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int) $r['id'] ?>"<?= $roleF === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="in" name="status" data-autosubmit>
      <option value="">Semua Status</option>
      <option value="aktif"<?= $statusF === 'aktif' ? ' selected' : '' ?>>Aktif</option>
      <option value="nonaktif"<?= $statusF === 'nonaktif' ? ' selected' : '' ?>>Nonaktif</option>
    </select>
    <button class="btn sec sm" type="submit">Cari</button>
    <?php if ($adaFilter): ?><a class="reset" href="<?= e($self) ?>/user.php">Reset</a><?php endif; ?>
  </form>

  <div class="tbl-wrap">
    <table class="tbl sa">
      <thead><tr><th>NIM/NIP</th><th>Nama</th><th>Email</th><th>No HP</th><th>Role</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $isMe = (int) $r['id'] === (int) $me['id']; ?>
        <tr>
          <td><?= e($r['nim_nip']) ?></td>
          <td><?= e($r['nama']) ?></td>
          <td><?= e($r['email']) ?></td>
          <td><?= e($r['no_hp'] ?: '-') ?></td>
          <td><?= role_tag($r['role_nama']) ?></td>
          <td><?= status_tag($r['status']) ?></td>
          <td>
            <a class="link-edit" href="<?= e($self) ?>/user-form.php?id=<?= (int) $r['id'] ?>">Edit</a>
            <?php if (!$isMe): ?>
              &nbsp;
              <form method="post" class="inline" data-confirm="Hapus user &quot;<?= e($r['nama']) ?>&quot;? Tindakan ini tidak dapat dibatalkan.">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button type="submit" class="link-del">Hapus</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="7" class="empty"><?= $adaFilter ? 'Tidak ada user yang cocok dengan filter.' : 'Belum ada user.' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= pagination_html($pg, ['q' => $q, 'role' => $roleF, 'status' => $statusF], 'user') ?>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
