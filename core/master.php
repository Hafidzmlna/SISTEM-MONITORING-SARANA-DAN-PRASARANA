<?php
// Helper bersama untuk halaman Master Data Sarpras (Barang, Ruang, Gedung).

const KONDISI_GEDUNG = ['baik', 'perbaikan', 'rusak'];
const KONDISI_BARANG = ['baik', 'rusak_ringan', 'perbaikan', 'rusak'];
const STATUS_RUANG   = ['tersedia', 'dipakai'];
const STATUS_BARANG  = ['tersedia', 'dipinjam'];

function label_enum(string $v): string
{
    return ucfirst(str_replace('_', ' ', $v));
}

/** <option> dari daftar nilai enum. */
function opt_enum(array $values, string $selected): string
{
    $h = '';
    foreach ($values as $v) {
        $h .= '<option value="' . e($v) . '"' . ($v === $selected ? ' selected' : '') . '>' . e(label_enum($v)) . '</option>';
    }
    return $h;
}

function err_msg(array $errors, string $key): string
{
    return isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '';
}

/** Judul + tab + toolbar (cari, Import Excel, Tambah). */
function master_head(string $aktif, string $q, string $placeholder, string $tambahLabel): void
{
    $base = url('/modules/sarpras');
    $tabs = [
        ['barang', 'Data Barang', 'kelola_barang'],
        ['ruang',  'Data Ruang',  'kelola_ruang'],
        ['gedung', 'Data Gedung', 'kelola_gedung'],
    ];
    echo '<h2 class="title mh-title">Master Data</h2><div class="tabs">';
    foreach ($tabs as [$key, $label, $perm]) {
        if (!has_permission($perm)) continue;
        echo '<a class="tab' . ($aktif === $key ? ' on' : '') . '" href="' . e("$base/$key.php") . '">' . e($label) . '</a>';
    }
    echo '</div><div class="toolbar">'
       . '<form method="get" class="search">' . icon('search')
       . '<input type="search" name="q" value="' . e($q) . '" placeholder="' . e($placeholder) . '" aria-label="Cari"></form>'
       . '<div class="head-actions">'
       . '<a class="btn ghost sm" href="' . e("$base/master-import.php?jenis=$aktif") . '">' . icon('upload') . 'Import Excel</a>'
       . '<a class="btn sm" href="' . e("$base/$aktif-form.php") . '">' . icon('plus') . e($tambahLabel) . '</a>'
       . '</div></div>';
}

/** Tombol ikon Ubah + Hapus (form POST dengan konfirmasi). */
function aksi_buttons(string $editHref, int $id, string $nama): string
{
    return '<div class="aksi">'
        . '<a class="ib" aria-label="Ubah" title="Ubah" href="' . e($editHref) . '">' . icon('edit') . '</a>'
        . '<form method="post" class="inline" data-confirm="Hapus &quot;' . e($nama) . '&quot;? Tindakan ini tidak dapat dibatalkan.">'
        . csrf_field() . '<input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="ib danger" aria-label="Hapus" title="Hapus">' . icon('trash') . '</button></form></div>';
}
