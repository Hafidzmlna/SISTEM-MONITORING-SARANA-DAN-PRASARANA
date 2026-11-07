<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';      // err_msg(), label_enum()
require __DIR__ . '/../../core/laporan.php';
$me = require_role(['sarpras']);
require_permission('proses_laporan_kerusakan');

$list = url('/modules/sarpras/laporan-kerusakan.php');
$id   = (int) ($_GET['id'] ?? 0);
$r = fetch_one(
    'SELECT l.*, p.nama AS pelapor, p.nim_nip, b.nama AS barang_master, b.kondisi AS barang_kondisi
     FROM laporan_kerusakan l
     JOIN users p ON p.id = l.pelapor_id
     LEFT JOIN barang b ON b.id = l.barang_id
     WHERE l.id = ?', [$id]);
if (!$r) abort(404, 'Laporan tidak ditemukan.');

// Alur status yang diizinkan (selesai & ditolak = final)
$alur = ['diajukan' => ['diproses', 'selesai', 'ditolak'], 'diproses' => ['selesai', 'ditolak']];
$opsi = $alur[$r['status']] ?? [];
$errors = [];
$f = ['status' => $opsi[0] ?? '', 'catatan' => (string) $r['catatan_sarpras'], 'sinkron' => $r['barang_id'] ? 1 : 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$opsi) { flash_set('warn', 'Laporan sudah final dan tidak dapat diubah.'); redirect('/modules/sarpras/laporan-kerusakan.php'); }

    $f = [
        'status'  => $_POST['status'] ?? '',
        'catatan' => trim($_POST['catatan'] ?? ''),
        'sinkron' => !empty($_POST['sinkron']) && $r['barang_id'] ? 1 : 0,
    ];
    if (!in_array($f['status'], $opsi, true)) $errors['status'] = 'Pilih status yang valid.';
    if (mb_strlen($f['catatan']) > 255) $errors['catatan'] = 'Catatan maksimal 255 karakter.';
    elseif ($f['status'] === 'ditolak' && $f['catatan'] === '') $errors['catatan'] = 'Alasan penolakan wajib diisi.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $sekarang = fetch_value('SELECT status FROM laporan_kerusakan WHERE id = ? FOR UPDATE', [$id]);
            if ($sekarang !== $r['status']) {
                $pdo->rollBack();
                flash_set('warn', 'Status laporan sudah diubah sebelumnya. Silakan periksa kembali.');
                redirect('/modules/sarpras/laporan-kerusakan-ubah.php?id=' . $id);
            }
            query(
                'UPDATE laporan_kerusakan
                 SET status = ?, ditangani_oleh = COALESCE(ditangani_oleh, ?), catatan_sarpras = ?, tanggal_selesai = ?
                 WHERE id = ?',
                [$f['status'], $me['id'], $f['catatan'] ?: null, $f['status'] === 'selesai' ? date('Y-m-d') : null, $id]
            );
            if ($f['sinkron']) {
                if ($f['status'] === 'diproses')    query("UPDATE barang SET kondisi = 'perbaikan' WHERE id = ?", [$r['barang_id']]);
                elseif ($f['status'] === 'selesai') query("UPDATE barang SET kondisi = 'baik' WHERE id = ?", [$r['barang_id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        catat_log("Mengubah status laporan {$r['kode']} dari {$r['status']} ke {$f['status']}", 'laporan');
        flash_set('ok', "Status laporan {$r['kode']} diubah menjadi " . ucfirst($f['status']) . '.');
        redirect('/modules/sarpras/laporan-kerusakan.php');
    }
}

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Ubah Status – <?= e($r['kode']) ?></h2>
  <a class="btn sec sm" href="<?= e($list) ?>">Kembali</a>
</div>

<div class="card-form">
  <div class="lap-grid">
    <div class="lap-col">
      <dl class="doc-grid" style="grid-template-columns:140px 1fr">
        <dt>Status saat ini</dt><dd><?= badge_laporan($r['status'], 'kerusakan') ?></dd>
        <dt>Pelapor</dt><dd><?= e($r['pelapor']) ?> (<?= e($r['nim_nip']) ?>)</dd>
        <dt>Tanggal</dt><dd><?= e(tgl($r['tanggal_lapor'])) ?></dd>
        <dt>Barang</dt><dd><?= e($r['nama_barang']) ?></dd>
        <dt>Kategori</dt><dd><?= e(KATEGORI_FASILITAS[$r['kategori_fasilitas']] ?? '-') ?></dd>
        <dt>Lokasi</dt><dd><?= e($r['lokasi']) ?></dd>
        <dt>Deskripsi</dt><dd><?= e($r['deskripsi']) ?></dd>
        <?php if (path_foto_laporan($r['foto'])): ?>
          <dt>Foto</dt><dd><img class="doc-foto" alt="Foto kerusakan" src="<?= e(url('/modules/user/laporan-foto.php?id=' . (int) $r['id'])) ?>"></dd>
        <?php endif; ?>
      </dl>
    </div>

    <div class="lap-col">
      <?php if (!$opsi): ?>
        <div class="note">Laporan ini sudah berstatus <b><?= e(ucfirst($r['status'])) ?></b> dan tidak dapat diubah lagi.</div>
      <?php else: ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="fld">
          <label for="status">Ubah status menjadi</label>
          <select class="in<?= isset($errors['status']) ? ' invalid' : '' ?>" id="status" name="status">
            <?php foreach ($opsi as $s): ?>
              <option value="<?= e($s) ?>"<?= $f['status'] === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
            <?php endforeach; ?>
          </select>
          <?= err_msg($errors, 'status') ?>
        </div>
        <div class="fld">
          <label for="catatan">Catatan <span class="hint">(wajib bila ditolak)</span></label>
          <textarea class="in<?= isset($errors['catatan']) ? ' invalid' : '' ?>" id="catatan" name="catatan" maxlength="255"
                    placeholder="Contoh: Teknisi dijadwalkan hari Kamis"><?= e($f['catatan']) ?></textarea>
          <?= err_msg($errors, 'catatan') ?>
        </div>

        <?php if ($r['barang_id']): ?>
          <label class="chk">
            <input type="checkbox" name="sinkron" value="1"<?= $f['sinkron'] ? ' checked' : '' ?>>
            <span>Perbarui kondisi barang di Master Data
              <span class="sub"><?= e($r['barang_master']) ?> – kondisi sekarang: <?= e(label_enum($r['barang_kondisi'])) ?>.
              Diproses → Perbaikan, Selesai → Baik.</span></span>
          </label>
        <?php else: ?>
          <div class="note">Laporan ini belum terhubung ke data barang di Master Data, jadi kondisi barang tidak diperbarui otomatis.</div>
        <?php endif; ?>

        <div class="form-actions">
          <a class="btn sec" href="<?= e($list) ?>">Batal</a>
          <button class="btn" type="submit">Simpan</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
