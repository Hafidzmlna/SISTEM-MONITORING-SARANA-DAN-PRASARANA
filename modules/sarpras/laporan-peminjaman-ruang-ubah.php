<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';      // err_msg()
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
$me = require_role(['sarpras']);
require_permission('setujui_peminjaman');
peminjaman_sinkron_waktu();

$list = url('/modules/sarpras/laporan-peminjaman-ruang.php');
$id   = (int) ($_GET['id'] ?? 0);
$r = fetch_one(
    'SELECT pr.*, p.nama AS pemohon, p.nim_nip, r.nama AS ruang, r.kondisi AS ruang_kondisi, g.nama AS gedung
     FROM peminjaman_ruang pr
     JOIN users p ON p.id = pr.user_id
     JOIN ruang r ON r.id = pr.ruang_id JOIN gedung g ON g.id = r.gedung_id
     WHERE pr.id = ?', [$id]);
if (!$r) abort(404, 'Data peminjaman tidak ditemukan.');

$alur = ['diajukan' => ['disetujui', 'ditolak'], 'disetujui' => ['ditolak']];
$opsi = $alur[$r['status']] ?? [];
$namaStatus = ['disetujui' => 'Disetujui', 'ditolak' => $r['status'] === 'disetujui' ? 'Ditolak (batalkan persetujuan)' : 'Ditolak'];
$errors = [];
$f = ['status' => $opsi[0] ?? '', 'catatan' => (string) $r['catatan_sarpras']];

// Pratinjau bentrok untuk pengajuan yang menunggu
$bentrok = $r['status'] === 'diajukan'
    ? ruang_bentrok((int) $r['ruang_id'], $r['tanggal'], substr($r['jam_mulai'], 0, 5), substr($r['jam_selesai'], 0, 5), (int) $r['id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$opsi) { flash_set('warn', 'Pengajuan sudah final dan tidak dapat diubah.'); redirect('/modules/sarpras/laporan-peminjaman-ruang.php'); }
    $f = ['status' => $_POST['status'] ?? '', 'catatan' => trim($_POST['catatan'] ?? '')];
    if (!in_array($f['status'], $opsi, true)) $errors['status'] = 'Pilih keputusan yang valid.';
    if (mb_strlen($f['catatan']) > 255) $errors['catatan'] = 'Catatan maksimal 255 karakter.';
    elseif ($f['status'] === 'ditolak' && $f['catatan'] === '') $errors['catatan'] = 'Alasan wajib diisi.';

    if (!$errors) {
        $pdo = db(); $pdo->beginTransaction();
        try {
            fetch_value('SELECT id FROM ruang WHERE id = ? FOR UPDATE', [$r['ruang_id']]);        // serialisasi per ruang
            $sekarang = fetch_value('SELECT status FROM peminjaman_ruang WHERE id = ? FOR UPDATE', [$id]);
            if ($sekarang !== $r['status']) throw new RuntimeException('Status pengajuan sudah berubah. Muat ulang halaman.');

            if ($f['status'] === 'disetujui') {
                if ($r['ruang_kondisi'] !== 'baik') throw new RuntimeException('Ruang sedang tidak berkondisi baik, tidak dapat disetujui.');
                if (date('Y-m-d H:i:s') >= $r['tanggal'] . ' ' . $r['jam_selesai']) throw new RuntimeException('Waktu peminjaman sudah lewat.');
                if ($b = ruang_bentrok((int) $r['ruang_id'], $r['tanggal'], substr($r['jam_mulai'], 0, 5), substr($r['jam_selesai'], 0, 5), $id))
                    throw new RuntimeException('Bentrok dengan peminjaman ' . $b['kode'] . ' (' . waktu_label($b['jam_mulai'], $b['jam_selesai']) . ').');
            }
            query('UPDATE peminjaman_ruang SET status = ?, diproses_oleh = ?, catatan_sarpras = ? WHERE id = ?',
                  [$f['status'], $me['id'], $f['catatan'] ?: null, $id]);
            $pdo->commit();
        } catch (RuntimeException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['status'] = $e->getMessage();
        } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }

        if (!$errors) {
            catat_log("Peminjaman ruang {$r['kode']} ({$r['ruang']}) {$f['status']}", 'peminjaman');
            flash_set('ok', "Peminjaman {$r['kode']} " . ($f['status'] === 'disetujui' ? 'disetujui.' : 'ditolak.'));
            redirect('/modules/sarpras/laporan-peminjaman-ruang.php');
        }
    }
}

$page_title = 'Laporan'; $active = 'laporan';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Proses Peminjaman Ruang – <?= e($r['kode']) ?></h2>
  <a class="btn sec sm" href="<?= e($list) ?>">Kembali</a>
</div>
<div class="card-form"><div class="lap-grid">
  <div class="lap-col">
    <dl class="doc-grid" style="grid-template-columns:140px 1fr">
      <dt>Status saat ini</dt><dd><?= badge_laporan($r['status'], 'peminjaman_ruang') ?></dd>
      <dt>Peminjam</dt><dd><?= e($r['pemohon']) ?> (<?= e($r['nim_nip']) ?>)</dd>
      <dt>Ruang</dt><dd><?= e($r['ruang']) ?>, <?= e($r['gedung']) ?></dd>
      <dt>Tanggal</dt><dd><?= e(tgl($r['tanggal'])) ?></dd>
      <dt>Waktu</dt><dd><?= e(waktu_label($r['jam_mulai'], $r['jam_selesai'])) ?></dd>
      <dt>Keperluan</dt><dd><?= e($r['keperluan']) ?></dd>
      <?php if ($r['catatan_sarpras'] && !$opsi): ?><dt>Catatan</dt><dd><?= e($r['catatan_sarpras']) ?></dd><?php endif; ?>
    </dl>
  </div>
  <div class="lap-col">
  <?php if (!$opsi): ?>
    <div class="note">Pengajuan ini sudah berstatus <b><?= e(ucfirst($r['status'])) ?></b> dan tidak dapat diubah lagi.</div>
  <?php else: ?>
    <?php if ($bentrok): ?>
      <div class="alert warn">Jadwal ini bentrok dengan peminjaman <b><?= e($bentrok['kode']) ?></b> (<?= e(waktu_label($bentrok['jam_mulai'], $bentrok['jam_selesai'])) ?>) yang sudah disetujui. Pengajuan ini tidak dapat disetujui.</div>
    <?php endif; ?>
    <?php if ($r['ruang_kondisi'] !== 'baik'): ?>
      <div class="alert warn">Kondisi ruang saat ini <b><?= e(label_enum($r['ruang_kondisi'])) ?></b>.</div>
    <?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="fld">
        <label for="status">Keputusan</label>
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
