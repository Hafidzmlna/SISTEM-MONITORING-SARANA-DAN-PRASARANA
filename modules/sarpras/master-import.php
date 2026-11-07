<?php
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/master.php';
require_role(['sarpras']);

const IMPORT_MAX = 1000;

$spec = [
    'gedung' => [
        'judul' => 'Data Gedung', 'perm' => 'kelola_gedung', 'kembali' => 'gedung.php',
        'kolom' => ['nama', 'kondisi', 'keterangan'], 'wajib' => ['nama'],
        'contoh' => [['Gedung D', 'baik', 'Gedung perkuliahan']],
        'catatan' => 'kondisi: baik / perbaikan / rusak (kosong = baik).',
        'alias' => ['nama_gedung' => 'nama'],
    ],
    'ruang' => [
        'judul' => 'Data Ruang', 'perm' => 'kelola_ruang', 'kembali' => 'ruang.php',
        'kolom' => ['gedung', 'nama', 'kapasitas', 'kondisi', 'status', 'keterangan'], 'wajib' => ['gedung', 'nama'],
        'contoh' => [['Gedung A', 'Ruang Kelas A-204', '40', 'baik', 'tersedia', '']],
        'catatan' => 'gedung harus sudah ada (sesuai nama). kondisi: baik / perbaikan / rusak. status: tersedia / dipakai.',
        'alias' => ['nama_gedung' => 'gedung', 'nama_ruang' => 'nama'],
    ],
    'barang' => [
        'judul' => 'Data Barang', 'perm' => 'kelola_barang', 'kembali' => 'barang.php',
        'kolom' => ['kode_barang', 'nama', 'kategori', 'gedung', 'ruang', 'jumlah', 'kondisi', 'status', 'keterangan'], 'wajib' => ['nama', 'kategori'],
        'contoh' => [['INV-ELK-001', 'Proyektor Epson', 'Elektronik', 'Gedung A', 'Ruang Kelas A-204', '1', 'baik', 'tersedia', '']],
        'catatan' => 'kategori harus sudah ada (mis. Elektronik, Furnitur). ruang & gedung boleh kosong (belum ditempatkan); isi gedung bila nama ruang sama di beberapa gedung. kondisi: baik / rusak ringan / perbaikan / rusak. status: tersedia / dipinjam.',
        'alias' => ['nama_barang' => 'nama', 'nama_ruang' => 'ruang', 'nama_gedung' => 'gedung'],
    ],
];

$jenis = $_GET['jenis'] ?? 'barang';
if (!isset($spec[$jenis])) abort(404);
$S = $spec[$jenis];
require_permission($S['perm']);
$back = url('/modules/sarpras/' . $S['kembali']);

/* ---------- Template CSV ---------- */
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template-' . $jenis . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $S['kolom'], ';', '"', '\\');
    foreach ($S['contoh'] as $row) fputcsv($out, $row, ';', '"', '\\');
    fclose($out);
    exit;
}

function master_baca_file(string $tmp, string $ext): array
{
    if ($ext === 'csv') {
        $h = fopen($tmp, 'r');
        $first = (string) fgets($h);
        $d = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        rewind($h);
        $rows = [];
        while (($r = fgetcsv($h, 0, $d, '"', '\\')) !== false) $rows[] = $r;
        fclose($h);
        return $rows;
    }
    $auto = __DIR__ . '/../../vendor/autoload.php';
    if (is_file($auto)) require_once $auto;
    if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
        throw new RuntimeException('File .xlsx membutuhkan PhpSpreadsheet. Jalankan "composer require phpoffice/phpspreadsheet" di folder proyek, atau simpan file sebagai CSV.');
    }
    return \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet()->toArray(null, true, true, false);
}

$norm = fn(string $s) => strtolower(trim(preg_replace('/\s+/', ' ', $s)));
$en   = fn(string $v, string $def) => ($v = str_replace(' ', '_', strtolower(trim($v)))) !== '' ? $v : $def;

