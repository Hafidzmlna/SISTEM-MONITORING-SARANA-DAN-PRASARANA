<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';      // err_msg(), label_enum()
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
$me = require_role(['sarpras']);
require_permission('setujui_peminjaman');

$list = url('/modules/sarpras/laporan-peminjaman-barang.php');
$id   = (int) ($_GET['id'] ?? 0);
$r = fetch_one(
    'SELECT pb.*, p.nama AS pemohon, p.nim_nip, b.nama AS barang, b.kode_barang, b.jumlah AS stok_total, b.kondisi AS barang_kondisi
     FROM peminjaman_barang pb
     JOIN users p ON p.id = pb.user_id JOIN barang b ON b.id = pb.barang_id
     WHERE pb.id = ?', [$id]);
if (!$r) abort(404, 'Data peminjaman tidak ditemukan.');

$alur = ['diajukan' => ['disetujui', 'ditolak'], 'disetujui' => ['dipinjam', 'ditolak'], 'dipinjam' => ['dikembalikan']];
$opsi = $alur[$r['status']] ?? [];
$namaStatus = ['disetujui' => 'Disetujui', 'ditolak' => $r['status'] === 'disetujui' ? 'Ditolak (batalkan persetujuan)' : 'Ditolak',
          'dipinjam' => 'Dipinjam (barang sudah diserahkan)', 'dikembalikan' => 'Dikembalikan (barang sudah kembali)'];
$errors = [];
$f = ['status' => $opsi[0] ?? '', 'catatan' => (string) $r['catatan_sarpras']];
$telat = barang_terlambat($r);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$opsi) { flash_set('warn', 'Pengajuan sudah final dan tidak dapat diubah.'); redirect('/modules/sarpras/laporan-peminjaman-barang.php'); }
    $f = ['status' => $_POST['status'] ?? '', 'catatan' => trim($_POST['catatan'] ?? '')];
    if (!in_array($f['status'], $opsi, true)) $errors['status'] = 'Pilih keputusan yang valid.';
    if (mb_strlen($f['catatan']) > 255) $errors['catatan'] = 'Catatan maksimal 255 karakter.';
    elseif ($f['status'] === 'ditolak' && $f['catatan'] === '') $errors['catatan'] = 'Alasan wajib diisi.';

    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            fetch_value('SELECT id FROM barang WHERE id = ? FOR UPDATE', [$r['barang_id']]);       // serialisasi per barang
            $sekarang = fetch_value('SELECT status FROM peminjaman_barang WHERE id = ? FOR UPDATE', [$id]);
            if ($sekarang !== $r['status']) throw new RuntimeException('Status pengajuan sudah berubah. Muat ulang halaman.');

            if ($f['status'] === 'disetujui') {
                $sisa = stok_tersedia((int) $r['barang_id'], $r['tgl_pinjam'], $r['tgl_kembali'], $id);
                if ($sisa < (int) $r['jumlah']) throw new RuntimeException("Stok pada rentang tanggal tersebut tidak cukup (tersisa $sisa unit).");
                if ($r['barang_kondisi'] !== 'baik') throw new RuntimeException('Barang sedang tidak berkondisi baik.');
            }
            if ($f['status'] === 'dipinjam') {
                $bebas = (int) $r['stok_total'] - unit_sedang_dipinjam((int) $r['barang_id'], $id);
                if ($bebas < (int) $r['jumlah']) throw new RuntimeException("Unit belum tersedia secara fisik (bebas $bebas unit). Pastikan peminjam sebelumnya sudah mengembalikan.");
            }
            query('UPDATE peminjaman_barang SET status = ?, diproses_oleh = ?, catatan_sarpras = ?, tgl_dikembalikan = ? WHERE id = ?',
                  [$f['status'], $me['id'], $f['catatan'] ?: null, $f['status'] === 'dikembalikan' ? date('Y-m-d') : null, $id]);
            if (in_array($f['status'], ['dipinjam', 'dikembalikan'], true)) sinkron_status_barang((int) $r['barang_id']);
            $pdo->commit();
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['status'] = $e->getMessage();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

        if (!$errors) {
            catat_log("Peminjaman barang {$r['kode']} ({$r['barang']}) {$f['status']}", 'peminjaman');
            flash_set('ok', "Peminjaman {$r['kode']} diubah menjadi " . ucfirst($f['status']) . '.');
            redirect('/modules/sarpras/laporan-peminjaman-barang.php');
        }
    }
}

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Proses Peminjaman Barang – <?= e($r['kode']) ?></h2>
  <a class="btn sec sm" href="<?= e($list) ?>">Kembali</a>
