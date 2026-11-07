<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
$u = require_role(['mahasiswa', 'dosen']);
require_permission('lihat_riwayat_sendiri');

$self = url('/modules/user/status-laporan.php');
$tab  = ($_GET['tab'] ?? 'kerusakan') === 'mutasi' ? 'mutasi' : 'kerusakan';
$q    = trim($_GET['q'] ?? '');

$daftarStatus = $tab === 'kerusakan'
    ? ['diajukan', 'diproses', 'selesai', 'ditolak']
    : ['diajukan', 'disetujui', 'ditolak'];
$st = $_GET['status'] ?? '';
if (!in_array($st, $daftarStatus, true)) $st = '';

$where = []; $params = [];
if ($tab === 'kerusakan') {
    $where[] = 'l.pelapor_id = ?'; $params[] = $u['id'];
    if ($st !== '') { $where[] = 'l.status = ?'; $params[] = $st; }
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(l.kode LIKE ? OR l.nama_barang LIKE ? OR l.lokasi LIKE ? OR l.kategori_fasilitas LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $from = 'FROM laporan_kerusakan l';
    $cols = 'l.id, l.kode, l.nama_barang, l.lokasi, l.kategori_fasilitas, l.deskripsi, l.status';
} else {
    $where[] = 'm.pelapor_id = ?'; $params[] = $u['id'];
    if ($st !== '') { $where[] = 'm.status = ?'; $params[] = $st; }
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(m.kode LIKE ? OR b.nama LIKE ? OR r1.nama LIKE ? OR r2.nama LIKE ?)';
        array_push($params, $like, $like, $like, $like);
    }
    $from = 'FROM laporan_mutasi m
             JOIN barang b ON b.id = m.barang_id
             LEFT JOIN ruang r1 ON r1.id = m.dari_ruang_id
             JOIN ruang r2 ON r2.id = m.ke_ruang_id';
    $cols = 'm.id, m.kode, m.tanggal, m.status, b.nama AS barang, r1.nama AS dari, r2.nama AS ke';
}
$w = 'WHERE ' . implode(' AND ', $where);

$total = (int) fetch_value("SELECT COUNT(*) $from $w", $params);
$pg    = paginate($total, 10, (int) ($_GET['page'] ?? 1));
$rows  = fetch_all("SELECT $cols $from $w ORDER BY " . ($tab === 'kerusakan' ? 'l.id' : 'm.id') . " DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);

$chipLink = fn(string $s) => $self . '?' . http_build_query(array_filter(['tab' => $tab, 'status' => $s, 'q' => $q], fn($v) => $v !== ''));
$detail   = fn(int $id, bool $cetak = false) => url('/modules/user/laporan-detail.php') . '?jenis=' . $tab . '&id=' . $id . ($cetak ? '&cetak=1' : '');

$page_title = 'Status Laporan'; $active = 'status-laporan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="status-head">
  <div>
    <h2 class="title" style="margin:0;font-size:26px">Status Laporan</h2>
    <p class="lead"><?= $tab === 'kerusakan' ? 'Daftar laporan kerusakan yang Anda ajukan.' : 'Daftar laporan mutasi barang yang Anda ajukan.' ?></p>
  </div>
  <form method="get" class="search-row">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <?php if ($st !== ''): ?><input type="hidden" name="status" value="<?= e($st) ?>"><?php endif; ?>
    <div class="search" style="width:340px">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= e($q) ?>" aria-label="Cari"
             placeholder="<?= $tab === 'kerusakan' ? 'Cari ID, fasilitas, atau lokasi' : 'Cari ID, barang, atau ruang' ?>">
    </div>
    <button class="btn" type="submit">Cari</button>
  </form>
</div>

<div class="seg">
  <a class="<?= $tab === 'kerusakan' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=kerusakan">Laporan Kerusakan</a>
  <a class="<?= $tab === 'mutasi' ? 'on' : '' ?>" href="<?= e($self) ?>?tab=mutasi">Laporan Mutasi</a>
</div>

<div class="chips">
  <a class="chip<?= $st === '' ? ' on' : '' ?>" href="<?= e($chipLink('')) ?>">Semua</a>
  <?php foreach ($daftarStatus as $s): ?>
    <a class="chip<?= $st === $s ? ' on' : '' ?>" href="<?= e($chipLink($s)) ?>"><?= e(ucfirst($s)) ?></a>
  <?php endforeach; ?>
</div>

<section class="panel">
  <div class="tbl-wrap">
  <?php if ($tab === 'kerusakan'): ?>
    <table class="tbl">
      <thead><tr><th>ID Laporan</th><th>Pelapor</th><th>Fasilitas</th><th>Lokasi</th><th>Deskripsi</th><th>Status</th><th>Tindakan</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a class="id" href="<?= e($detail((int) $r['id'])) ?>"><?= e($r['kode']) ?></a></td>
          <td><?= e($u['nama']) ?></td>
          <td><?= e(KATEGORI_FASILITAS[$r['kategori_fasilitas']] ?? '-') ?><span class="sub"><?= e($r['nama_barang']) ?></span></td>
          <td class="muted-cell"><?= e($r['lokasi']) ?></td>
          <td class="desc"><?= e(mb_strimwidth($r['deskripsi'], 0, 90, '…')) ?></td>
          <td><?= badge($r['status']) ?></td>
          <td><a class="btn xs" href="<?= e($detail((int) $r['id'], true)) ?>"><?= icon('download') ?>Unduh</a></td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="7" class="empty"><?= ($q !== '' || $st !== '') ? 'Tidak ada laporan yang cocok.' : 'Anda belum pernah mengajukan laporan kerusakan.' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  <?php else: ?>
    <table class="tbl">
      <thead><tr><th>ID Laporan</th><th>Barang</th><th>Dari</th><th>Ke</th><th>Tanggal</th><th>Status</th><th>Tindakan</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><a class="id" href="<?= e($detail((int) $r['id'])) ?>"><?= e($r['kode']) ?></a></td>
          <td><?= e($r['barang']) ?></td>
          <td class="muted-cell"><?= e($r['dari'] ?? 'Belum ditempatkan') ?></td>
          <td class="muted-cell"><?= e($r['ke']) ?></td>
          <td><?= e(tgl($r['tanggal'])) ?></td>
          <td><?= badge($r['status']) ?></td>
          <td><a class="btn xs" href="<?= e($detail((int) $r['id'], true)) ?>"><?= icon('download') ?>Unduh</a></td>
        </tr>
      <?php endforeach; if (!$rows): ?>
        <tr><td colspan="7" class="empty"><?= ($q !== '' || $st !== '') ? 'Tidak ada laporan yang cocok.' : 'Anda belum pernah mengajukan laporan mutasi.' ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  <?php endif; ?>
  </div>
  <?= pagination_compact($pg, ['tab' => $tab, 'status' => $st, 'q' => $q]) ?>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
