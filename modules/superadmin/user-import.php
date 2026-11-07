<?php
require __DIR__ . '/../../core/bootstrap.php';
$me = require_role(['superadmin']);
require_permission('kelola_user_role');

$self = url('/modules/superadmin');
const IMPORT_MAX_ROWS = 300;   // password_hash sengaja lambat; batasi per file
$kolom = ['nim_nip', 'nama', 'email', 'no_hp', 'role', 'password', 'status'];

/* ---------- Unduh template CSV ---------- */
if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="template-import-user.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, $kolom, ';', '"', '\\');
    fputcsv($out, ['2021001', 'Budi Santoso', 'budi@kampus.ac.id', '081234567890', 'mahasiswa', 'GantiPassword123', 'aktif'], ';', '"', '\\');
    fputcsv($out, ['198501012010011001', 'Dewi Lestari', 'dewi@kampus.ac.id', '081298765432', 'dosen', 'GantiPassword123', 'aktif'], ';', '"', '\\');
    fclose($out);
    exit;
}

/** Baca file jadi array baris. Melempar RuntimeException bila gagal. */
function baca_file_import(string $tmp, string $ext): array
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

$hasil = null;
$galat = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    set_time_limit(180);
    $file = $_FILES['file'] ?? null;

    try {
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Pilih file terlebih dahulu.');
        if ($file['size'] > 2 * 1024 * 1024) throw new RuntimeException('Ukuran file maksimal 2 MB.');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'], true)) throw new RuntimeException('Format file harus .csv atau .xlsx.');

        $rows = baca_file_import($file['tmp_name'], $ext);
        if (count($rows) < 2) throw new RuntimeException('File kosong atau hanya berisi judul kolom.');

        // Normalisasi header
        $alias  = ['nim' => 'nim_nip', 'nip' => 'nim_nip', 'nim/nip' => 'nim_nip', 'hp' => 'no_hp', 'no_hp' => 'no_hp',
                   'kata_sandi' => 'password', 'sandi' => 'password', 'nama_lengkap' => 'nama'];
        $header = array_map(function ($h) use ($alias) {
            $h = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)));
            $h = str_replace([' ', '-'], '_', $h);
            return $alias[$h] ?? $h;
        }, array_shift($rows));
        foreach (['nim_nip', 'nama', 'email', 'role', 'password'] as $wajib) {
            if (!in_array($wajib, $header, true)) throw new RuntimeException("Kolom \"$wajib\" tidak ditemukan di baris judul.");
        }
        if (count($rows) > IMPORT_MAX_ROWS) throw new RuntimeException('Maksimal ' . IMPORT_MAX_ROWS . ' baris per import. Bagi file menjadi beberapa bagian.');

        // Data pembanding
        $roleMap = [];
        foreach (fetch_all('SELECT id, kode, nama FROM roles') as $r) {
            $roleMap[strtolower($r['kode'])] = $r; $roleMap[strtolower($r['nama'])] = $r;
        }
        $nimAda   = array_flip(array_column(fetch_all('SELECT nim_nip FROM users'), 'nim_nip'));
        $emailAda = array_flip(array_map('strtolower', array_column(fetch_all('SELECT email FROM users'), 'email')));

        $ok = 0; $gagal = [];
        $pdo = db();
        $pdo->beginTransaction();
        $ins = $pdo->prepare('INSERT INTO users (nim_nip, nama, email, no_hp, password, role_id, status) VALUES (?,?,?,?,?,?,?)');
        $set = $pdo->prepare('INSERT INTO user_settings (user_id) VALUES (?)');

        foreach ($rows as $i => $row) {
            $no = $i + 2;                                   // nomor baris di spreadsheet
            $d  = [];
            foreach ($header as $k => $nama) $d[$nama] = trim((string) ($row[$k] ?? ''));
            if (implode('', $d) === '') continue;           // baris kosong

            $nim = $d['nim_nip']; $email = strtolower($d['email']); $nm = $d['nama'];
            $hp = $d['no_hp'] ?? ''; $pw = $d['password']; $st = strtolower($d['status'] ?? '') ?: 'aktif';
            $role = $roleMap[strtolower($d['role'])] ?? null;

            $err = null;
            if (!preg_match('/^[A-Za-z0-9._-]{3,30}$/', $nim))             $err = 'NIM/NIP tidak valid';
            elseif (mb_strlen($nm) < 2 || mb_strlen($nm) > 100)            $err = 'Nama tidak valid';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))            $err = 'Email tidak valid';
            elseif ($hp !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $hp)) $err = 'No HP tidak valid';
            elseif (!$role)                                                $err = 'Role "' . $d['role'] . '" tidak dikenal';
            elseif ($role['kode'] === 'superadmin')                        $err = 'Role Superadmin tidak boleh diimpor';
            elseif (strlen($pw) < 8 || strlen($pw) > 72)                   $err = 'Kata sandi 8–72 karakter';
            elseif (!in_array($st, ['aktif', 'nonaktif'], true))           $err = 'Status harus aktif/nonaktif';
            elseif (isset($nimAda[$nim]))                                  $err = 'NIM/NIP sudah terdaftar';
            elseif (isset($emailAda[$email]))                              $err = 'Email sudah terdaftar';

            if ($err) { $gagal[] = [$no, $nim ?: '-', $err]; continue; }

            $ins->execute([$nim, $nm, $email, $hp ?: null, password_hash($pw, PASSWORD_DEFAULT), $role['id'], $st]);
            $set->execute([$pdo->lastInsertId()]);
            $nimAda[$nim] = true; $emailAda[$email] = true;
            $ok++;
        }
        $pdo->commit();
        catat_log("Import user: {$ok} berhasil, " . count($gagal) . ' gagal', 'user');
        $hasil = ['ok' => $ok, 'gagal' => $gagal];
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        $galat = $e instanceof RuntimeException ? $e->getMessage() : 'Import gagal: ' . (APP_DEBUG ? $e->getMessage() : 'terjadi kesalahan.');
    }
}