$hasil = null; $galat = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_time_limit(120);
    $file = $_FILES['file'] ?? null;
    $pdo  = db();

    try {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Pilih file terlebih dahulu.');
        if ($file['size'] > 2 * 1024 * 1024) throw new RuntimeException('Ukuran file maksimal 2 MB.');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) throw new RuntimeException('Format file harus .csv atau .xlsx.');

        $rows = master_baca_file($file['tmp_name'], $ext);
        if (count($rows) < 2) throw new RuntimeException('File kosong atau hanya berisi judul kolom.');

        $header = array_map(function ($h) use ($S) {
            $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)));
            $h = str_replace([' ', '-'], '_', $h);
            return $S['alias'][$h] ?? $h;
        }, array_shift($rows));
        foreach ($S['wajib'] as $w) {
            if (!in_array($w, $header, true)) throw new RuntimeException("Kolom \"$w\" tidak ditemukan di baris judul.");
        }
        if (count($rows) > IMPORT_MAX) throw new RuntimeException('Maksimal ' . IMPORT_MAX . ' baris per import.');

        // ---- Data pembanding & statement ----
        $gId = [];
        foreach (fetch_all('SELECT id, nama FROM gedung') as $g) $gId[$norm($g['nama'])] = (int) $g['id'];

        if ($jenis === 'gedung') {
            $stmt = $pdo->prepare('INSERT INTO gedung (nama, kondisi, keterangan) VALUES (?,?,?)');
        } elseif ($jenis === 'ruang') {
            $rAda = [];
            foreach (fetch_all('SELECT gedung_id, nama FROM ruang') as $r) $rAda[$r['gedung_id'] . '|' . $norm($r['nama'])] = true;
            $stmt = $pdo->prepare('INSERT INTO ruang (gedung_id, nama, kapasitas, kondisi, status, keterangan) VALUES (?,?,?,?,?,?)');
        } else {
            $kId = [];
            foreach (fetch_all('SELECT id, nama FROM kategori_barang') as $k) $kId[$norm($k['nama'])] = (int) $k['id'];
            $rByNama = [];
            foreach (fetch_all('SELECT id, gedung_id, nama FROM ruang') as $r) $rByNama[$norm($r['nama'])][] = ['id' => (int) $r['id'], 'gid' => (int) $r['gedung_id']];
            $kodeAda = array_flip(array_column(fetch_all('SELECT kode_barang FROM barang WHERE kode_barang IS NOT NULL'), 'kode_barang'));
            $stmt = $pdo->prepare('INSERT INTO barang (kode_barang, nama, kategori_id, ruang_id, jumlah, kondisi, status, keterangan) VALUES (?,?,?,?,?,?,?,?)');
        }

        $ok = 0; $gagal = [];
        $pdo->beginTransaction();

        foreach ($rows as $i => $row) {
            $no = $i + 2;
            $d = [];
            foreach ($header as $k => $nama) $d[$nama] = trim((string) ($row[$k] ?? ''));
            if (implode('', $d) === '') continue;
            $g = fn(string $k) => $d[$k] ?? '';
            $err = null; $label = $g('nama') ?: '-';
            $ket = mb_substr($g('keterangan'), 0, 255) ?: null;

            if ($jenis === 'gedung') {
                $kond = $en($g('kondisi'), 'baik');
                if (mb_strlen($g('nama')) < 2 || mb_strlen($g('nama')) > 100) $err = 'Nama gedung 2–100 karakter';
                elseif (!in_array($kond, KONDISI_GEDUNG, true))                $err = 'Kondisi tidak valid';
                elseif (isset($gId[$norm($g('nama'))]))                        $err = 'Gedung sudah ada';
                if (!$err) {
                    $stmt->execute([$g('nama'), $kond, $ket]);
                    $gId[$norm($g('nama'))] = (int) $pdo->lastInsertId();
                }
            } elseif ($jenis === 'ruang') {
                $gid  = $gId[$norm($g('gedung'))] ?? null;
                $kond = $en($g('kondisi'), 'baik'); $st = $en($g('status'), 'tersedia');
                $kap  = $g('kapasitas');
                if (!$gid)                                                      $err = 'Gedung "' . $g('gedung') . '" tidak ditemukan';
                elseif ($g('nama') === '' || mb_strlen($g('nama')) > 100)       $err = 'Nama ruang wajib (maks 100 karakter)';
                elseif ($kap !== '' && (!ctype_digit($kap) || (int) $kap < 1 || (int) $kap > 10000)) $err = 'Kapasitas harus angka 1–10000';
                elseif (!in_array($kond, KONDISI_GEDUNG, true))                 $err = 'Kondisi tidak valid';
                elseif (!in_array($st, STATUS_RUANG, true))                     $err = 'Status tidak valid';
                elseif (isset($rAda[$gid . '|' . $norm($g('nama'))]))           $err = 'Ruang sudah ada di gedung ini';
                if (!$err) {
                    $stmt->execute([$gid, $g('nama'), $kap === '' ? null : (int) $kap, $kond, $st, $ket]);
                    $rAda[$gid . '|' . $norm($g('nama'))] = true;
                }
            } else {
                $kid  = $kId[$norm($g('kategori'))] ?? null;
                $kond = $en($g('kondisi'), 'baik'); $st = $en($g('status'), 'tersedia');
                $jml  = $g('jumlah') === '' ? '1' : $g('jumlah');
                $kode = $g('kode_barang');
                $ruangId = null;
                if ($g('ruang') !== '') {
                    $cands = $rByNama[$norm($g('ruang'))] ?? [];
                    if ($g('gedung') !== '') {
                        $gid = $gId[$norm($g('gedung'))] ?? null;
                        $cands = array_values(array_filter($cands, fn($c) => $c['gid'] === $gid));
                    }
                    if (count($cands) === 1) $ruangId = $cands[0]['id'];
                    elseif (!$cands)         $err = 'Ruang "' . $g('ruang') . '" tidak ditemukan';
                    else                     $err = 'Nama ruang ada di beberapa gedung, isi kolom gedung';
                }
                if (!$err) {
                    if (mb_strlen($g('nama')) < 2 || mb_strlen($g('nama')) > 120)  $err = 'Nama barang 2–120 karakter';
                    elseif (!$kid)                                                 $err = 'Kategori "' . $g('kategori') . '" tidak ditemukan';
                    elseif (!ctype_digit($jml) || (int) $jml < 1 || (int) $jml > 100000) $err = 'Jumlah harus angka 1–100000';
                    elseif (!in_array($kond, KONDISI_BARANG, true))                $err = 'Kondisi tidak valid';
                    elseif (!in_array($st, STATUS_BARANG, true))                   $err = 'Status tidak valid';
                    elseif ($kode !== '' && !preg_match('#^[A-Za-z0-9._/-]{2,30}$#', $kode)) $err = 'Format kode barang tidak valid';
                    elseif ($kode !== '' && isset($kodeAda[$kode]))                $err = 'Kode barang sudah ada';
                }
                if (!$err) {
                    $stmt->execute([$kode ?: null, $g('nama'), $kid, $ruangId, (int) $jml, $kond, $st, $ket]);
                    if ($kode !== '') $kodeAda[$kode] = true;
                }
            }

            if ($err) $gagal[] = [$no, $label, $err]; else $ok++;
        }
        $pdo->commit();
        catat_log("Import {$S['judul']}: {$ok} berhasil, " . count($gagal) . ' dilewati', $jenis);
        $hasil = ['ok' => $ok, 'gagal' => $gagal];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $galat = $e instanceof RuntimeException ? $e->getMessage() : 'Import gagal: ' . (APP_DEBUG ? $e->getMessage() : 'terjadi kesalahan.');
    }
}

