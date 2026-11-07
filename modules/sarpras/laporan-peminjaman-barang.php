<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
require_role(['sarpras']);
require_permission('lihat_laporan_peminjaman');

$F  = laporan_filter('peminjaman_barang', $_GET);
$total = (int) fetch_value("SELECT COUNT(*) {$F['from']} {$F['where']}", $F['params']);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT pb.id, pb.kode, pb.jumlah, pb.tgl_pinjam, pb.tgl_kembali, pb.keperluan, pb.status,
            p.nama AS pemohon, b.nama AS barang
     {$F['from']} {$F['where']} ORDER BY {$F['order']} LIMIT {$pg['per']} OFFSET {$pg['offset']}", $F['params']);
$bisaProses = has_permission('setujui_peminjaman');
$user = url('/modules/user/laporan-detail.php');
$self = url('/modules/sarpras');

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
laporan_head('peminjaman_barang', $F, 'Cari laporan peminjaman barang...');
?>
<div class="card0"><div class="tbl-wrap">
  <table class="tbl sa">
    <thead><tr><th>Nama</th><th>Keperluan</th><th>Tanggal Pinjam</th><th>Tanggal Kembali</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $final = in_array($r['status'], ['dikembalikan', 'ditolak'], true); ?>
      <tr>
        <td><a class="td-link" href="<?= e("$user?jenis=peminjaman_barang&id=" . (int) $r['id']) ?>"><?= e($r['pemohon']) ?></a>
            <span class="sub"><?= e($r['kode']) ?> · <?= e($r['barang']) ?><?= (int) $r['jumlah'] > 1 ? ' ×' . (int) $r['jumlah'] : '' ?></span></td>
        <td title="<?= e($r['keperluan']) ?>"><?= e(mb_strimwidth($r['keperluan'], 0, 40, '…')) ?></td>
        <td><?= e(tgl($r['tgl_pinjam'])) ?></td>
        <td><?= e(tgl($r['tgl_kembali'])) ?></td>
        <td><?= barang_terlambat($r) ? badge('terlambat', 'Terlambat') : badge_laporan($r['status'], 'peminjaman_barang') ?></td>
        <td><div class="aksi">
          <?php if ($bisaProses): ?>
            <a class="ib<?= $final ? ' off' : '' ?>" title="<?= $final ? 'Sudah final' : 'Proses pengajuan' ?>" aria-label="Ubah"
               href="<?= e("$self/laporan-peminjaman-barang-ubah.php?id=" . (int) $r['id']) ?>"><?= icon('edit') ?></a>
          <?php endif; ?>
          <a class="ib" title="Unduh laporan" aria-label="Unduh laporan" href="<?= e("$user?jenis=peminjaman_barang&id=" . (int) $r['id'] . '&cetak=1') ?>"><?= icon('download') ?></a>
        </div></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="6" class="empty"><?= ($F['q'] !== '' || $F['status'] !== '' || $F['dari'] !== '' || $F['sampai'] !== '') ? 'Tidak ada data yang cocok.' : 'Belum ada peminjaman barang.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?= laporan_foot('peminjaman_barang', $pg, $F) ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
