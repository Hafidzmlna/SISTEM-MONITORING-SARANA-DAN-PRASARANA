<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
require __DIR__ . '/../../core/charts.php';
require_role(['rektorat']);
require_permission('lihat_dashboard_statistik');
peminjaman_sinkron_waktu();

$totalRuang   = (int) fetch_value('SELECT COUNT(*) FROM ruang');
$ruangPakai   = (int) fetch_value("SELECT COUNT(*) FROM ruang r WHERE r.status = 'dipakai'
                  OR EXISTS (SELECT 1 FROM peminjaman_ruang x WHERE x.ruang_id = r.id AND x.status = 'digunakan')");
$totalBarang  = (int) fetch_value('SELECT COALESCE(SUM(jumlah), 0) FROM barang');
$barangPinjam = (int) fetch_value("SELECT COALESCE(SUM(jumlah), 0) FROM peminjaman_barang WHERE status = 'dipinjam'");
$totalRusak   = (int) fetch_value('SELECT COUNT(*) FROM laporan_kerusakan');
$rusakSelesai = (int) fetch_value("SELECT COUNT(*) FROM laporan_kerusakan WHERE status = 'selesai'");

$kond = bucket_kondisi_barang();
$segs = [['Baik', $kond['baik'], WARNA_BAIK], ['Perbaikan', $kond['perbaikan'], WARNA_PERBAIKAN], ['Rusak', $kond['rusak'], WARNA_RUSAK]];

$aktivitas = fetch_all(
    "(SELECT 'kerusakan' AS tipe, l.nama_barang AS judul, l.lokasi AS sub, p.nama AS siapa, l.status AS st, l.updated_at AS ts
      FROM laporan_kerusakan l JOIN users p ON p.id = l.pelapor_id)
     UNION ALL
     (SELECT 'barang', 'Peminjaman barang', b.nama, p.nama, pb.status, pb.updated_at
      FROM peminjaman_barang pb JOIN users p ON p.id = pb.user_id JOIN barang b ON b.id = pb.barang_id)
     ORDER BY ts DESC LIMIT 6");
$tone = ['diajukan' => ['red', 'alert'], 'diproses' => ['amber', 'wrench'], 'selesai' => ['green', 'check'], 'ditolak' => ['', 'alert']];

$page_title = 'Dashboard'; $active = 'dashboard';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="stats3">
  <?= stat_card('Total Ruang', $totalRuang, 'building') ?>
  <?= stat_card('Ruang Digunakan', $ruangPakai, 'room') ?>
  <?= stat_card('Total Barang', $totalBarang, 'box') ?>
  <?= stat_card('Barang Dipinjam', $barangPinjam, 'swap') ?>
  <?= stat_card('Total Laporan Kerusakan', $totalRusak, 'alert', 'red') ?>
  <?= stat_card('Laporan Selesai', $rusakSelesai, 'check', 'green') ?>
</div>

<div class="dash-grid">
  <section class="cardx">
    <h2>Kondisi Sarana &amp; Prasarana</h2>
    <?= donut_svg($segs, 'Total (Aset)', number_format($totalBarang, 0, ',', '.')) ?>
    <?= legend_html($segs) ?>
  </section>

  <section class="cardx">
    <div class="ph"><h2>Aktivitas Laporan Terbaru</h2>
      <a href="<?= e(url('/modules/rektorat/laporan.php')) ?>">Lihat Semua</a></div>
    <?php foreach ($aktivitas as $a):
        [$warna, $ikon] = $a['tipe'] === 'kerusakan' ? ($tone[$a['st']] ?? ['', 'alert']) : ['', 'swap']; ?>
      <div class="act">
        <div class="ai <?= e($warna) ?>"><?= icon($ikon) ?></div>
        <div class="at"><b><?= e($a['judul']) ?></b><small><?= e($a['sub']) ?> · <?= e($a['siapa']) ?></small></div>
        <span class="tm"><?= e(time_ago($a['ts'])) ?></span>
        <?= $a['tipe'] === 'kerusakan' ? badge_laporan($a['st'], 'kerusakan') : badge($a['st']) ?>
      </div>
    <?php endforeach; if (!$aktivitas): ?>
      <p class="empty">Belum ada aktivitas.</p>
    <?php endif; ?>
  </section>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
