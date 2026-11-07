<?php
require __DIR__ . '/../../core/bootstrap.php';
require_role(['sarpras']);
require_permission('lihat_dashboard_statistik');

$stat = [
    'Total Barang'      => (int) fetch_value('SELECT COALESCE(SUM(jumlah),0) FROM barang'),
    'Total Ruang'       => (int) fetch_value('SELECT COUNT(*) FROM ruang'),
    'Barang Rusak'      => (int) fetch_value("SELECT COALESCE(SUM(jumlah),0) FROM barang WHERE kondisi = 'rusak'"),
    'Peminjaman Barang' => (int) fetch_value("SELECT COUNT(*) FROM peminjaman_barang WHERE status = 'dipinjam'"),
    'Ruang Terpakai'    => (int) fetch_value("SELECT COUNT(*) FROM ruang WHERE status = 'dipakai'"),
];
$laporan = fetch_all(
    'SELECT nama_barang, lokasi, tanggal_lapor FROM laporan_kerusakan ORDER BY created_at DESC LIMIT 5'
);
$pinjam = fetch_all(
    'SELECT r.nama, pr.tanggal, pr.jam_mulai, pr.jam_selesai
     FROM peminjaman_ruang pr JOIN ruang r ON r.id = pr.ruang_id
     ORDER BY pr.created_at DESC LIMIT 5'
);

$page_title = 'Dashboard Sarpras'; $active = 'dashboard';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="cards">
  <?php foreach ($stat as $label => $val): ?>
    <div class="card-stat"><span><?= e($label) ?></span><strong><?= number_format($val, 0, ',', '.') ?></strong></div>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <section class="panel">
    <div class="ph"><h2>Laporan Kerusakan Terbaru</h2><a href="<?= e(url('/modules/sarpras/laporan-kerusakan.php')) ?>">Lihat semua</a></div>
    <table class="tbl">
      <thead><tr><th>No</th><th>Nama Barang</th><th>Lokasi</th><th>Tanggal</th></tr></thead>
      <tbody>
      <?php foreach ($laporan as $i => $r): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($r['nama_barang']) ?></td><td><?= e($r['lokasi']) ?></td><td><?= e(tgl($r['tanggal_lapor'])) ?></td></tr>
      <?php endforeach; if (!$laporan): ?><tr><td colspan="4" class="empty">Belum ada laporan.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
  <section class="panel">
    <div class="ph"><h2>Laporan Peminjaman Ruang</h2><a href="<?= e(url('/modules/sarpras/laporan-peminjaman-ruang.php')) ?>">Lihat semua</a></div>
    <table class="tbl">
      <thead><tr><th>No</th><th>Nama Ruang</th><th>Tanggal</th><th>Waktu</th></tr></thead>
      <tbody>
      <?php foreach ($pinjam as $i => $r): ?>
        <tr><td><?= $i + 1 ?></td><td><?= e($r['nama']) ?></td><td><?= e(tgl($r['tanggal'])) ?></td>
            <td><?= e(substr($r['jam_mulai'], 0, 5) . ' - ' . substr($r['jam_selesai'], 0, 5)) ?></td></tr>
      <?php endforeach; if (!$pinjam): ?><tr><td colspan="4" class="empty">Belum ada peminjaman.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </section>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
