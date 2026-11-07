<?php
// Komponen tampilan untuk Rektorat: kartu statistik, donat, batang, "x waktu lalu".
// Semua dirender di server (SVG/HTML+CSS), tanpa library JavaScript.

const WARNA_BAIK = '#22B573', WARNA_PERBAIKAN = '#F5B82E', WARNA_RUSAK = '#E5484D';

/** Kelompok kondisi barang untuk Rektorat: Baik / Perbaikan (termasuk rusak ringan) / Rusak. */
function bucket_kondisi_barang(): array
{
    $b = ['baik' => 0, 'perbaikan' => 0, 'rusak' => 0];
    foreach (fetch_all('SELECT kondisi, COALESCE(SUM(jumlah), 0) AS n FROM barang GROUP BY kondisi') as $r) {
        $k = match ($r['kondisi']) { 'baik' => 'baik', 'rusak' => 'rusak', default => 'perbaikan' };
        $b[$k] += (int) $r['n'];
    }
    return $b;
}

function bucket_kondisi_ruang(): array
{
    $b = ['baik' => 0, 'perbaikan' => 0, 'rusak' => 0];
    foreach (fetch_all('SELECT kondisi, COUNT(*) AS n FROM ruang GROUP BY kondisi') as $r) $b[$r['kondisi']] = (int) $r['n'];
    return $b;
}

function stat_card(string $label, $nilai, string $ikon, string $tone = 'blue'): string
{
    return '<div class="sc"><div class="ibox ' . e($tone) . '">' . icon($ikon) . '</div><div><div class="lb">' . e($label)
         . '</div><div class="vl">' . e(is_int($nilai) ? number_format($nilai, 0, ',', '.') : (string) $nilai) . '</div></div></div>';
}

/** Donat SVG. $segs = [[label, nilai, warna], ...]. */
function donut_svg(array $segs, string $atas, string $nilai): string
{
    $r = 90; $c = 2 * M_PI * $r;
    $sum = array_sum(array_column($segs, 1));
    $svg = '<svg viewBox="0 0 240 240" width="240" height="240" role="img" aria-label="Diagram kondisi">'
         . '<circle cx="120" cy="120" r="90" fill="none" stroke="#EEF1F7" stroke-width="28"/>';
    $off = 0.0;
    if ($sum > 0) {
        foreach ($segs as [$label, $v, $warna]) {
            if ($v <= 0) continue;
            $len = $c * $v / $sum;
            $svg .= '<circle cx="120" cy="120" r="90" fill="none" stroke="' . e($warna) . '" stroke-width="28"'
                  . ' stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="' . round(-$off, 2)
                  . '" transform="rotate(-90 120 120)"/>';
            $off += $len;
        }
    }
    return '<div class="donut-wrap">' . $svg . '</svg><div class="donut-center"><div class="lb">' . e($atas)
         . '</div><div class="big">' . e($nilai) . '</div></div></div>';
}

/** Legenda; $dengan_angka menampilkan jumlah di kanan. */
function legend_html(array $segs, bool $denganAngka = false): string
{
    $h = '<div class="' . ($denganAngka ? 'legend-col' : 'legend') . '">';
    foreach ($segs as [$label, $v, $warna]) {
        $h .= '<div class="lg"><s style="background:' . e($warna) . '"></s><span style="flex:1">' . e($label) . '</span>'
            . ($denganAngka ? '<b>' . number_format((int) $v, 0, ',', '.') . '</b>' : '') . '</div>';
    }
    return $h . '</div>';
}

/**
 * Batang berpasangan. $grup = [[label, nilaiA, nilaiB], ...]; skala mengikuti nilai terbesar.
 */
function bars_html(array $grup, string $namaA, string $namaB, string $warnaA = '#246BFD', string $warnaB = '#C9D8FA'): string
{
    if (!$grup) return '<p class="empty">Belum ada data.</p>';
    $maks = max(1, ...array_map(fn($g) => max($g[1], $g[2]), $grup));
    $h = '<div class="bars">';
    foreach ($grup as [$label, $a, $b]) {
        $h .= '<div class="bargrp"><div class="pair">'
            . '<div class="bar" title="' . e($namaA . ': ' . $a) . '" style="height:' . round(200 * $a / $maks) . 'px;background:' . e($warnaA) . '"></div>'
            . '<div class="bar" title="' . e($namaB . ': ' . $b) . '" style="height:' . round(200 * $b / $maks) . 'px;background:' . e($warnaB) . '"></div>'
            . '</div><div class="lb">' . e($label) . '</div></div>';
    }
    return $h . '</div><div class="legend" style="justify-content:flex-start;gap:20px;margin-top:8px">'
         . '<div class="lg"><s style="background:' . e($warnaA) . '"></s>' . e($namaA) . '</div>'
         . '<div class="lg"><s style="background:' . e($warnaB) . '"></s>' . e($namaB) . '</div></div>';
}

function time_ago(string $datetime): string
{
    $t = strtotime($datetime);
    $d = time() - $t;
    if ($d < 60)    return 'baru saja';
    if ($d < 3600)  return intdiv($d, 60) . ' menit lalu';
    if ($d < 86400) return intdiv($d, 3600) . ' jam lalu';
    if ($d < 172800) return 'Kemarin';
    if ($d < 604800) return intdiv($d, 86400) . ' hari lalu';
    return tgl(date('Y-m-d', $t));
}
