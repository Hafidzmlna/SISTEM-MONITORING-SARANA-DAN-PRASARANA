<?php
require __DIR__ . '/../../core/bootstrap.php';
require_role(['superadmin']);
require_permission('kelola_user_role');

$jumlahUser = (int) fetch_value('SELECT COUNT(*) FROM users');
$jumlahRole = (int) fetch_value('SELECT COUNT(*) FROM roles');
$perRole = fetch_all(
    'SELECT r.nama, COUNT(u.id) AS total FROM roles r
     LEFT JOIN users u ON u.role_id = r.id GROUP BY r.id, r.nama ORDER BY r.id'
);
$logs = fetch_all(
    'SELECT l.created_at, l.aktivitas, u.nama, r.nama AS role
     FROM log_aktivitas l
     LEFT JOIN users u ON u.id = l.user_id
     LEFT JOIN roles r ON r.id = u.role_id
     ORDER BY l.id DESC LIMIT 7'
);

$page_title = 'Ringkasan'; $active = 'ringkasan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="cards">
  <div class="card-stat"><span>Jumlah User</span><strong><?= $jumlahUser ?></strong></div>
  <div class="card-stat"><span>Jumlah Role</span><strong><?= $jumlahRole ?></strong></div>
  <?php foreach ($perRole as $r): ?>
    <div class="card-stat"><span><?= e($r['nama']) ?></span><strong><?= (int) $r['total'] ?></strong></div>
  <?php endforeach; ?>
</div>

<section class="panel">
  <div class="ph"><h2>Log Aktivitas Terbaru</h2><a href="<?= e(url('/modules/superadmin/log.php')) ?>">Lihat semua</a></div>
  <table class="tbl">
    <thead><tr><th>User</th><th>Role</th><th>Aktivitas</th><th>Waktu</th></tr></thead>
    <tbody>
    <?php foreach ($logs as $l): ?>
      <tr><td><?= e($l['nama'] ?? '-') ?></td><td><?= e($l['role'] ?? '-') ?></td>
          <td><?= e($l['aktivitas']) ?></td><td><?= e(tgl($l['created_at'], 'd/m/Y H:i')) ?></td></tr>
    <?php endforeach; if (!$logs): ?><tr><td colspan="4" class="empty">Belum ada aktivitas.</td></tr><?php endif; ?>
    </tbody>
  </table>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
