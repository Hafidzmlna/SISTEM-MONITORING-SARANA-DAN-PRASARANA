<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
require_role(['rektorat']);

// Urutan tab mengikuti desain; mutasi ditambahkan di akhir karena Rektorat punya hak lihat mutasi.
$urut = ['kerusakan', 'peminjaman_ruang', 'peminjaman_barang', 'mutasi'];
$boleh = array_values(array_filter($urut, fn($j) => has_permission(perm_lihat_laporan($j))));
if (!$boleh) abort(403);
$jenis = $_GET['jenis'] ?? $boleh[0];
if (!in_array($jenis, $boleh, true)) $jenis = $boleh[0];
if (str_starts_with($jenis, 'peminjaman_')) peminjaman_sinkron_waktu();

$F = laporan_filter($jenis, $_GET);
$total = (int) fetch_value("SELECT COUNT(*) {$F['from']} {$F['where']}", $F['params']);
$pg = paginate($total, 8, (int) ($_GET['page'] ?? 1));
$kolom = [
    'kerusakan'         => 'l.id, l.kode, l.nama_barang, l.lokasi, l.tanggal_lapor, l.status, p.nama AS pelapor',
    'peminjaman_ruang'  => 'pr.id, pr.kode, pr.tanggal, pr.jam_mulai, pr.jam_selesai, pr.status, p.nama AS pemohon, r.nama AS ruang, g.nama AS gedung',
    'peminjaman_barang' => 'pb.id, pb.kode, pb.jumlah, pb.tgl_pinjam, pb.tgl_kembali, pb.status, p.nama AS pemohon, b.nama AS barang',
    'mutasi'            => 'm.id, m.kode, m.tanggal, m.status, b.nama AS barang, r1.nama AS dari, r2.nama AS ke, p.nama AS pelapor',
][$jenis];
$rows = fetch_all("SELECT $kolom {$F['from']} {$F['where']} ORDER BY {$F['order']} LIMIT {$pg['per']} OFFSET {$pg['offset']}", $F['params']);
$gedungList = fetch_all('SELECT id, nama FROM gedung ORDER BY nama');

$self   = url('/modules/rektorat/laporan.php');
$detail = fn(int $id) => url('/modules/user/laporan-detail.php') . "?jenis=$jenis&id=$id&cetak=1";
$qs     = array_filter(['jenis' => $jenis, 'q' => $F['q'], 'status' => $F['status'], 'gedung' => $F['gedung'] ?: '', 'dari' => $F['dari'], 'sampai' => $F['sampai']], fn($v) => $v !== '');
$adaFilter = $F['q'] !== '' || $F['status'] !== '' || $F['gedung'] || $F['dari'] !== '' || $F['sampai'] !== '';
$no = $pg['offset'];

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <div>
    <h2 class="title" style="font-size:26px">Laporan</h2>
    <p class="lead">Rekap laporan yang masuk ke sistem</p>
  </div>
  <?php if (has_permission('unduh_rekap_laporan')): ?>
    <a class="btn" href="<?= e(url('/modules/laporan/export.php') . '?' . http_build_query($qs)) ?>"><?= icon('download') ?>Unduh Laporan</a>
  <?php endif; ?>
</div>

<div class="tabs">
  <?php foreach ($boleh as $j): ?>
    <a class="tab<?= $jenis === $j ? ' on' : '' ?>" href="<?= e($self) ?>?jenis=<?= e($j) ?>"><?= e(JUDUL_LAPORAN[$j]) ?></a>
  <?php endforeach; ?>
</div>

