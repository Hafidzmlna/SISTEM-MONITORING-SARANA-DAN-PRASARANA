<?php
// Helper bersama untuk laporan kerusakan & mutasi.

const KATEGORI_FASILITAS = [
    'elektronik' => 'Elektronik', 'furnitur' => 'Furnitur', 'kelistrikan' => 'Kelistrikan',
    'sanitasi' => 'Sanitasi', 'bangunan' => 'Bangunan', 'perlengkapan' => 'Perlengkapan', 'lainnya' => 'Lainnya',
];
const FOTO_MAKS_BYTE = 5 * 1024 * 1024;
const LAPORAN_MAKS_PER_HARI = 20;   // batas anti-spam per user

function kode_laporan(string $prefix, int $id): string
{
    return $prefix . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
}

function opsi_kategori_fasilitas(string $selected): string
{
    $h = '<option value="">Pilih kategori</option>';
    foreach (KATEGORI_FASILITAS as $v => $label) {
        $h .= '<option value="' . e($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h;
}

/** Pemilik laporan, atau role yang punya hak lihat laporan (Sarpras/Rektorat). */
function boleh_lihat_laporan(string $jenis, int $pelaporId): bool
{
    $u = current_user();
    if (!$u) return false;
    if ($pelaporId === (int) $u['id']) return true;
    return has_permission(perm_lihat_laporan($jenis));
}

function normalisasi_lokasi(string $s): string
{
    $s = str_replace(['–', '—'], '-', $s);
    return preg_replace('/\s+/', ' ', mb_strtolower(trim($s)));
}

/** Cocokkan teks lokasi ("Gedung A – Ruang 204" atau nama ruang unik) ke id ruang master. */
function cari_ruang_dari_lokasi(string $lokasi): ?int
{
    $byLabel = []; $byNama = [];
    foreach (fetch_all('SELECT r.id, r.nama, g.nama AS gedung FROM ruang r JOIN gedung g ON g.id = r.gedung_id') as $r) {
        $byLabel[normalisasi_lokasi($r['gedung'] . ' - ' . $r['nama'])] = (int) $r['id'];
        $byNama[normalisasi_lokasi($r['nama'])][] = (int) $r['id'];
    }
    $k = normalisasi_lokasi($lokasi);
    if (isset($byLabel[$k])) return $byLabel[$k];
    if (isset($byNama[$k]) && count($byNama[$k]) === 1) return $byNama[$k][0];
    return null;
}

/**
 * Simpan foto laporan (PNG/JPG, maks 5 MB) ke uploads/laporan dengan nama acak.
 * @return string|null nama file tersimpan, atau null bila tidak ada file / gagal (lihat $error)
 */
function simpan_foto_laporan(?array $file, ?string &$error): ?string
{
    $error = null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Ukuran foto melebihi batas.' : 'Foto gagal diunggah, coba lagi.';
        return null;
    }
    if ($file['size'] > FOTO_MAKS_BYTE) { $error = 'Ukuran foto maksimal 5 MB.'; return null; }

    $info = @getimagesize($file['tmp_name']);
    $tipe = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (!$info || !isset($tipe[$info[2]])) { $error = 'Foto harus berformat PNG atau JPG.'; return null; }
    if ($info[0] > 10000 || $info[1] > 10000) { $error = 'Dimensi foto terlalu besar.'; return null; }

    $dir = __DIR__ . '/../uploads/laporan';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { $error = 'Folder penyimpanan tidak dapat dibuat.'; return null; }

    $nama = bin2hex(random_bytes(16)) . '.' . $tipe[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nama)) { $error = 'Foto gagal disimpan.'; return null; }
    return $nama;
}

/** Path file foto di disk, hanya bila nama file valid (cegah path traversal). */
function path_foto_laporan(?string $nama): ?string
{
    if (!$nama || !preg_match('/^[a-f0-9]{32}\.(jpg|png)$/', $nama)) return null;
    $p = __DIR__ . '/../uploads/laporan/' . $nama;
    return is_file($p) ? $p : null;
}

function hapus_foto_laporan(?string $nama): void
{
    if ($p = path_foto_laporan($nama)) @unlink($p);
}

/* =====================================================
   Laporan untuk Sarpras / Rektorat (daftar, filter, ekspor)
   ===================================================== */

const STATUS_LAPORAN = [
    'kerusakan'         => ['diajukan', 'diproses', 'selesai', 'ditolak'],
    'mutasi'            => ['diajukan', 'disetujui', 'ditolak'],
    'peminjaman_ruang'  => ['diajukan', 'disetujui', 'digunakan', 'selesai', 'ditolak'],
    'peminjaman_barang' => ['diajukan', 'disetujui', 'dipinjam', 'terlambat', 'dikembalikan', 'ditolak'],
];
const JUDUL_LAPORAN = [
    'mutasi' => 'Laporan Mutasi Barang', 'kerusakan' => 'Laporan Kerusakan',
    'peminjaman_ruang' => 'Laporan Peminjaman Ruang', 'peminjaman_barang' => 'Laporan Peminjaman Barang',
];
const SLUG_LAPORAN = [
    'mutasi' => 'laporan-mutasi', 'kerusakan' => 'laporan-kerusakan',
    'peminjaman_ruang' => 'laporan-peminjaman-ruang', 'peminjaman_barang' => 'laporan-peminjaman-barang',
];

