<?php
// Helper peminjaman ruang & barang (dipakai Mahasiswa/Dosen, Sarpras, Rektorat).

const DURASI_MENIT = [
    30 => '30 menit', 60 => '1 jam', 90 => '1,5 jam', 120 => '2 jam', 150 => '2,5 jam',
    180 => '3 jam', 240 => '4 jam', 300 => '5 jam', 360 => '6 jam', 480 => '8 jam',
];
const JAM_BUKA  = '06:00';
const JAM_TUTUP = '22:00';
const PINJAM_MAJU_MAKS_HARI   = 180;   // boleh mengajukan sampai 180 hari ke depan
const PINJAM_BARANG_MAKS_HARI = 30;    // lama pinjam barang maksimal
const PINJAM_MAKS_PER_HARI    = 20;    // batas pengajuan per user per hari (anti-spam)

/**
 * Status ruang bergerak otomatis mengikuti waktu:
 * disetujui -> digunakan (saat jam mulai) -> selesai (setelah jam selesai).
 * Dipanggil di awal halaman yang menampilkan peminjaman ruang.
 */
function peminjaman_sinkron_waktu(): void
{
    $now = date('Y-m-d H:i:s');
    query("UPDATE peminjaman_ruang SET status = 'selesai'
           WHERE status IN ('disetujui','digunakan') AND TIMESTAMP(tanggal, jam_selesai) <= ?", [$now]);
    query("UPDATE peminjaman_ruang SET status = 'digunakan'
           WHERE status = 'disetujui' AND TIMESTAMP(tanggal, jam_mulai) <= ? AND TIMESTAMP(tanggal, jam_selesai) > ?", [$now, $now]);
}

function jam_opsi(string $selected): string
{
    $h = '<option value="">Pilih jam</option>';
    for ($m = 6 * 60; $m < 22 * 60; $m += 30) {
        $v = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $h .= '<option value="' . $v . '"' . ($v === $selected ? ' selected' : '') . '>' . str_replace(':', '.', $v) . '</option>';
    }
    return $h;
}

function durasi_opsi(int $selected): string
{
    $h = '<option value="">Pilih durasi</option>';
    foreach (DURASI_MENIT as $m => $label) {
        $h .= '<option value="' . $m . '"' . ($m === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $h;
}

function jam_valid(string $hhmm): bool
{
    if (!preg_match('/^\d{2}:\d{2}$/', $hhmm)) return false;
    return $hhmm >= JAM_BUKA && $hhmm < JAM_TUTUP && in_array((int) substr($hhmm, 3), [0, 30], true);
}

/** Jam selesai = mulai + durasi (menit). Null bila melewati jam tutup. */
function jam_selesai(string $mulai, int $menit): ?string
{
    $total = (int) substr($mulai, 0, 2) * 60 + (int) substr($mulai, 3) + $menit;
    $s = sprintf('%02d:%02d', intdiv($total, 60), $total % 60);
    return $s > JAM_TUTUP ? null : $s;
}

/** Tanggal Y-m-d valid dan berada dalam [min, maks]. */
function tanggal_dalam_rentang(string $s, string $min, string $maks): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return $d && $d->format('Y-m-d') === $s && $s >= $min && $s <= $maks;
}

function waktu_label(string $mulai, string $selesai): string
{
    return str_replace(':', '.', substr($mulai, 0, 5)) . ' - ' . str_replace(':', '.', substr($selesai, 0, 5));
}

/** Peminjaman ruang yang sudah disetujui dan bertabrakan dengan rentang ini (null = aman). */
function ruang_bentrok(int $ruangId, string $tanggal, string $mulai, string $selesai, ?int $kecuali = null): ?array
{
    return fetch_one(
        "SELECT kode, jam_mulai, jam_selesai FROM peminjaman_ruang
         WHERE ruang_id = ? AND tanggal = ? AND status IN ('disetujui','digunakan')
           AND jam_mulai < ? AND jam_selesai > ? AND id <> ? LIMIT 1",
        [$ruangId, $tanggal, $selesai . ':00', $mulai . ':00', $kecuali ?? 0]
    );
}

/**
 * Sisa unit barang pada rentang tanggal tertentu.
 * Unit yang terpakai = peminjaman disetujui/dipinjam yang tumpang tindih tanggalnya,
 * ditambah yang masih dipinjam walau sudah lewat tanggal kembali (belum dikembalikan).
 */
function stok_tersedia(int $barangId, string $pinjam, string $kembali, ?int $kecuali = null): int
{
    $total = (int) fetch_value('SELECT jumlah FROM barang WHERE id = ?', [$barangId]);
    $terpakai = (int) fetch_value(
        "SELECT COALESCE(SUM(jumlah), 0) FROM peminjaman_barang
         WHERE barang_id = ? AND id <> ? AND status IN ('disetujui','dipinjam')
           AND tgl_pinjam <= ?
           AND (tgl_kembali >= ? OR (status = 'dipinjam' AND tgl_kembali < ?))",
        [$barangId, $kecuali ?? 0, $kembali, $pinjam, date('Y-m-d')]
    );
    return max(0, $total - $terpakai);
}

/** Unit barang yang sedang benar-benar dipinjam (sudah diserahkan) saat ini. */
function unit_sedang_dipinjam(int $barangId, ?int $kecuali = null): int
{
    return (int) fetch_value(
        "SELECT COALESCE(SUM(jumlah), 0) FROM peminjaman_barang WHERE barang_id = ? AND status = 'dipinjam' AND id <> ?",
        [$barangId, $kecuali ?? 0]
    );
}

/** barang.status = 'dipinjam' bila semua unit sedang dipinjam, selain itu 'tersedia'. */
function sinkron_status_barang(int $barangId): void
{
    $total = (int) fetch_value('SELECT jumlah FROM barang WHERE id = ?', [$barangId]);
    $baru  = unit_sedang_dipinjam($barangId) >= $total && $total > 0 ? 'dipinjam' : 'tersedia';
    query('UPDATE barang SET status = ? WHERE id = ?', [$baru, $barangId]);
}

function barang_terlambat(array $row): bool
{
    return $row['status'] === 'dipinjam' && $row['tgl_kembali'] < date('Y-m-d');
}