$page_title = 'Import ' . $S['judul']; $active = 'master-data';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Import <?= e($S['judul']) ?></h2>
  <a class="btn sec sm" href="<?= e($back) ?>">Kembali</a>
</div>

<?php if ($galat): ?><div class="alert bad"><?= e($galat) ?></div><?php endif; ?>

<?php if ($hasil): ?>
  <div class="alert <?= $hasil['gagal'] ? 'warn' : 'ok' ?>">
    <?= (int) $hasil['ok'] ?> data berhasil diimpor<?= $hasil['gagal'] ? ', ' . count($hasil['gagal']) . ' baris dilewati.' : '.' ?>
  </div>
  <?php if ($hasil['gagal']): ?>
  <section class="panel">
    <div class="ph"><h2>Baris yang dilewati</h2></div>
    <div class="tbl-wrap"><table class="tbl sa">
      <thead><tr><th>Baris</th><th>Nama</th><th>Alasan</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($hasil['gagal'], 0, 50) as [$no, $nm, $alasan]): ?>
        <tr><td><?= (int) $no ?></td><td><?= e($nm) ?></td><td><?= e($alasan) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (count($hasil['gagal']) > 50): ?><p class="hint">Menampilkan 50 dari <?= count($hasil['gagal']) ?> baris.</p><?php endif; ?>
  </section>
  <?php endif; ?>
<?php endif; ?>

<section class="panel">
  <div class="ph"><h2>Unggah file</h2><a href="?jenis=<?= e($jenis) ?>&amp;template=1">Unduh template CSV</a></div>
  <p class="muted">Baris pertama harus berisi judul kolom: <code><?= e(implode(', ', $S['kolom'])) ?></code>.<br>
    <?= e($S['catatan']) ?><br>Maksimal <?= IMPORT_MAX ?> baris dan 2 MB per file. Baris yang bermasalah dilewati, sisanya tetap diimpor.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="fld">
      <label for="file">File CSV / XLSX</label>
      <input class="in" style="padding:10px 14px;height:auto" id="file" type="file" name="file" accept=".csv,.xlsx" required>
    </div>
    <div class="form-actions" style="justify-content:flex-start"><button class="btn" type="submit">Import</button></div>
  </form>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
