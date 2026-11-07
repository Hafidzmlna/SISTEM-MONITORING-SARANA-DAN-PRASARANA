<?php
// Variabel yang diisi halaman sebelum include: $page_title, $active
$hdrUser  = current_user();
$hdrMenu  = menu_for_role($hdrUser['role_kode']);
$hdrFlash = flash_get();
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(($page_title ?? 'Dashboard') . ' – ' . APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><span class="logo">S</span><span><?= e(APP_NAME) ?></span>
      <button type="button" class="sb-close" data-close-sidebar aria-label="Tutup menu"><?= icon('close') ?></button></div>
    <nav>
      <?php foreach ($hdrMenu as [$mKey, $mLabel, $mPath]): ?>
        <a class="nav<?= ($active ?? '') === $mKey ? ' on' : '' ?>" href="<?= e(url($mPath)) ?>"><?= e($mLabel) ?></a>
      <?php endforeach; ?>
    </nav>
    <form method="post" action="<?= e(url('/modules/auth/logout.php')) ?>" class="logout">
      <?= csrf_field() ?>
      <button type="submit" class="nav" data-open-dialog="dlg-keluar">Keluar</button>
    </form>
    <dialog id="dlg-keluar" class="dlg" aria-labelledby="dlg-keluar-judul">
      <div class="dlg-ic"><?= icon('logout') ?></div>
      <h1 id="dlg-keluar-judul">Keluar dari <?= e(APP_NAME) ?>?</h1>
      <p>Anda akan keluar dari akun <?= e($hdrUser['role_nama']) ?> dan perlu masuk kembali untuk mengakses sistem.</p>
      <form method="post" action="<?= e(url('/modules/auth/logout.php')) ?>">
        <?= csrf_field() ?>
        <div class="dlg-act">
          <button type="button" class="btn sec" data-close-dialog>Batal</button>
          <button type="submit" class="btn">Ya, Keluar</button>
        </div>
      </form>
    </dialog>
  </aside>

  <div class="sb-overlay" data-close-sidebar></div>

  <div class="main">
    <header class="topbar">
      <button type="button" class="burger" data-toggle-sidebar aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><?= icon('menu') ?></button>
      <div class="tb-text">
        <h1>Selamat Datang, <?= e($hdrUser['nama']) ?></h1>
        <p><?= e(hari_tanggal()) ?></p>
      </div>
      <div class="user">
        <?php if (!empty($hdrUser['foto'])): ?><img class="avatar" alt="" src="<?= e(url('/modules/akun/foto.php') . '?v=' . substr($hdrUser['foto'], 0, 8)) ?>"><?php else: ?><span class="avatar"><?= e(inisial($hdrUser['nama'])) ?></span><?php endif; ?>
        <span><strong><?= e($hdrUser['nama']) ?></strong><small><?= e($hdrUser['role_nama']) ?></small></span>
      </div>
    </header>
    <main class="content">
      <?php if ($hdrFlash): ?>
        <div class="alert <?= e($hdrFlash['type']) ?>"><?= e($hdrFlash['message']) ?></div>
      <?php endif; ?>
