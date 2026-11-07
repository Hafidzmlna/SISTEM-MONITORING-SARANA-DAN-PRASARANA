<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require_role(['sarpras']);
require_permission('lihat_laporan_mutasi');

$F  = laporan_filter('mutasi', $_GET);
$total = (int) fetch_value("SELECT COUNT(*) {$F['from']} {$F['where']}", $F['params']);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT m.id, m.kode, m.tanggal, m.keterangan, m.status, b.nama AS barang,
            r1.nama AS dari, r2.nama AS ke, p.nama AS pelapor
     {$F['from']} {$F['where']} ORDER BY {$F['order']} LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $F['params']
);
$bisaProses = has_permission('proses_mutasi_barang');
$user = url('/modules/user/laporan-detail.php');
$self = url('/modules/sarpras');

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
laporan_head('mutasi', $F, 'Cari laporan mutasi barang...');
?>
<div class="card0"><div class="tbl-wrap">
  <table class="tbl sa">
    <thead><tr><th>Nama Barang</th><th>Dari</th><th>Ke</th><th>Keterangan</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $final = $r['status'] !== 'diajukan'; ?>
      <tr>
        <td><a class="td-link" href="<?= e("$user?jenis=mutasi&id=" . (int) $r['id']) ?>"><?= e($r['barang']) ?></a>
            <span class="sub"><?= e($r['kode']) ?> · <?= e($r['pelapor']) ?></span></td>
        <td><?= e($r['dari'] ?? 'Belum ditempatkan') ?></td>
        <td><?= e($r['ke']) ?></td>
        <td title="<?= e($r['keterangan']) ?>"><?= e(mb_strimwidth((string) $r['keterangan'], 0, 40, '…')) ?></td>
        <td><?= e(tgl($r['tanggal'])) ?></td>
        <td><?= badge_laporan($r['status'], 'mutasi') ?></td>
        <td><div class="aksi">
          <?php if ($bisaProses): ?>
            <a class="ib<?= $final ? ' off' : '' ?>" title="<?= $final ? 'Laporan sudah final' : 'Proses mutasi' ?>" aria-label="Ubah"
               href="<?= e("$self/laporan-mutasi-ubah.php?id=" . (int) $r['id']) ?>"><?= icon('edit') ?></a>
          <?php endif; ?>
          <a class="ib" title="Unduh laporan" aria-label="Unduh laporan" href="<?= e("$user?jenis=mutasi&id=" . (int) $r['id'] . '&cetak=1') ?>"><?= icon('download') ?></a>
        </div></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="7" class="empty"><?= ($F['q'] !== '' || $F['status'] !== '' || $F['dari'] !== '' || $F['sampai'] !== '') ? 'Tidak ada laporan yang cocok.' : 'Belum ada laporan mutasi barang.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?= laporan_foot('mutasi', $pg, $F) ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
