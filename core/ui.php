<?php

/** Hitung data pagination. LIMIT/OFFSET dipakai langsung (sudah di-cast ke int). */
function paginate(int $total, int $per, int $page): array
{
    $pages  = max(1, (int) ceil($total / $per));
    $page   = min(max(1, $page), $pages);
    $offset = ($page - 1) * $per;
    return [
        'total' => $total, 'per' => $per, 'page' => $page, 'pages' => $pages, 'offset' => $offset,
        'from'  => $total ? $offset + 1 : 0,
        'to'    => min($total, $offset + $per),
    ];
}

/** Footer tabel: "Menampilkan 1-10 dari N user" + tombol halaman. $query = filter aktif. */
function pagination_html(array $pg, array $query, string $noun): string
{
    $query = array_filter($query, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    $link  = fn(int $p) => '?' . http_build_query($query + ['page' => $p]);

    $html  = '<div class="pg-foot"><span>Menampilkan ' . $pg['from'] . '-' . $pg['to']
           . ' dari ' . number_format($pg['total'], 0, ',', '.') . ' ' . e($noun) . '</span><div class="pg-btns">';

    $html .= $pg['page'] > 1
        ? '<a class="btn sec sm" href="' . e($link($pg['page'] - 1)) . '">Sebelumnya</a>'
        : '<span class="btn sec sm disabled">Sebelumnya</span>';

    for ($p = max(1, $pg['page'] - 2); $p <= min($pg['pages'], $pg['page'] + 2); $p++) {
        $html .= '<a class="btn sm' . ($p === $pg['page'] ? '' : ' sec') . '" href="' . e($link($p)) . '">' . $p . '</a>';
    }

    $html .= $pg['page'] < $pg['pages']
        ? '<a class="btn sec sm" href="' . e($link($pg['page'] + 1)) . '">Berikutnya</a>'
        : '<span class="btn sec sm disabled">Berikutnya</span>';

    return $html . '</div></div>';
}

function role_tag(?string $nama): string
{
    return $nama ? '<span class="tag">' . e($nama) . '</span>' : '<span class="muted">-</span>';
}

function status_tag(string $status): string
{
    return $status === 'aktif'
        ? '<span class="tag ok">Aktif</span>'
        : '<span class="tag no">Nonaktif</span>';
}

/** Ikon SVG kecil (stroke mengikuti warna teks). */
function icon(string $name): string
{
    $p = [
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'edit'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'  => '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'upload' => '<path d="M12 16V4"/><path d="m7 9 5-5 5 5"/><path d="M5 20h14"/>',
        'download' => '<path d="M12 4v12"/><path d="m7 11 5 5 5-5"/><path d="M5 20h14"/>',
        'room'     => '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="15" cy="12" r="1"/>',
        'box'      => '<path d="M21 8 12 3 3 8v8l9 5 9-5Z"/><path d="m3 8 9 5 9-5M12 13v8"/>',
        'swap'     => '<path d="M7 7h13l-3-3"/><path d="M17 17H4l3 3"/>',
        'alert'    => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 2.4 18a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/>',
        'check'    => '<path d="m5 12 5 5L20 7"/>',
        'wrench'   => '<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4Z"/>',
        'building' => '<rect x="4" y="3" width="16" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M10 21v-4h4v4"/>',
        'logout'   => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17-5-5 5-5M5 12h12"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
        'left'   => '<path d="m15 18-6-6 6-6"/>',
        'right'  => '<path d="m9 18 6-6-6-6"/>',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}

/** Pagination bernomor gaya Master Data: ‹ 1 2 3 … 8 › */
function pagination_compact(array $pg, array $query, string $noun = 'data', ?string $leftHtml = null): string
{
    $query = array_filter($query, fn($v) => $v !== '' && $v !== 0 && $v !== null);
    $link  = fn(int $p) => '?' . http_build_query($query + ['page' => $p]);
    $last  = $pg['pages'];
    $cur   = $pg['page'];

    $kiri = $leftHtml ?? '<span>Menampilkan ' . $pg['from'] . '-' . $pg['to']
          . ' dari ' . number_format($pg['total'], 0, ',', '.') . ' ' . e($noun) . '</span>';
    $html = '<div class="pg-foot">' . $kiri . '<div class="pg-btns">';

    $html .= $cur > 1
        ? '<a class="pn" aria-label="Sebelumnya" href="' . e($link($cur - 1)) . '">' . icon('left') . '</a>'
        : '<span class="pn off">' . icon('left') . '</span>';

    $set = array_unique(array_filter([1, $cur - 1, $cur, $cur + 1, $last], fn($n) => $n >= 1 && $n <= $last));
    sort($set);
    $prev = 0;
    foreach ($set as $n) {
        if ($n - $prev > 1) $html .= '<span class="pn-gap">…</span>';
        $html .= '<a class="pn' . ($n === $cur ? ' on' : '') . '" href="' . e($link($n)) . '">' . $n . '</a>';
        $prev = $n;
    }

    $html .= $cur < $last
        ? '<a class="pn" aria-label="Berikutnya" href="' . e($link($cur + 1)) . '">' . icon('right') . '</a>'
        : '<span class="pn off">' . icon('right') . '</span>';

    return $html . '</div></div>';
}