<section class="card-pad">
  <form method="get" class="filterbar">
    <input type="hidden" name="jenis" value="<?= e($jenis) ?>">
    <div class="search"><?= icon('search') ?><input type="search" name="q" value="<?= e($F['q']) ?>" placeholder="Cari laporan" aria-label="Cari"></div>
    <select class="in" name="status" data-autosubmit aria-label="Status">
      <option value="">Semua Status</option>
      <?php foreach (STATUS_LAPORAN[$jenis] as $s): ?>
        <option value="<?= e($s) ?>"<?= $F['status'] === $s ? ' selected' : '' ?>><?= e(status_laporan_label($s, $jenis)) ?></option>
      <?php endforeach; ?>
    </select>
    <select class="in" name="gedung" data-autosubmit aria-label="Gedung">
      <option value="0">Semua Gedung</option>
      <?php foreach ($gedungList as $g): ?>
        <option value="<?= (int) $g['id'] ?>"<?= (int) $F['gedung'] === (int) $g['id'] ? ' selected' : '' ?>><?= e($g['nama']) ?></option>
      <?php endforeach; ?>
    </select>
    <input class="in" type="date" name="dari" value="<?= e($F['dari']) ?>" aria-label="Dari tanggal" data-autosubmit>
    <span class="sep">s/d</span>
    <input class="in" type="date" name="sampai" value="<?= e($F['sampai']) ?>" aria-label="Sampai tanggal" data-autosubmit>
    <button class="btn sec sm" type="submit">Cari</button>
    <?php if ($adaFilter): ?><a class="reset" href="<?= e($self) ?>?jenis=<?= e($jenis) ?>">Reset</a><?php endif; ?>
  </form>

  <div class="tbl-wrap"><table class="tbl sa">
  <?php if ($jenis === 'kerusakan'): ?>
    <thead><tr><th>No</th><th>ID Laporan</th><th>Pelapor</th><th>Lokasi / Barang</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= ++$no ?></td><td><b><?= e($r['kode']) ?></b></td><td><?= e($r['pelapor']) ?></td>
          <td><?= e($r['lokasi']) ?><span class="sub"><?= e($r['nama_barang']) ?></span></td>
          <td><?= e(tgl($r['tanggal_lapor'])) ?></td><td><?= badge_laporan($r['status'], 'kerusakan') ?></td>
          <td><a class="btn xs" href="<?= e($detail((int) $r['id'])) ?>"><?= icon('download') ?>Unduh</a></td></tr>
    <?php endforeach; ?>
  <?php elseif ($jenis === 'peminjaman_ruang'): ?>
    <thead><tr><th>No</th><th>ID Peminjaman</th><th>Peminjam</th><th>Ruang</th><th>Tanggal</th><th>Waktu</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= ++$no ?></td><td><b><?= e($r['kode']) ?></b></td><td><?= e($r['pemohon']) ?></td>
          <td><?= e($r['ruang']) ?><span class="sub"><?= e($r['gedung']) ?></span></td>
          <td><?= e(tgl($r['tanggal'])) ?></td><td><?= e(waktu_label($r['jam_mulai'], $r['jam_selesai'])) ?></td>
          <td><?= badge_laporan($r['status'], 'peminjaman_ruang') ?></td>
          <td><a class="btn xs" href="<?= e($detail((int) $r['id'])) ?>"><?= icon('download') ?>Unduh</a></td></tr>
    <?php endforeach; ?>
  <?php elseif ($jenis === 'peminjaman_barang'): ?>
    <thead><tr><th>No</th><th>ID Peminjaman</th><th>Peminjam</th><th>Barang</th><th>Tanggal Pinjam</th><th>Tanggal Kembali</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= ++$no ?></td><td><b><?= e($r['kode']) ?></b></td><td><?= e($r['pemohon']) ?></td>
          <td><?= e($r['barang']) ?><?= (int) $r['jumlah'] > 1 ? ' ×' . (int) $r['jumlah'] : '' ?></td>
          <td><?= e(tgl($r['tgl_pinjam'])) ?></td><td><?= e(tgl($r['tgl_kembali'])) ?></td>
          <td><?= barang_terlambat($r) ? badge('terlambat', 'Terlambat') : badge_laporan($r['status'], 'peminjaman_barang') ?></td>
          <td><a class="btn xs" href="<?= e($detail((int) $r['id'])) ?>"><?= icon('download') ?>Unduh</a></td></tr>
    <?php endforeach; ?>
  <?php else: ?>
    <thead><tr><th>No</th><th>ID Laporan</th><th>Pelapor</th><th>Barang</th><th>Dari → Ke</th><th>Tanggal</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= ++$no ?></td><td><b><?= e($r['kode']) ?></b></td><td><?= e($r['pelapor']) ?></td><td><?= e($r['barang']) ?></td>
          <td><?= e($r['dari'] ?? 'Belum ditempatkan') ?> → <?= e($r['ke']) ?></td>
          <td><?= e(tgl($r['tanggal'])) ?></td><td><?= badge_laporan($r['status'], 'mutasi') ?></td>
          <td><a class="btn xs" href="<?= e($detail((int) $r['id'])) ?>"><?= icon('download') ?>Unduh</a></td></tr>
    <?php endforeach; ?>
  <?php endif; ?>
    <?php if (!$rows): ?>
      <tr><td colspan="8" class="empty"><?= $adaFilter ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table></div>

  <?= pagination_compact($pg, $qs, 'data') ?>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
