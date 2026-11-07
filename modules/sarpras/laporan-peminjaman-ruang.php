<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
require_role(['sarpras']);
require_permission('lihat_laporan_peminjaman');
peminjaman_sinkron_waktu();

$F  = laporan_filter('peminjaman_ruang', $_GET);
$total = (int) fetch_value("SELECT COUNT(*) {$F['from']} {$F['where']}", $F['params']);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all(
    "SELECT pr.id, pr.kode, pr.tanggal, pr.jam_mulai, pr.jam_selesai, pr.keperluan, pr.status,
            p.nama AS pemohon, r.nama AS ruang, g.nama AS gedung
     {$F['from']} {$F['where']} ORDER BY {$F['order']} LIMIT {$pg['per']} OFFSET {$pg['offset']}", $F['params']);
$bisaProses = has_permission('setujui_peminjaman');
$user = url('/modules/user/laporan-detail.php');
$self = url('/modules/sarpras');

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
laporan_head('peminjaman_ruang', $F, 'Cari laporan peminjaman ruang...');
?>
<div class="card0"><div class="tbl-wrap">
  <table class="tbl sa">
    <thead><tr><th>Nama</th><th>Ruang</th><th>Keperluan</th><th>Tanggal</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $final = in_array($r['status'], ['digunakan', 'selesai', 'ditolak'], true); ?>
      <tr>
        <td><a class="td-link" href="<?= e("$user?jenis=peminjaman_ruang&id=" . (int) $r['id']) ?>"><?= e($r['pemohon']) ?></a><span class="sub"><?= e($r['kode']) ?></span></td>
        <td><?= e($r['ruang']) ?><span class="sub"><?= e($r['gedung']) ?></span></td>
        <td title="<?= e($r['keperluan']) ?>"><?= e(mb_strimwidth($r['keperluan'], 0, 36, '…')) ?></td>
        <td><?= e(tgl($r['tanggal'])) ?></td>
        <td><?= e(waktu_label($r['jam_mulai'], $r['jam_selesai'])) ?></td>
        <td><?= badge_laporan($r['status'], 'peminjaman_ruang') ?></td>
        <td><div class="aksi">
          <?php if ($bisaProses): ?>
            <a class="ib<?= $final ? ' off' : '' ?>" title="<?= $final ? 'Sudah final' : 'Proses pengajuan' ?>" aria-label="Ubah"
               href="<?= e("$self/laporan-peminjaman-ruang-ubah.php?id=" . (int) $r['id']) ?>"><?= icon('edit') ?></a>
          <?php endif; ?>
          <a class="ib" title="Unduh laporan" aria-label="Unduh laporan" href="<?= e("$user?jenis=peminjaman_ruang&id=" . (int) $r['id'] . '&cetak=1') ?>"><?= icon('download') ?></a>
        </div></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="7" class="empty"><?= ($F['q'] !== '' || $F['status'] !== '' || $F['dari'] !== '' || $F['sampai'] !== '') ? 'Tidak ada data yang cocok.' : 'Belum ada peminjaman ruang.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div></div>
<?= laporan_foot('peminjaman_ruang', $pg, $F) ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
