<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
$u = require_role(['mahasiswa', 'dosen']);

$bisaRuang  = has_permission('pinjam_ruang');
$bisaBarang = has_permission('pinjam_barang');
$bisaSaya   = has_permission('lihat_riwayat_sendiri');
if (!$bisaRuang && !$bisaBarang && !$bisaSaya) abort(403);

$tabValid = array_keys(array_filter(['ruang' => $bisaRuang, 'barang' => $bisaBarang, 'saya' => $bisaSaya]));
$tab = $_GET['tab'] ?? $tabValid[0];
if (!in_array($tab, $tabValid, true)) $tab = $tabValid[0];

peminjaman_sinkron_waktu();
$self = url('/modules/user/peminjaman.php');

/* ---------- Batalkan pengajuan sendiri ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'batal') {
    csrf_check();
    $j  = ($_POST['jenis'] ?? '') === 'barang' ? 'barang' : 'ruang';
    $id = (int) ($_POST['id'] ?? 0);
    $tbl = $j === 'barang' ? 'peminjaman_barang' : 'peminjaman_ruang';
    $r = fetch_one("SELECT id, kode, status FROM $tbl WHERE id = ? AND user_id = ?", [$id, $u['id']]);
    if (!$r)                          flash_set('bad', 'Pengajuan tidak ditemukan.');
    elseif ($r['status'] !== 'diajukan') flash_set('warn', 'Hanya pengajuan yang masih menunggu yang dapat dibatalkan.');
    else {
        query("UPDATE $tbl SET status = 'ditolak', catatan_sarpras = 'Dibatalkan oleh peminjam' WHERE id = ? AND status = 'diajukan'", [$id]);
        catat_log("Membatalkan pengajuan peminjaman {$r['kode']}", 'peminjaman');
        flash_set('ok', "Pengajuan {$r['kode']} dibatalkan.");
    }
    redirect('/modules/user/peminjaman.php?tab=saya');
}

$q = trim($_GET['q'] ?? '');
$like = '%' . addcslashes($q, '%_\\') . '%';
$detail = fn(string $j, int $id, bool $cetak = false) => url('/modules/user/laporan-detail.php') . "?jenis=$j&id=$id" . ($cetak ? '&cetak=1' : '');
$today = date('Y-m-d');

/* ---------- Data per tab ---------- */
if ($tab === 'ruang') {
    $w = $q !== '' ? 'WHERE (r.nama LIKE ? OR g.nama LIKE ?)' : '';
    $p = $q !== '' ? [$like, $like] : [];
    $total = (int) fetch_value("SELECT COUNT(*) FROM ruang r JOIN gedung g ON g.id = r.gedung_id $w", $p);
    $pg = paginate($total, 10, (int) ($_GET['page'] ?? 1));
    $rows = fetch_all(
        "SELECT r.id, r.nama, r.kapasitas, r.kondisi, g.nama AS gedung,
                (r.status = 'dipakai' OR EXISTS (SELECT 1 FROM peminjaman_ruang x WHERE x.ruang_id = r.id AND x.status = 'digunakan')) AS dipakai
         FROM ruang r JOIN gedung g ON g.id = r.gedung_id $w
         ORDER BY g.nama, r.nama LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
} elseif ($tab === 'barang') {
    $w = "WHERE b.kondisi = 'baik'" . ($q !== '' ? ' AND (b.nama LIKE ? OR b.kode_barang LIKE ? OR r.nama LIKE ? OR g.nama LIKE ?)' : '');
    $p = $q !== '' ? [$like, $like, $like, $like] : [];
    $from = 'FROM barang b LEFT JOIN ruang r ON r.id = b.ruang_id LEFT JOIN gedung g ON g.id = r.gedung_id';
    $total = (int) fetch_value("SELECT COUNT(*) $from $w", $p);
    $pg = paginate($total, 10, (int) ($_GET['page'] ?? 1));
    $rows = fetch_all(
        "SELECT b.id, b.nama, b.kode_barang, b.jumlah, b.kondisi, r.nama AS ruang, g.nama AS gedung,
                GREATEST(0, b.jumlah - COALESCE((SELECT SUM(x.jumlah) FROM peminjaman_barang x WHERE x.barang_id = b.id AND x.status = 'dipinjam'), 0)) AS sisa
         $from $w ORDER BY b.nama LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
} else {
    $kolom = "id, kode, item, tgl1, tgl2, jam1, jam2, keperluan, status, catatan, created_at";
    $sub = "SELECT 'ruang' AS jenis, pr.id, pr.kode, CONCAT(r.nama, ', ', g.nama) AS item, pr.tanggal AS tgl1, NULL AS tgl2,
                   pr.jam_mulai AS jam1, pr.jam_selesai AS jam2, pr.keperluan, pr.status, pr.catatan_sarpras AS catatan, pr.created_at
            FROM peminjaman_ruang pr JOIN ruang r ON r.id = pr.ruang_id JOIN gedung g ON g.id = r.gedung_id WHERE pr.user_id = ?
            UNION ALL
            SELECT 'barang', pb.id, pb.kode, b.nama, pb.tgl_pinjam, pb.tgl_kembali, NULL, NULL, pb.keperluan, pb.status, pb.catatan_sarpras, pb.created_at
            FROM peminjaman_barang pb JOIN barang b ON b.id = pb.barang_id WHERE pb.user_id = ?";
    $w = $q !== '' ? 'WHERE (t.kode LIKE ? OR t.item LIKE ? OR t.keperluan LIKE ?)' : '';
    $p = [$u['id'], $u['id']] + ($q !== '' ? [2 => $like, 3 => $like, 4 => $like] : []);
    $total = (int) fetch_value("SELECT COUNT(*) FROM ($sub) t $w", $p);
    $pg = paginate($total, 10, (int) ($_GET['page'] ?? 1));
    $rows = fetch_all("SELECT t.* FROM ($sub) t $w ORDER BY t.created_at DESC, t.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
}

$judul = ['ruang' => 'Lihat ketersediaan ruangan dan ajukan peminjaman.',
          'barang' => 'Lihat daftar barang dan ajukan peminjaman.',
          'saya' => 'Pantau status pengajuan peminjaman Anda.'];
$holder = ['ruang' => 'Cari nama ruangan', 'barang' => 'Cari nama barang', 'saya' => 'Cari kode atau nama'];

$page_title = 'Peminjaman'; $active = 'peminjaman';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="status-head">
  <div>
    <h2 class="title" style="margin:0;font-size:26px">Peminjaman</h2>
    <p class="lead"><?= e($judul[$tab]) ?></p>
  </div>
  <form method="get" class="search-row">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="search" style="width:340px">
      <?= icon('search') ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($holder[$tab]) ?>" aria-label="Cari">
    </div>
    <button class="btn" type="submit">Cari</button>
  </form>
</div>

<div class="seg">
  <?php if ($bisaRuang): ?><a class="<?= $tab === 'ruang' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=ruang">Peminjaman Ruang</a><?php endif; ?>
  <?php if ($bisaBarang): ?><a class="<?= $tab === 'barang' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=barang">Peminjaman Barang</a><?php endif; ?>
  <?php if ($bisaSaya): ?><a class="<?= $tab === 'saya' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=saya">Peminjaman Saya</a><?php endif; ?>
</div>

<section class="panel"><div class="tbl-wrap">
<?php if ($tab === 'ruang'): ?>
  <table class="tbl">
    <thead><tr><th>Nama Ruangan</th><th>Status</th><th>Tindakan</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $tidak = in_array($r['kondisi'], ['rusak', 'perbaikan'], true); ?>
      <tr>
        <td><?= e($r['nama']) ?><span class="sub"><?= e($r['gedung']) ?><?= $r['kapasitas'] ? ' · kapasitas ' . (int) $r['kapasitas'] : '' ?></span></td>
        <td><?php if ($tidak): ?><?= badge('rusak', 'Tidak tersedia') ?>
            <?php elseif ($r['dipakai']): ?><?= badge('dipakai', 'Dipakai') ?>
            <?php else: ?><?= badge('tersedia', 'Kosong') ?><?php endif; ?></td>
        <td><a class="btn xs<?= $tidak ? ' off' : '' ?>" href="<?= e(url('/modules/user/peminjaman-form.php?jenis=ruang&id=' . (int) $r['id'])) ?>">Pinjam Ruang</a></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="3" class="empty"><?= $q !== '' ? 'Tidak ada ruangan yang cocok.' : 'Belum ada data ruangan.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>

<?php elseif ($tab === 'barang'): ?>
  <table class="tbl">
    <thead><tr><th>Nama Barang</th><th>Lokasi</th><th>Kondisi</th><th>Tindakan</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= e($r['nama']) ?><span class="sub"><?= e(implode(' · ', array_filter([$r['kode_barang'], 'Tersedia ' . (int) $r['sisa'] . ' dari ' . (int) $r['jumlah'] . ' unit']))) ?></span></td>
        <td class="muted-cell"><?= $r['ruang'] ? e($r['gedung'] . ' – ' . $r['ruang']) : 'Belum ditempatkan' ?></td>
        <td><?= badge($r['kondisi']) ?></td>
        <td><a class="btn xs" href="<?= e(url('/modules/user/peminjaman-form.php?jenis=barang&id=' . (int) $r['id'])) ?>">Pinjam Barang</a></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="4" class="empty"><?= $q !== '' ? 'Tidak ada barang yang cocok.' : 'Belum ada barang yang dapat dipinjam.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>

<?php else: ?>
  <table class="tbl">
    <thead><tr><th>ID</th><th>Jenis</th><th>Ruang / Barang</th><th>Waktu</th><th>Keperluan</th><th>Status</th><th>Tindakan</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $telat = $r['jenis'] === 'barang' && $r['status'] === 'dipinjam' && $r['tgl2'] < $today; ?>
      <tr>
        <td><a class="id" href="<?= e($detail('peminjaman_' . $r['jenis'], (int) $r['id'])) ?>"><?= e($r['kode']) ?></a></td>
        <td><?= $r['jenis'] === 'ruang' ? 'Ruang' : 'Barang' ?></td>
        <td><?= e($r['item']) ?></td>
        <td class="muted-cell"><?= $r['jenis'] === 'ruang'
              ? e(tgl($r['tgl1'])) . '<span class="sub">' . e(waktu_label($r['jam1'], $r['jam2'])) . '</span>'
              : e(tgl($r['tgl1'])) . ' – ' . e(tgl($r['tgl2'])) ?></td>
        <td class="desc"><?= e(mb_strimwidth($r['keperluan'], 0, 70, '…')) ?>
            <?php if ($r['status'] === 'ditolak' && $r['catatan']): ?><span class="sub">Catatan: <?= e($r['catatan']) ?></span><?php endif; ?></td>
        <td><?= $telat ? badge('terlambat', 'Terlambat') : badge($r['status']) ?></td>
        <td><div class="aksi">
          <a class="btn xs" href="<?= e($detail('peminjaman_' . $r['jenis'], (int) $r['id'], true)) ?>"><?= icon('download') ?>Unduh</a>
          <?php if ($r['status'] === 'diajukan'): ?>
            <form method="post" class="inline" data-confirm="Batalkan pengajuan <?= e($r['kode']) ?>?">
              <?= csrf_field() ?><input type="hidden" name="aksi" value="batal"><input type="hidden" name="jenis" value="<?= e($r['jenis']) ?>"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
              <button class="btn xs sec" type="submit">Batalkan</button>
            </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; if (!$rows): ?>
      <tr><td colspan="7" class="empty"><?= $q !== '' ? 'Tidak ada pengajuan yang cocok.' : 'Anda belum pernah mengajukan peminjaman.' ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>
<?= pagination_compact($pg, ['tab' => $tab, 'q' => $q]) ?>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
