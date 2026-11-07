<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/peminjaman.php';
require __DIR__ . '/../../core/charts.php';
require_role(['rektorat']);
require_permission('lihat_data_aset');
peminjaman_sinkron_waktu();

$tab  = ($_GET['tab'] ?? 'ruang') === 'barang' ? 'barang' : 'ruang';
$self = url('/modules/rektorat/monitoring.php');

if ($tab === 'ruang') {
    $kond  = bucket_kondisi_ruang();
    $total = (int) fetch_value('SELECT COUNT(*) FROM ruang');
    $pakai = (int) fetch_value("SELECT COUNT(*) FROM ruang r WHERE r.status = 'dipakai'
                OR EXISTS (SELECT 1 FROM peminjaman_ruang x WHERE x.ruang_id = r.id AND x.status = 'digunakan')");
    $grup = array_map(fn($g) => [$g['nama'], (int) $g['dipakai'], (int) $g['total'] - (int) $g['dipakai']], fetch_all(
        "SELECT g.nama, COUNT(r.id) AS total,
                COALESCE(SUM(CASE WHEN r.status = 'dipakai' OR EXISTS (SELECT 1 FROM peminjaman_ruang x WHERE x.ruang_id = r.id AND x.status = 'digunakan')
                                  THEN 1 ELSE 0 END), 0) AS dipakai
         FROM gedung g LEFT JOIN ruang r ON r.gedung_id = g.id GROUP BY g.id, g.nama ORDER BY g.nama"));
} else {
    $kond  = bucket_kondisi_barang();
    $total = array_sum($kond);
    $pakai = (int) fetch_value("SELECT COALESCE(SUM(jumlah), 0) FROM peminjaman_barang WHERE status = 'dipinjam'");
    $grup = array_map(fn($k) => [$k['nama'], (int) $k['dipinjam'], max(0, (int) $k['total'] - (int) $k['dipinjam'])], fetch_all(
        "SELECT k.nama, COALESCE(SUM(b.jumlah), 0) AS total, COALESCE(SUM(d.n), 0) AS dipinjam
         FROM kategori_barang k
         LEFT JOIN barang b ON b.kategori_id = k.id
         LEFT JOIN (SELECT barang_id, SUM(jumlah) AS n FROM peminjaman_barang WHERE status = 'dipinjam' GROUP BY barang_id) d ON d.barang_id = b.id
         GROUP BY k.id, k.nama ORDER BY k.nama"));
}
$segs = [['Baik', $kond['baik'], WARNA_BAIK], ['Perbaikan', $kond['perbaikan'], WARNA_PERBAIKAN], ['Rusak', $kond['rusak'], WARNA_RUSAK]];
$namaKond = $tab === 'ruang' ? 'Kondisi Ruangan' : 'Kondisi Barang';

$page_title = 'Monitoring'; $active = 'monitoring';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <div>
    <h2 class="title" style="font-size:26px">Monitoring</h2>
    <p class="lead">Pantau penggunaan dan kondisi sarana prasarana</p>
  </div>
  <div class="seg top">
    <a class="<?= $tab === 'ruang' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=ruang">Monitoring Ruangan</a>
    <a class="<?= $tab === 'barang' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=barang">Monitoring Barang</a>
  </div>
</div>

<div class="stats3">
  <?= $tab === 'ruang' ? stat_card('Total Ruangan', $total, 'building') . stat_card('Ruangan Digunakan', $pakai, 'room', 'green')
                       : stat_card('Total Barang', $total, 'box') . stat_card('Total Barang Dipinjam', $pakai, 'swap', 'amber') ?>
  <div class="sc sc-cond"><div>
    <div class="lb"><?= e($namaKond) ?></div>
    <div class="row3">
      <div><small>Baik</small><b style="color:#1E8E5A"><?= (int) $kond['baik'] ?></b></div>
      <div><small>Perbaikan</small><b style="color:#8A5A00"><?= (int) $kond['perbaikan'] ?></b></div>
      <div><small>Rusak</small><b style="color:#B3261E"><?= (int) $kond['rusak'] ?></b></div>
    </div>
  </div></div>
</div>

<div class="grid-eq">
  <section class="cardx">
    <h2><?= $tab === 'ruang' ? 'Statistik Penggunaan Ruangan per Gedung' : 'Statistik Peminjaman Barang per Kategori' ?></h2>
    <?= $tab === 'ruang' ? bars_html($grup, 'Digunakan', 'Kosong') : bars_html($grup, 'Dipinjam', 'Tersedia') ?>
  </section>
  <section class="cardx">
    <h2><?= e($namaKond) ?></h2>
    <div class="donut-row">
      <?= donut_svg($segs, 'Total', number_format((int) $total, 0, ',', '.')) ?>
      <?= legend_html($segs, true) ?>
    </div>
  </section>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
