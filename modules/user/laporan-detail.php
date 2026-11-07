<?php
// Detail + cetak/simpan PDF (lewat dialog cetak browser) untuk laporan kerusakan, mutasi,
// dan peminjaman ruang/barang. Boleh dibuka pemilik atau role yang punya hak lihat.
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
$me = require_login();

$jenis = $_GET['jenis'] ?? '';
if (!isset(STATUS_LAPORAN[$jenis])) $jenis = 'kerusakan';
$id = (int) ($_GET['id'] ?? 0);
if (str_starts_with($jenis, 'peminjaman_')) peminjaman_sinkron_waktu();

$base = 'p.nama AS pelapor, p.nim_nip, ro.nama AS role_pelapor';
if ($jenis === 'kerusakan') {
    $r = fetch_one("SELECT l.*, $base, h.nama AS penangan
        FROM laporan_kerusakan l JOIN users p ON p.id = l.pelapor_id JOIN roles ro ON ro.id = p.role_id
        LEFT JOIN users h ON h.id = l.ditangani_oleh WHERE l.id = ?", [$id]);
} elseif ($jenis === 'mutasi') {
    $r = fetch_one("SELECT m.*, $base, h.nama AS penangan, b.nama AS barang, b.kode_barang,
            CONCAT(r1.nama, ', ', g1.nama) AS dari, CONCAT(r2.nama, ', ', g2.nama) AS ke
        FROM laporan_mutasi m JOIN users p ON p.id = m.pelapor_id JOIN roles ro ON ro.id = p.role_id
        LEFT JOIN users h ON h.id = m.diproses_oleh JOIN barang b ON b.id = m.barang_id
        LEFT JOIN ruang r1 ON r1.id = m.dari_ruang_id LEFT JOIN gedung g1 ON g1.id = r1.gedung_id
        JOIN ruang r2 ON r2.id = m.ke_ruang_id JOIN gedung g2 ON g2.id = r2.gedung_id WHERE m.id = ?", [$id]);
} elseif ($jenis === 'peminjaman_ruang') {
    $r = fetch_one("SELECT pr.*, pr.user_id AS pelapor_id, $base, h.nama AS penangan, r.nama AS ruang, g.nama AS gedung
        FROM peminjaman_ruang pr JOIN users p ON p.id = pr.user_id JOIN roles ro ON ro.id = p.role_id
        LEFT JOIN users h ON h.id = pr.diproses_oleh JOIN ruang r ON r.id = pr.ruang_id JOIN gedung g ON g.id = r.gedung_id
        WHERE pr.id = ?", [$id]);
} else {
    $r = fetch_one("SELECT pb.*, pb.user_id AS pelapor_id, $base, h.nama AS penangan, b.nama AS barang, b.kode_barang
        FROM peminjaman_barang pb JOIN users p ON p.id = pb.user_id JOIN roles ro ON ro.id = p.role_id
        LEFT JOIN users h ON h.id = pb.diproses_oleh JOIN barang b ON b.id = pb.barang_id WHERE pb.id = ?", [$id]);
}
if (!$r) abort(404, 'Data tidak ditemukan.');
if (!boleh_lihat_laporan($jenis, (int) $r['pelapor_id'])) abort(403);

$isUser = in_array($me['role_kode'], ['mahasiswa', 'dosen'], true);
if ($isUser) {
    $kembali = $jenis === 'kerusakan' || $jenis === 'mutasi'
        ? url('/modules/user/status-laporan.php?tab=' . $jenis) : url('/modules/user/peminjaman.php?tab=saya');
} else {
    $kembali = match ($me['role_kode']) {
        'sarpras'  => url('/modules/sarpras/' . SLUG_LAPORAN[$jenis] . '.php'),
        'rektorat' => url('/modules/rektorat/laporan.php?jenis=' . $jenis),
        default    => url('/'),
    };
}
$judulMap = ['kerusakan' => 'Laporan Kerusakan', 'mutasi' => 'Laporan Mutasi Barang',
             'peminjaman_ruang' => 'Peminjaman Ruang', 'peminjaman_barang' => 'Peminjaman Barang'];
$judul = $judulMap[$jenis];
$telat = $jenis === 'peminjaman_barang' && barang_terlambat($r);
$isLaporan = in_array($jenis, ['kerusakan', 'mutasi'], true);

$page_title = $judul . ' ' . $r['kode'];
$active = $isUser ? ($isLaporan ? 'status-laporan' : 'peminjaman') : (in_array($me['role_kode'], ['sarpras', 'rektorat'], true) ? 'laporan' : '');
require __DIR__ . '/../../layouts/header.php';
?>
<div class="print-only"><strong><?= e(APP_NAME) ?></strong> – Sistem Monitoring Sarana dan Prasarana</div>

<div class="page-head no-print">
  <a class="btn sec sm" href="<?= e($kembali) ?>">Kembali</a>
  <button class="btn sm" type="button" onclick="window.print()"><?= icon('download') ?>Cetak / Simpan PDF</button>
</div>

<section class="doc">
  <div class="doc-head">
    <h2><?= e($judul) ?> <?= e($r['kode']) ?></h2>
    <?= $telat ? badge('terlambat', 'Terlambat') : badge_laporan($r['status'], $jenis) ?>
  </div>

  <dl class="doc-grid">
    <dt><?= $isLaporan ? 'Pelapor' : 'Peminjam' ?></dt><dd><?= e($r['pelapor']) ?> (<?= e($r['nim_nip']) ?>) – <?= e($r['role_pelapor']) ?></dd>
    <dt>Dikirim pada</dt><dd><?= e(tgl($r['created_at'], 'd/m/Y H:i')) ?></dd>

  <?php if ($jenis === 'kerusakan'): ?>
    <dt>Tanggal kejadian</dt><dd><?= e(tgl($r['tanggal_lapor'])) ?></dd>
    <dt>Nama barang</dt><dd><?= e($r['nama_barang']) ?></dd>
    <dt>Kategori fasilitas</dt><dd><?= e(KATEGORI_FASILITAS[$r['kategori_fasilitas']] ?? '-') ?></dd>
    <dt>Lokasi</dt><dd><?= e($r['lokasi']) ?></dd>
    <dt>Deskripsi</dt><dd><?= e($r['deskripsi']) ?></dd>
    <?php if (path_foto_laporan($r['foto'])): ?>
      <dt>Foto</dt><dd><img class="doc-foto" alt="Foto kerusakan" src="<?= e(url('/modules/user/laporan-foto.php?id=' . (int) $r['id'])) ?>"></dd>
    <?php endif; ?>
  <?php elseif ($jenis === 'mutasi'): ?>
    <dt>Tanggal mutasi</dt><dd><?= e(tgl($r['tanggal'])) ?></dd>
    <dt>Barang</dt><dd><?= e($r['barang']) ?><?= $r['kode_barang'] ? ' (' . e($r['kode_barang']) . ')' : '' ?></dd>
    <dt>Dari</dt><dd><?= e($r['dari'] ?? 'Belum ditempatkan') ?></dd>
    <dt>Ke</dt><dd><?= e($r['ke']) ?></dd>
    <dt>Keterangan</dt><dd><?= e($r['keterangan']) ?></dd>
  <?php elseif ($jenis === 'peminjaman_ruang'): ?>
    <dt>Ruang</dt><dd><?= e($r['ruang']) ?>, <?= e($r['gedung']) ?></dd>
    <dt>Tanggal</dt><dd><?= e(tgl($r['tanggal'])) ?></dd>
    <dt>Waktu</dt><dd><?= e(waktu_label($r['jam_mulai'], $r['jam_selesai'])) ?></dd>
    <dt>Keperluan</dt><dd><?= e($r['keperluan']) ?></dd>
  <?php else: ?>
    <dt>Barang</dt><dd><?= e($r['barang']) ?><?= $r['kode_barang'] ? ' (' . e($r['kode_barang']) . ')' : '' ?></dd>
    <dt>Jumlah</dt><dd><?= (int) $r['jumlah'] ?> unit</dd>
    <dt>Tanggal pinjam</dt><dd><?= e(tgl($r['tgl_pinjam'])) ?></dd>
    <dt>Tanggal kembali</dt><dd><?= e(tgl($r['tgl_kembali'])) ?></dd>
    <?php if ($r['tgl_dikembalikan']): ?><dt>Dikembalikan pada</dt><dd><?= e(tgl($r['tgl_dikembalikan'])) ?></dd><?php endif; ?>
    <dt>Keperluan</dt><dd><?= e($r['keperluan']) ?></dd>
  <?php endif; ?>

    <dt>Diproses oleh</dt><dd><?= e($r['penangan'] ?? 'Belum ada') ?></dd>
    <?php if ($jenis === 'kerusakan' && $r['tanggal_selesai']): ?><dt>Tanggal selesai</dt><dd><?= e(tgl($r['tanggal_selesai'])) ?></dd><?php endif; ?>
    <?php if ($r['catatan_sarpras']): ?><dt>Catatan Sarpras</dt><dd><?= e($r['catatan_sarpras']) ?></dd><?php endif; ?>
  </dl>
</section>

<?php if (isset($_GET['cetak'])): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
