<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';      // err_msg()
require __DIR__ . '/../../core/laporan.php';
$me = require_role(['sarpras']);
require_permission('proses_mutasi_barang');

$list = url('/modules/sarpras/laporan-mutasi.php');
$id   = (int) ($_GET['id'] ?? 0);
$r = fetch_one(
    'SELECT m.*, p.nama AS pelapor, p.nim_nip, b.nama AS barang, b.kode_barang, b.ruang_id AS ruang_sekarang_id,
            CONCAT(r1.nama, \', \', g1.nama) AS dari, CONCAT(r2.nama, \', \', g2.nama) AS ke,
            CONCAT(rs.nama, \', \', gs.nama) AS lokasi_sekarang
     FROM laporan_mutasi m
     JOIN users p ON p.id = m.pelapor_id
     JOIN barang b ON b.id = m.barang_id
     LEFT JOIN ruang r1 ON r1.id = m.dari_ruang_id LEFT JOIN gedung g1 ON g1.id = r1.gedung_id
     JOIN ruang r2 ON r2.id = m.ke_ruang_id JOIN gedung g2 ON g2.id = r2.gedung_id
     LEFT JOIN ruang rs ON rs.id = b.ruang_id LEFT JOIN gedung gs ON gs.id = rs.gedung_id
     WHERE m.id = ?', [$id]);
if (!$r) abort(404, 'Laporan tidak ditemukan.');

$opsi   = $r['status'] === 'diajukan' ? ['disetujui', 'ditolak'] : [];
$errors = [];
$f = ['status' => 'disetujui', 'catatan' => (string) $r['catatan_sarpras']];
$lokasiBerubah = (int) $r['ruang_sekarang_id'] !== (int) $r['dari_ruang_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$opsi) { flash_set('warn', 'Laporan sudah final dan tidak dapat diubah.'); redirect('/modules/sarpras/laporan-mutasi.php'); }

    $f = ['status' => $_POST['status'] ?? '', 'catatan' => trim($_POST['catatan'] ?? '')];
    if (!in_array($f['status'], $opsi, true)) $errors['status'] = 'Pilih keputusan yang valid.';
    if (mb_strlen($f['catatan']) > 255) $errors['catatan'] = 'Catatan maksimal 255 karakter.';
    elseif ($f['status'] === 'ditolak' && $f['catatan'] === '') $errors['catatan'] = 'Alasan penolakan wajib diisi.';

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $sekarang = fetch_value('SELECT status FROM laporan_mutasi WHERE id = ? FOR UPDATE', [$id]);
            if ($sekarang !== 'diajukan') {
                $pdo->rollBack();
                flash_set('warn', 'Laporan ini sudah diproses sebelumnya.');
                redirect('/modules/sarpras/laporan-mutasi.php');
            }
            if ($f['status'] === 'disetujui') {
                if (!fetch_value('SELECT id FROM barang WHERE id = ? FOR UPDATE', [$r['barang_id']])) {
                    throw new RuntimeException('Barang sudah tidak ada di Master Data.');
                }
                query('UPDATE barang SET ruang_id = ? WHERE id = ?', [$r['ke_ruang_id'], $r['barang_id']]);   // barang pindah ruang
            }
            query('UPDATE laporan_mutasi SET status = ?, diproses_oleh = ?, catatan_sarpras = ? WHERE id = ?',
                  [$f['status'], $me['id'], $f['catatan'] ?: null, $id]);
            $pdo->commit();
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['status'] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        if (!$errors) {
            catat_log("Mutasi {$r['kode']} ({$r['barang']}) " . $f['status'] . ($f['status'] === 'disetujui' ? ": dipindahkan ke {$r['ke']}" : ''), 'laporan');
            flash_set('ok', $f['status'] === 'disetujui'
                ? "Mutasi {$r['kode']} disetujui. {$r['barang']} sekarang berada di {$r['ke']}."
                : "Mutasi {$r['kode']} ditolak.");
            redirect('/modules/sarpras/laporan-mutasi.php');
        }
    }
}

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Proses Mutasi – <?= e($r['kode']) ?></h2>
  <a class="btn sec sm" href="<?= e($list) ?>">Kembali</a>
</div>

<div class="card-form">
  <div class="lap-grid">
    <div class="lap-col">
      <dl class="doc-grid" style="grid-template-columns:140px 1fr">
        <dt>Status saat ini</dt><dd><?= badge_laporan($r['status'], 'mutasi') ?></dd>
        <dt>Pelapor</dt><dd><?= e($r['pelapor']) ?> (<?= e($r['nim_nip']) ?>)</dd>
        <dt>Tanggal</dt><dd><?= e(tgl($r['tanggal'])) ?></dd>
        <dt>Barang</dt><dd><?= e($r['barang']) ?><?= $r['kode_barang'] ? ' (' . e($r['kode_barang']) . ')' : '' ?></dd>
        <dt>Dari</dt><dd><?= e($r['dari'] ?? 'Belum ditempatkan') ?></dd>
        <dt>Ke</dt><dd><?= e($r['ke']) ?></dd>
        <dt>Keterangan</dt><dd><?= e($r['keterangan']) ?></dd>
        <?php if ($r['catatan_sarpras'] && !$opsi): ?><dt>Catatan</dt><dd><?= e($r['catatan_sarpras']) ?></dd><?php endif; ?>
      </dl>
    </div>

    <div class="lap-col">
      <?php if (!$opsi): ?>
        <div class="note">Laporan ini sudah berstatus <b><?= e(ucfirst($r['status'])) ?></b> dan tidak dapat diubah lagi.</div>
      <?php else: ?>
        <?php if ($lokasiBerubah): ?>
          <div class="alert warn">Lokasi barang saat ini (<b><?= e($r['lokasi_sekarang'] ?? 'belum ditempatkan') ?></b>) berbeda dari lokasi saat laporan diajukan.
            Jika disetujui, barang tetap dipindahkan ke <b><?= e($r['ke']) ?></b>.</div>
        <?php endif; ?>
        <div class="note">Jika <b>Disetujui</b>, lokasi barang di Master Data otomatis berpindah ke <b><?= e($r['ke']) ?></b>.</div>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="fld">
            <label for="status">Keputusan</label>
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
                      placeholder="Catatan untuk pelapor"><?= e($f['catatan']) ?></textarea>
            <?= err_msg($errors, 'catatan') ?>
          </div>
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
