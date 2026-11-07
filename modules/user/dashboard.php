<?php
require __DIR__ . '/../../core/bootstrap.php';
$u = require_role(['mahasiswa', 'dosen']);

$laporan = fetch_all(
    'SELECT kode, nama_barang, lokasi, status FROM laporan_kerusakan
     WHERE pelapor_id = ? ORDER BY created_at DESC LIMIT 6', [$u['id']]
);
$ruang = fetch_all(
    'SELECT r.nama, g.nama AS gedung, r.status FROM ruang r
     JOIN gedung g ON g.id = r.gedung_id ORDER BY r.nama LIMIT 6'
);

$page_title = 'Dashboard'; $active = 'dashboard';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="grid-2">
  <section class="panel">
    <div class="ph"><h2>Laporan Terbaru</h2><a href="<?= e(url('/modules/user/status-laporan.php')) ?>">Lihat semua</a></div>
    <table class="tbl">
      <thead><tr><th>ID</th><th>Fasilitas</th><th>Lokasi</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($laporan as $r): ?>
        <tr><td class="id"><?= e($r['kode']) ?></td><td><?= e($r['nama_barang']) ?></td>
            <td><?= e($r['lokasi']) ?></td><td><?= badge($r['status']) ?></td></tr>
      <?php endforeach; if (!$laporan): ?>
        <tr><td colspan="4" class="empty">Belum ada laporan.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="panel">
    <div class="ph"><h2>Peminjaman Ruang</h2><a href="<?= e(url('/modules/user/peminjaman.php?tab=ruang')) ?>">Lihat semua</a></div>
    <table class="tbl">
      <thead><tr><th>Nama Ruangan</th><th>Gedung</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($ruang as $r): ?>
        <tr><td><?= e($r['nama']) ?></td><td><?= e($r['gedung']) ?></td><td><?= badge($r['status']) ?></td></tr>
      <?php endforeach; if (!$ruang): ?>
        <tr><td colspan="3" class="empty">Belum ada data ruang.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
