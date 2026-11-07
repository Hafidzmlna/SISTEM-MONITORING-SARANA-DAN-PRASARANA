<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require_role(['sarpras']);
require_permission('lihat_laporan_kerusakan');

$F  = laporan_filter('kerusakan', $_GET);
$total = (int) fetch_value("SELECT COUNT(*) {$F['from']} {$F['where']}", $F['params']);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT l.id, l.kode, l.nama_barang, l.lokasi, l.deskripsi, l.tanggal_lapor, l.status, p.nama AS pelapor
     {$F['from']} {$F['where']} ORDER BY {$F['order']} LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $F['params']
);
$bisaProses = has_permission('proses_laporan_kerusakan');
$user = url('/modules/user/laporan-detail.php');
$self = url('/modules/sarpras');

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
laporan_head('kerusakan', $F, 'Cari laporan kerusakan...');
?>
<div class="card0"><div class="tbl-wrap">
  <table class="tbl sa">
    <thead><tr><th>Nama Barang</th><th>Keterangan</th><th>Lokasi</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $final = in_array($r['status'], ['selesai', 'ditolak'], true); ?>
      <tr>
        <td><a class="td-link" href="<?= e("$user?jenis=kerusakan&id=" . (int) $r['id']) ?>"><?= e($r['nama_barang']) ?></a>
            <span class="sub"><?= e($r['kode']) ?> · <?= e($r['pelapor']) ?></span></td>
        <td title="<?= e($r['deskripsi']) ?>"><?= e(mb_strimwidth($r['deskripsi'], 0, 48, '…')) ?></td>
        <td><?= e($r['lokasi']) ?></td>
        <td><?= e(tgl($r['tanggal_lapor'])) ?></td>
        <td><?= badge_laporan($r['status'], 'kerusakan') ?></td>
        <td><div class="aksi">
          <?php if ($bisaProses): ?>
            <a class="ib<?= $final ? ' off' : '' ?>" title="<?= $final ? 'Laporan sudah final' : 'Ubah status' ?>" aria-label="Ubah"
               href="<?= e("$self/laporan-kerusakan-ubah.php?id=" . (int) $r['id']) ?>"><?= icon('edit') ?></a>
          <?php endif; ?>
          <a class="ib" title="Unduh laporan" aria-label="Unduh laporan" href="<?= e("$user?jenis=kerusakan&id=" . (int) $r['id'] . '&cetak=1') ?>"><?= icon('download') ?></a>
        </div></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6" class="empty"><?= ($F['q'] !== '' || $F['status'] !== '' || $F['dari'] !== '' || $F['sampai'] !== '') ? 'Tidak ada laporan yang cocok.' : 'Belum ada laporan kerusakan.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?= laporan_foot('kerusakan', $pg, $F) ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