function perm_lihat_laporan(string $jenis): string
{
    return match ($jenis) {
        'mutasi'    => 'lihat_laporan_mutasi',
        'kerusakan' => 'lihat_laporan_kerusakan',
        default     => 'lihat_laporan_peminjaman',
    };
}

/**
 * Label status sesuai pembaca: di sisi Sarpras status awal (kerusakan, mutasi, peminjaman ruang
 * & barang) tampil "Baru"; Mahasiswa/Dosen melihat "Diajukan". Nilai di database tetap 'diajukan'.
 */
function status_laporan_label(string $status, string $jenis = 'kerusakan'): string
{
    $role = current_user()['role_kode'] ?? '';
    if ($status === 'diajukan' && $role === 'sarpras') return 'Baru';
    if ($role === 'rektorat' && $jenis === 'kerusakan') {          // istilah Rektorat: Rusak / Perbaikan / Selesai
        if ($status === 'diajukan') return 'Rusak';
        if ($status === 'diproses') return 'Perbaikan';
    }
    return ucfirst(str_replace('_', ' ', $status));
}

function badge_laporan(string $status, string $jenis = 'kerusakan'): string
{
    $role = current_user()['role_kode'] ?? '';
    if ($role === 'rektorat' && $jenis === 'kerusakan') {          // warna: Rusak=merah, Perbaikan=kuning
        $warna = ['diajukan' => 'rusak', 'diproses' => 'rusak_ringan'];
        if (isset($warna[$status])) return badge($warna[$status], status_laporan_label($status, $jenis));
    }
    return badge($status, status_laporan_label($status, $jenis));
}

function param_tanggal(string $s): string
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : '';
}

/** Cegah formula injection saat CSV dibuka di Excel. */
function csv_aman($v): string
{
    $v = (string) $v;
    return ($v !== '' && preg_match('/^[=+\-@\t\r]/', $v)) ? "'" . $v : $v;
}

/** Bangun FROM/WHERE/params dari parameter GET (q, status, dari, sampai). */
function laporan_filter(string $jenis, array $in): array
{
    $q  = trim((string) ($in['q'] ?? ''));
    $st = (string) ($in['status'] ?? '');
    if (!in_array($st, STATUS_LAPORAN[$jenis], true)) $st = '';
    $dari   = param_tanggal((string) ($in['dari'] ?? ''));
    $sampai = param_tanggal((string) ($in['sampai'] ?? ''));
    $gd     = (int) ($in['gedung'] ?? 0);

    if ($jenis === 'kerusakan') {
        $t = 'l'; $tgl = 'l.tanggal_lapor'; $order = 'l.id DESC';
        $cari = ['l.kode', 'l.nama_barang', 'l.lokasi', 'l.deskripsi', 'p.nama'];
        $from = 'FROM laporan_kerusakan l
                 JOIN users p ON p.id = l.pelapor_id
                 LEFT JOIN users h ON h.id = l.ditangani_oleh';
    } elseif ($jenis === 'mutasi') {
        $t = 'm'; $tgl = 'm.tanggal'; $order = 'm.id DESC';
        $cari = ['m.kode', 'b.nama', 'r1.nama', 'r2.nama', 'm.keterangan', 'p.nama'];
        $from = 'FROM laporan_mutasi m
                 JOIN users p ON p.id = m.pelapor_id
                 LEFT JOIN users h ON h.id = m.diproses_oleh
                 JOIN barang b ON b.id = m.barang_id
                 LEFT JOIN ruang r1 ON r1.id = m.dari_ruang_id
                 JOIN ruang r2 ON r2.id = m.ke_ruang_id';
    } elseif ($jenis === 'peminjaman_ruang') {
        $t = 'pr'; $tgl = 'pr.tanggal'; $order = 'pr.id DESC';
        $cari = ['pr.kode', 'r.nama', 'g.nama', 'pr.keperluan', 'p.nama'];
        $from = 'FROM peminjaman_ruang pr
                 JOIN users p ON p.id = pr.user_id
                 JOIN ruang r ON r.id = pr.ruang_id
                 JOIN gedung g ON g.id = r.gedung_id
                 LEFT JOIN users h ON h.id = pr.diproses_oleh';
    } else {
        $t = 'pb'; $tgl = 'pb.tgl_pinjam'; $order = 'pb.id DESC';
        $cari = ['pb.kode', 'b.nama', 'pb.keperluan', 'p.nama'];
        $from = 'FROM peminjaman_barang pb
                 JOIN users p ON p.id = pb.user_id
                 JOIN barang b ON b.id = pb.barang_id
                 LEFT JOIN users h ON h.id = pb.diproses_oleh';
    }

    $where = []; $params = [];
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(' . implode(' OR ', array_map(fn($c) => "$c LIKE ?", $cari)) . ')';
        foreach ($cari as $_) $params[] = $like;
    }
    if ($st === 'terlambat') {                       // dihitung, bukan disimpan
        $where[] = "pb.status = 'dipinjam' AND pb.tgl_kembali < ?"; $params[] = date('Y-m-d');
    } elseif ($st === 'dipinjam' && $jenis === 'peminjaman_barang') {
        $where[] = "pb.status = 'dipinjam' AND pb.tgl_kembali >= ?"; $params[] = date('Y-m-d');
    } elseif ($st !== '') {
        $where[] = "$t.status = ?"; $params[] = $st;
    }
    if ($gd > 0) {                                   // filter gedung (Rektorat)
        if ($jenis === 'kerusakan') {
            $nm = (string) fetch_value('SELECT nama FROM gedung WHERE id = ?', [$gd]);
            $where[] = '(l.ruang_id IN (SELECT id FROM ruang WHERE gedung_id = ?) OR l.lokasi LIKE ?)';
            $params[] = $gd; $params[] = '%' . addcslashes($nm, '%_\\') . '%';
        } elseif ($jenis === 'mutasi') {
            $where[] = '(r2.gedung_id = ? OR r1.gedung_id = ?)'; $params[] = $gd; $params[] = $gd;
        } elseif ($jenis === 'peminjaman_ruang') {
            $where[] = 'r.gedung_id = ?'; $params[] = $gd;
        } else {
            $where[] = 'b.ruang_id IN (SELECT id FROM ruang WHERE gedung_id = ?)'; $params[] = $gd;
        }
    }
    if ($dari !== '')   { $where[] = "$tgl >= ?"; $params[] = $dari; }
    if ($sampai !== '') { $where[] = "$tgl <= ?"; $params[] = $sampai; }

    return [
        'from' => $from, 'where' => $where ? 'WHERE ' . implode(' AND ', $where) : '',
        'params' => $params, 'order' => $order,
        'q' => $q, 'status' => $st, 'dari' => $dari, 'sampai' => $sampai, 'gedung' => $gd,
    ];
}

