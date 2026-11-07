<?php
// Tujuan role yang belum punya dashboard (mis. role baru buatan Superadmin).
require __DIR__ . '/../../core/bootstrap.php';
$u = require_login();

$page_title = 'Beranda'; $active = '';
require __DIR__ . '/../../layouts/header.php';
?>
<section class="panel">
  <div class="ph"><h2>Akun Anda belum memiliki halaman</h2></div>
  <p class="muted">Role <strong><?= e($u['role_nama']) ?></strong> belum dihubungkan ke dashboard. Hubungi administrator sistem.</p>
</section>
<?php require __DIR__ . '/../../layouts/footer.php'; ?>
