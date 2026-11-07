<?php
require __DIR__ . '/../../core/bootstrap.php';
require_role(['superadmin']);
require_permission('lihat_log_aktivitas');

$self = url('/modules/superadmin/log.php');

function tanggal_valid(string $s): string
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : '';
}

$q      = trim($_GET['q'] ?? '');
$roleF  = (int) ($_GET['role'] ?? 0);
$dari   = tanggal_valid($_GET['dari'] ?? '');
$sampai = tanggal_valid($_GET['sampai'] ?? '');

$where = []; $params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where[] = '(u.nama LIKE ? OR l.aktivitas LIKE ? OR l.modul LIKE ?)';
    array_push($params, $like, $like, $like);
}
if ($roleF)  { $where[] = 'u.role_id = ?';        $params[] = $roleF; }
if ($dari)   { $where[] = 'l.created_at >= ?';    $params[] = $dari . ' 00:00:00'; }
if ($sampai) { $where[] = 'l.created_at <= ?';    $params[] = $sampai . ' 23:59:59'; }
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from  = 'FROM log_aktivitas l LEFT JOIN users u ON u.id = l.user_id LEFT JOIN roles r ON r.id = u.role_id';
$total = (int) fetch_value("SELECT COUNT(*) $from $w", $params);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT l.created_at, l.aktivitas, l.modul, u.nama AS user_nama, r.nama AS role_nama
     $from $w ORDER BY l.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);
$roles = fetch_all('SELECT id, nama FROM roles ORDER BY id');
$adaFilter = ($q !== '' || $roleF || $dari || $sampai);

$page_title = 'Log Aktivitas'; $active = 'log';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head"><h2 class="title">Log Aktivitas</h2></div>

<section class="panel">
  <form method="get" class="filters">
    <input class="in q" type="search" name="q" value="<?= e($q) ?>" placeholder="Cari user atau aktivitas">
    <select class="in" name="role" data-autosubmit>
      <option value="0">Semua Role</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int) $r['id'] ?>"<?= $roleF === (int) $r['id'] ? ' selected' : '' ?>><?= e($r['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <input class="in" type="date" name="dari" value="<?= e($dari) ?>" aria-label="Dari tanggal" data-autosubmit>
    <span class="muted">s/d</span>
    <input class="in" type="date" name="sampai" value="<?= e($sampai) ?>" aria-label="Sampai tanggal" data-autosubmit>
    <button class="btn sec sm" type="submit">Terapkan</button>
    <?php if ($adaFilter): ?><a class="reset" href="<?= e($self) ?>">Reset</a><?php endif; ?>
  </form>

  <div class="tbl-wrap">
    <table class="tbl sa">
      <thead><tr><th>Waktu</th><th>User</th><th>Role</th><th>Aktivitas</th><th>Modul</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $l): ?>
        <tr>
          <td class="muted-cell"><?= e(tgl($l['created_at'], 'd/m/Y, H:i')) ?></td>
          <td><?= e($l['user_nama'] ?? '-') ?></td>
          <td><?= role_tag($l['role_nama']) ?></td>
          <td><?= e($l['aktivitas']) ?></td>
          <td><?= e($l['modul']) ?></td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="5" class="empty"><?= $adaFilter ? 'Tidak ada log yang cocok dengan filter.' : 'Belum ada aktivitas tercatat.' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?= pagination_html($pg, ['q' => $q, 'role' => $roleF, 'dari' => $dari, 'sampai' => $sampai], 'log') ?>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