/** Judul "Laporan" + tab + kotak cari + filter status. */
function laporan_head(string $aktif, array $F, string $placeholder): void
{
    $base = url('/modules/sarpras');
    echo '<h2 class="title mh-title">Laporan</h2><div class="tabs">';
    foreach (JUDUL_LAPORAN as $k => $label) {
        if (!has_permission(perm_lihat_laporan($k))) continue;
        echo '<a class="tab' . ($aktif === $k ? ' on' : '') . '" href="' . e($base . '/' . SLUG_LAPORAN[$k] . '.php') . '">' . e($label) . '</a>';
    }
    echo '</div><div class="toolbar"><form method="get" class="search-row">';
    foreach (['dari', 'sampai'] as $k) {
        if ($F[$k] !== '') echo '<input type="hidden" name="' . $k . '" value="' . e($F[$k]) . '">';
    }
    echo '<div class="search">' . icon('search')
       . '<input type="search" name="q" value="' . e($F['q']) . '" placeholder="' . e($placeholder) . '" aria-label="Cari"></div>'
       . '<select class="in sel" name="status" data-autosubmit aria-label="Filter status"><option value="">Semua Status</option>';
    foreach (STATUS_LAPORAN[$aktif] as $s) {
        echo '<option value="' . e($s) . '"' . ($F['status'] === $s ? ' selected' : '') . '>' . e(status_laporan_label($s, $aktif)) . '</option>';
    }
    echo '</select></form></div>';
}

/** Baris bawah: rentang tanggal + Unduh Laporan (kiri), pagination (kanan). */
function laporan_foot(string $jenis, array $pg, array $F): string
{
    $exportQs = http_build_query(array_filter(
        ['jenis' => $jenis, 'q' => $F['q'], 'status' => $F['status'], 'dari' => $F['dari'], 'sampai' => $F['sampai']],
        fn($v) => $v !== ''));
    $kiri = '<form method="get" class="datebar">';
    foreach (['q', 'status'] as $k) {
        if ($F[$k] !== '') $kiri .= '<input type="hidden" name="' . $k . '" value="' . e($F[$k]) . '">';
    }
    $kiri .= '<label for="dari">Dari tanggal</label><input id="dari" class="in di" type="date" name="dari" value="' . e($F['dari']) . '" data-autosubmit>'
           . '<label for="sampai">Sampai tanggal</label><input id="sampai" class="in di" type="date" name="sampai" value="' . e($F['sampai']) . '" data-autosubmit>';
    if (has_permission('unduh_rekap_laporan')) {
        $kiri .= '<a class="btn sm" href="' . e(url('/modules/laporan/export.php') . '?' . $exportQs) . '">' . icon('download') . 'Unduh Laporan</a>';
    }
    $kiri .= '</form>';

    return pagination_compact($pg, ['q' => $F['q'], 'status' => $F['status'], 'dari' => $F['dari'], 'sampai' => $F['sampai']], 'laporan', $kiri);
}
