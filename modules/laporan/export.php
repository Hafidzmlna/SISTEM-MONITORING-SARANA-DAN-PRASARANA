<?php
// Unduh rekap laporan sebagai CSV (dibuka langsung di Excel). Mengikuti filter yang sedang aktif.
require __DIR__ . '/../../core/bootstrap.php';
require __DIR__ . '/../../core/laporan.php';
require __DIR__ . '/../../core/peminjaman.php';
require_login();

$jenis = $_GET['jenis'] ?? '';
if (!isset(STATUS_LAPORAN[$jenis])) abort(404);
require_permission('unduh_rekap_laporan');
require_permission(perm_lihat_laporan($jenis));
if ($jenis === 'peminjaman_ruang') peminjaman_sinkron_waktu();

const EXPORT_MAKS = 10000;
$F  = laporan_filter($jenis, $_GET);
$st = fn(string $s) => status_laporan_label($s, $jenis);
$q  = fn(string $cols) => "SELECT $cols {$F['from']} {$F['where']} ORDER BY {$F['order']} LIMIT " . EXPORT_MAKS;

if ($jenis === 'kerusakan') {
    $judul = ['Kode', 'Tanggal Laporan', 'Pelapor', 'NIM/NIP', 'Nama Barang', 'Kategori', 'Lokasi', 'Deskripsi', 'Status', 'Ditangani Oleh', 'Tanggal Selesai', 'Catatan Sarpras'];
    $data = fetch_all($q('l.kode, l.tanggal_lapor, p.nama AS pelapor, p.nim_nip, l.nama_barang, l.kategori_fasilitas, l.lokasi,
                          l.deskripsi, l.status, h.nama AS penangan, l.tanggal_selesai, l.catatan_sarpras'), $F['params']);
    $baris = array_map(fn($r) => [
        $r['kode'], tgl($r['tanggal_lapor']), $r['pelapor'], $r['nim_nip'], $r['nama_barang'],
        KATEGORI_FASILITAS[$r['kategori_fasilitas']] ?? '', $r['lokasi'], $r['deskripsi'], $st($r['status']),
        $r['penangan'] ?? '', $r['tanggal_selesai'] ? tgl($r['tanggal_selesai']) : '', $r['catatan_sarpras'] ?? '',
    ], $data);
} elseif ($jenis === 'mutasi') {
    $judul = ['Kode', 'Tanggal', 'Pelapor', 'NIM/NIP', 'Barang', 'Dari', 'Ke', 'Keterangan', 'Status', 'Diproses Oleh', 'Catatan Sarpras'];
    $data = fetch_all($q('m.kode, m.tanggal, p.nama AS pelapor, p.nim_nip, b.nama AS barang, r1.nama AS dari, r2.nama AS ke,
                          m.keterangan, m.status, h.nama AS penangan, m.catatan_sarpras'), $F['params']);
    $baris = array_map(fn($r) => [
        $r['kode'], tgl($r['tanggal']), $r['pelapor'], $r['nim_nip'], $r['barang'], $r['dari'] ?? '', $r['ke'],
        $r['keterangan'] ?? '', $st($r['status']), $r['penangan'] ?? '', $r['catatan_sarpras'] ?? '',
    ], $data);
} elseif ($jenis === 'peminjaman_ruang') {
    $judul = ['Kode', 'Tanggal', 'Waktu', 'Peminjam', 'NIM/NIP', 'Ruang', 'Gedung', 'Keperluan', 'Status', 'Diproses Oleh', 'Catatan Sarpras'];
    $data = fetch_all($q('pr.kode, pr.tanggal, pr.jam_mulai, pr.jam_selesai, p.nama AS pemohon, p.nim_nip, r.nama AS ruang, g.nama AS gedung,
                          pr.keperluan, pr.status, h.nama AS penangan, pr.catatan_sarpras'), $F['params']);
    $baris = array_map(fn($r) => [
        $r['kode'], tgl($r['tanggal']), waktu_label($r['jam_mulai'], $r['jam_selesai']), $r['pemohon'], $r['nim_nip'],
        $r['ruang'], $r['gedung'], $r['keperluan'], $st($r['status']), $r['penangan'] ?? '', $r['catatan_sarpras'] ?? '',
    ], $data);
} else {
    $judul = ['Kode', 'Peminjam', 'NIM/NIP', 'Barang', 'Jumlah', 'Tanggal Pinjam', 'Tanggal Kembali', 'Dikembalikan Pada', 'Keperluan', 'Status', 'Diproses Oleh', 'Catatan Sarpras'];
    $data = fetch_all($q('pb.kode, p.nama AS pemohon, p.nim_nip, b.nama AS barang, pb.jumlah, pb.tgl_pinjam, pb.tgl_kembali,
                          pb.tgl_dikembalikan, pb.keperluan, pb.status, h.nama AS penangan, pb.catatan_sarpras'), $F['params']);
    $baris = array_map(fn($r) => [
        $r['kode'], $r['pemohon'], $r['nim_nip'], $r['barang'], $r['jumlah'], tgl($r['tgl_pinjam']), tgl($r['tgl_kembali']),
        $r['tgl_dikembalikan'] ? tgl($r['tgl_dikembalikan']) : '', $r['keperluan'],
        barang_terlambat($r) ? 'Terlambat' : $st($r['status']), $r['penangan'] ?? '', $r['catatan_sarpras'] ?? '',
    ], $data);
}

catat_log('Mengunduh rekap ' . JUDUL_LAPORAN[$jenis] . ' (' . count($baris) . ' baris)', 'laporan');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . SLUG_LAPORAN[$jenis] . '-' . date('Ymd') . '.csv"');
header('X-Content-Type-Options: nosniff');
echo "\xEF\xBB\xBF";                                   // BOM supaya Excel membaca UTF-8
$out = fopen('php://output', 'w');
fputcsv($out, $judul, ';', '"', '\\');
foreach ($baris as $b) fputcsv($out, array_map('csv_aman', $b), ';', '"', '\\');
fclose($out);
exit;