</div>
<div class="card-form"><div class="lap-grid">
  <div class="lap-col">
    <dl class="doc-grid" style="grid-template-columns:150px 1fr">
      <dt>Status saat ini</dt><dd><?= $telat ? badge('terlambat', 'Terlambat') : badge_laporan($r['status'], 'peminjaman_barang') ?></dd>
      <dt>Peminjam</dt><dd><?= e($r['pemohon']) ?> (<?= e($r['nim_nip']) ?>)</dd>
      <dt>Barang</dt><dd><?= e($r['barang']) ?><?= $r['kode_barang'] ? ' (' . e($r['kode_barang']) . ')' : '' ?></dd>
      <dt>Jumlah</dt><dd><?= (int) $r['jumlah'] ?> unit</dd>
      <dt>Tanggal pinjam</dt><dd><?= e(tgl($r['tgl_pinjam'])) ?></dd>
      <dt>Tanggal kembali</dt><dd><?= e(tgl($r['tgl_kembali'])) ?></dd>
      <?php if ($r['tgl_dikembalikan']): ?><dt>Dikembalikan</dt><dd><?= e(tgl($r['tgl_dikembalikan'])) ?></dd><?php endif; ?>
      <dt>Keperluan</dt><dd><?= e($r['keperluan']) ?></dd>
      <?php if ($r['catatan_sarpras'] && !$opsi): ?><dt>Catatan</dt><dd><?= e($r['catatan_sarpras']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="lap-col">
  <?php if (!$opsi): ?>
    <div class="note">Pengajuan ini sudah berstatus <b><?= e(ucfirst($r['status'])) ?></b> dan tidak dapat diubah lagi.</div>
  <?php else: ?>
    <?php if ($telat): ?><div class="alert bad">Peminjaman ini <b>terlambat</b>: seharusnya dikembalikan <?= e(tgl($r['tgl_kembali'])) ?>.</div><?php endif; ?>
    <?php if ($r['status'] === 'diajukan'): ?>
      <div class="note">Sisa stok pada rentang tanggal ini: <b><?= stok_tersedia((int) $r['barang_id'], $r['tgl_pinjam'], $r['tgl_kembali'], $id) ?></b> dari <?= (int) $r['stok_total'] ?> unit.</div>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="fld">
        <label for="status">Ubah status menjadi</label>
        <select class="in<?= isset($errors['status']) ? ' invalid' : '' ?>" id="status" name="status">
          <?php foreach ($opsi as $s): ?><option value="<?= e($s) ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= e($namaStatus[$s]) ?></option><?php endforeach; ?>
        </select>
        <?= err_msg($errors, 'status') ?>
      </div>
      <div class="fld">
        <label for="catatan">Catatan <span class="hint">(wajib bila ditolak)</span></label>
        <textarea class="in<?= isset($errors['catatan']) ? ' invalid' : '' ?>" id="catatan" name="catatan" maxlength="255" placeholder="Catatan untuk peminjam"><?= e($f['catatan']) ?></textarea>
        <?= err_msg($errors, 'catatan') ?>
      </div>
      <div class="form-actions">
        <a class="btn sec" href="<?= e($list) ?>">Batal</a>
        <button class="btn" type="submit">Simpan</button>
      </div>
    </form>
  <?php endif; ?>
  </div>
</div></div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
