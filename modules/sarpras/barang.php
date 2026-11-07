<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';
require_role(['sarpras']);
require_permission('kelola_barang');

$self = url('/modules/sarpras');

/* ---------- Hapus ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    csrf_check();
    $b = fetch_one('SELECT id, nama FROM barang WHERE id = ?', [(int) ($_POST['id'] ?? 0)]);
    if (!$b) {
        flash_set('bad', 'Barang tidak ditemukan.');
    } else {
        try {
            query('DELETE FROM barang WHERE id = ?', [$b['id']]);
            catat_log('Menghapus barang ' . $b['nama'], 'barang');
            flash_set('ok', 'Barang "' . $b['nama'] . '" berhasil dihapus.');
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;
            flash_set('warn', 'Barang tidak bisa dihapus karena sudah memiliki riwayat peminjaman atau mutasi.');
        }
    }
    redirect('/modules/sarpras/barang.php');
}

/* ---------- Daftar ---------- */
$q = trim($_GET['q'] ?? '');
$where = ''; $params = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $where = 'WHERE (b.nama LIKE ? OR b.kode_barang LIKE ? OR k.nama LIKE ? OR r.nama LIKE ? OR g.nama LIKE ?)';
    $params = [$like, $like, $like, $like, $like];
}
$from = 'FROM barang b
         JOIN kategori_barang k ON k.id = b.kategori_id
         LEFT JOIN ruang r ON r.id = b.ruang_id
         LEFT JOIN gedung g ON g.id = r.gedung_id';
$total = (int) fetch_value("SELECT COUNT(*) $from $where", $params);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT b.id, b.kode_barang, b.nama, b.jumlah, b.kondisi, b.status,
            k.nama AS kategori, r.nama AS ruang, g.nama AS gedung
     $from $where ORDER BY b.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params
);

$page_title = 'Master Data'; $active = 'master-data';
require __DIR__ . '/../../layouts/header.php';
master_head('barang', $q, 'Cari data barang...', 'Tambah Barang');
?>
<div class="card0"><div class="tbl-wrap">
  <table class="tbl sa">
    <thead><tr><th>Nama Barang</th><th>Kategori</th><th>Lokasi</th><th>Kondisi</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $sub = array_filter([$r['kode_barang'], (int) $r['jumlah'] > 1 ? (int) $r['jumlah'] . ' unit' : null]); ?>
      <tr>
        <td><?= e($r['nama']) ?><?php if ($sub): ?><span class="sub"><?= e(implode(' · ', $sub)) ?></span><?php endif; ?></td>
        <td><?= e($r['kategori']) ?></td>
        <td><?php if ($r['ruang']): ?><?= e($r['ruang']) ?><span class="sub"><?= e($r['gedung']) ?></span>
            <?php else: ?><span class="muted">Belum ditempatkan</span><?php endif; ?></td>
        <td><?= badge($r['kondisi']) ?></td>
        <td><?= badge($r['status']) ?></td>
        <td><?= aksi_buttons("$self/barang-form.php?id=" . (int) $r['id'], (int) $r['id'], $r['nama']) ?></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6" class="empty"><?= $q !== '' ? 'Tidak ada barang yang cocok.' : 'Belum ada data barang.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?= pagination_compact($pg, ['q' => $q]) ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