$page_title = 'Import User'; $active = 'user';
require __DIR__ . '/../../layouts/header.php';
?>
<div class="page-head">
  <h2 class="title">Import User</h2>
  <a class="btn sec sm" href="<?= e($self) ?>/user.php">Kembali</a>
</div>

<?php if ($galat): ?><div class="alert bad"><?= e($galat) ?></div><?php endif; ?>

<?php if ($hasil): ?>
  <div class="alert <?= $hasil['gagal'] ? 'warn' : 'ok' ?>">
    <?= (int) $hasil['ok'] ?> user berhasil diimpor<?= $hasil['gagal'] ? ', ' . count($hasil['gagal']) . ' baris dilewati.' : '.' ?>
  </div>
  <?php if ($hasil['gagal']): ?>
  <section class="panel">
    <div class="ph"><h2>Baris yang dilewati</h2></div>
    <div class="tbl-wrap"><table class="tbl sa">
      <thead><tr><th>Baris</th><th>NIM/NIP</th><th>Alasan</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($hasil['gagal'], 0, 50) as [$no, $nim, $alasan]): ?>
        <tr><td><?= (int) $no ?></td><td><?= e($nim) ?></td><td><?= e($alasan) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (count($hasil['gagal']) > 50): ?><p class="hint">Menampilkan 50 dari <?= count($hasil['gagal']) ?> baris.</p><?php endif; ?>
  </section>
  <?php endif; ?>
<?php endif; ?>

<section class="panel">
  <div class="ph"><h2>Unggah file</h2><a href="?template=1">Unduh template CSV</a></div>
  <p class="muted">Baris pertama harus berisi judul kolom:
    <code><?= e(implode(', ', $kolom)) ?></code>.
    Kolom <b>role</b> diisi kode role (mahasiswa, dosen, sarpras, rektorat). <b>status</b> boleh kosong (otomatis aktif).
    Maksimal <?= IMPORT_MAX_ROWS ?> baris dan 2 MB per file. Role Superadmin tidak dapat diimpor.</p>
  <form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="fld">
      <label for="file">File CSV / XLSX</label>
      <input class="in" style="padding:10px 14px;height:auto" id="file" type="file" name="file" accept=".csv,.xlsx" required>
    </div>
    <div class="form-actions" style="justify-content:flex-start">
      <button class="btn" type="submit">Import</button>
    </div>
  </form>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
