<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/charts.php';
require_role(['rektorat']);
require_permission('lihat_data_aset');

$totalRuang  = (int) fetch_value('SELECT COUNT(*) FROM ruang');
$kond        = bucket_kondisi_barang();
$totalBarang = array_sum($kond);
$gedung = fetch_all(
    'SELECT g.nama, COUNT(DISTINCT r.id) AS ruang, COALESCE(SUM(b.jumlah), 0) AS barang
     FROM gedung g LEFT JOIN ruang r ON r.gedung_id = g.id LEFT JOIN barang b ON b.ruang_id = r.id
     GROUP BY g.id, g.nama ORDER BY g.nama');
$belum = (int) fetch_value('SELECT COALESCE(SUM(jumlah), 0) FROM barang WHERE ruang_id IS NULL');
$baris = [['Baik', $kond['baik'], WARNA_BAIK], ['Perbaikan', $kond['perbaikan'], WARNA_PERBAIKAN], ['Rusak', $kond['rusak'], WARNA_RUSAK]];

$page_title = 'Rekap'; $active = 'rekap';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head"><div>
  <h2 class="title" style="font-size:26px">Rekap</h2>
  <p class="lead">Ringkasan sarana dan prasarana seluruh gedung</p>
</div></div>

<div class="stats4">
  <?= stat_card('Total Ruang', $totalRuang, 'building') ?>
  <?= stat_card('Total Barang', $totalBarang, 'box') ?>
  <?= stat_card('Perlu Perbaikan', $kond['perbaikan'], 'wrench', 'amber') ?>
  <?= stat_card('Rusak Berat', $kond['rusak'], 'alert', 'red') ?>
</div>

<div class="grid-eq">
  <section class="cardx">
    <h2 style="margin-bottom:12px">Rekap Per Gedung</h2>
    <div class="tbl-wrap"><table class="tbl sa">
      <thead><tr><th>Gedung</th><th>Ruangan (jumlah ruang)</th><th>Barang (jumlah barang)</th></tr></thead>
      <tbody>
      <?php foreach ($gedung as $g): ?>
        <tr><td><?= e($g['nama']) ?></td><td><?= (int) $g['ruang'] ?></td><td><?= number_format((int) $g['barang'], 0, ',', '.') ?></td></tr>
      <?php endforeach; if ($belum > 0): ?>
        <tr><td class="muted-cell">Belum ditempatkan</td><td>–</td><td><?= number_format($belum, 0, ',', '.') ?></td></tr>
      <?php endif; if (!$gedung && $belum === 0): ?>
        <tr><td colspan="3" class="empty">Belum ada data.</td></tr>
      <?php endif; ?>
      </tbody>
    </table></div>
  </section>

  <section class="cardx">
    <h2>Kondisi Barang</h2>
    <div class="stack" role="img" aria-label="Komposisi kondisi barang">
      <?php foreach ($baris as [$l, $v, $w]): if ($v > 0): ?><i style="width:<?= round(100 * $v / max(1, $totalBarang), 2) ?>%;background:<?= e($w) ?>"></i><?php endif; endforeach; ?>
    </div>
    <?php foreach ($baris as [$label, $v, $warna]): $pct = $totalBarang ? round(100 * $v / $totalBarang) : 0; ?>
      <div class="rk">
        <div class="lg"><s style="background:<?= e($warna) ?>"></s><span style="flex:1"><?= e($label) ?></span><b><?= number_format((int) $v, 0, ',', '.') ?></b></div>
        <div class="prog"><i style="width:<?= $pct ?>%;background:<?= e($warna) ?>"></i></div>
      </div>
    <?php endforeach; ?>
    <p class="hint">Perbaikan mencakup barang berstatus "perbaikan" dan "rusak ringan"; Rusak = rusak berat.</p>
  </section>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
