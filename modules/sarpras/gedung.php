<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';
require_role(['sarpras']);
require_permission('kelola_gedung');

$self = url('/modules/sarpras');

/* ---------- Hapus ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    $g = fetch_one('SELECT id, nama FROM gedung WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$g) {
        flash_set('bad', 'Gedung tidak ditemukan.');
    } else {
        $n = (int) fetch_value('SELECT COUNT(*) FROM ruang WHERE gedung_id = ?', [$g['id']]);
        if ($n > 0) {
            flash_set('warn', "Gedung \"{$g['nama']}\" masih memiliki {$n} ruang. Pindahkan atau hapus ruangnya terlebih dahulu.");
        } else {
            query('DELETE FROM gedung WHERE id = ?', [$g['id']]);
            catat_log('Menghapus gedung ' . $g['nama'], 'gedung');
            flash_set('ok', 'Gedung "' . $g['nama'] . '" berhasil dihapus.');
        }
    }
    redirect('/modules/sarpras/gedung.php');
}

/* ---------- Daftar ---------- */
$q = trim($_GET['q'] ?? '');
$where = ''; $params = [];
if ($q !== '') { $where = 'WHERE nama LIKE ?'; $params[] = '%' . addcslashes($q, '%_\\') . '%'; }

$total = (int) fetch_value("SELECT COUNT(*) FROM gedung $where", $params);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT id, nama, kondisi FROM gedung $where ORDER BY nama LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params
);

$page_title = 'Master Data'; $active = 'master-data';
require __DIR__ . '/../../layouts/header.php';
master_head('gedung', $q, 'Cari data gedung...', 'Tambah Gedung');
?>
<div class="card0"><div class="tbl-wrap">
  <table class="tbl sa">
    <thead><tr><th>Nama Gedung</th><th>Kondisi</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['nama']) ?></td>
        <td><?= badge($r['kondisi']) ?></td>
        <td><?= aksi_buttons("$self/gedung-form.php?id=" . (int) $r['id'], (int) $r['id'], $r['nama']) ?></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="3" class="empty"><?= $q !== '' ? 'Tidak ada gedung yang cocok.' : 'Belum ada data gedung.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?= pagination_compact($pg, ['q' => $q]) ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
