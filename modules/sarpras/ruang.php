<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';
require_role(['sarpras']);
require_permission('kelola_ruang');

$self = url('/modules/sarpras');

/* ---------- Hapus ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    $r = fetch_one('SELECT id, nama FROM ruang WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$r) {
        flash_set('bad', 'Ruang tidak ditemukan.');
    } else {
        $n = (int) fetch_value('SELECT COUNT(*) FROM barang WHERE ruang_id = ?', [$r['id']]);
        if ($n > 0) {
            flash_set('warn', "Ruang \"{$r['nama']}\" masih berisi {$n} data barang. Pindahkan barangnya terlebih dahulu.");
        } else {
            try {
                query('DELETE FROM ruang WHERE id = ?', [$r['id']]);
                catat_log('Menghapus ruang ' . $r['nama'], 'ruang');
                flash_set('ok', 'Ruang "' . $r['nama'] . '" berhasil dihapus.');
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                flash_set('warn', 'Ruang tidak bisa dihapus karena sudah memiliki riwayat peminjaman atau mutasi.');
            }
        }
    }
    redirect('/modules/sarpras/ruang.php');
}

/* ---------- Daftar ---------- */
$q = trim($_GET['q'] ?? '');
$where = ''; $params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where = 'WHERE (r.nama LIKE ? OR g.nama LIKE ?)';
    $params = [$like, $like];
}
$from  = 'FROM ruang r JOIN gedung g ON g.id = r.gedung_id';
$total = (int) fetch_value("SELECT COUNT(*) $from $where", $params);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT r.id, r.nama, r.kapasitas, r.kondisi, r.status, g.nama AS gedung
     $from $where ORDER BY g.nama, r.nama LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params
);

$page_title = 'Master Data'; $active = 'master-data';
require __DIR__ . '/../../layouts/header.php';
master_head('ruang', $q, 'Cari data ruang...', 'Tambah Ruang');
?>
<div class="card0"><div class="tbl-wrap">
  <table class="tbl sa">
    <thead><tr><th>Nama Ruang</th><th>Gedung</th><th>Kondisi</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['nama']) ?><?php if ($r['kapasitas']): ?><span class="sub">Kapasitas <?= (int) $r['kapasitas'] ?> orang</span><?php endif; ?></td>
        <td><?= e($r['gedung']) ?></td>
        <td><?= badge($r['kondisi']) ?></td>
        <td><?= badge($r['status']) ?></td>
        <td><?= aksi_buttons("$self/ruang-form.php?id=" . (int) $r['id'], (int) $r['id'], $r['nama']) ?></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="5" class="empty"><?= $q !== '' ? 'Tidak ada ruang yang cocok.' : 'Belum ada data ruang.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?= pagination_compact($pg, ['q' => $q]) ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
