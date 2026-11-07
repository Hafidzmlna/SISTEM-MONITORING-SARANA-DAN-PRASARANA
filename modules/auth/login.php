<?php
require __DIR__ . '/../../core/bootstrap.php';

if ($u = current_user()) redirect(role_home($u['role_kode']));

$error    = null;
$identity = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $identity = trim($_POST['identity'] ?? '');
    [$ok, $msg] = login_attempt($identity, (string) ($_POST['password'] ?? ''));
    if ($ok) redirect(role_home(current_user()['role_kode']));
    $error = $msg;
}

// Jika file logo ada (assets/img/Logo-Baru-IKIP-PGR-Bojonegoro-1-1.png), tampilkan; jika tidak, pakai kotak placeholder.
$logoFile = __DIR__ . '/../../assets/img/Logo-Baru-IKIP-PGR-Bojonegoro-1-1.png';
$punyaLogo = is_file($logoFile);
?><!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login – <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= e(asset('/assets/css/app.css')) ?>">
</head>
<body class="login-page">
  <section class="login-left">
    <div class="login-title"><?= e(APP_NAME) ?></div>
    <?php if ($punyaLogo): ?>
      <img class="login-logo" src="<?= e(url('/assets/img/Logo-Baru-IKIP-PGR-Bojonegoro-1-1.png')) ?>" alt="Logo <?= e(APP_NAME) ?>">
    <?php else: ?>
      <div class="login-logo placeholder">Logo</div>
    <?php endif; ?>
    <div class="login-tagline">Sistem Monitoring Sarana dan Prasarana</div>
  </section>

  <section class="login-right">
    <div class="login-box">
      <div>
        <h1>Masuk</h1>
        <p>Silakan masuk untuk melanjutkan ke <?= e(APP_NAME) ?>.</p>
      </div>

      <?php if ($error): ?><div class="alert bad"><?= e($error) ?></div><?php endif; ?>

      <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <div class="fld">
          <label for="identity">Email atau NIM/NIP</label>
          <input class="in" id="identity" name="identity" type="text" value="<?= e($identity) ?>"
                 placeholder="Masukkan email atau NIM/NIP" required autofocus>
        </div>
        <div class="fld">
          <label for="password">Kata Sandi</label>
          <input class="in" id="password" name="password" type="password"
                 placeholder="Masukkan kata sandi" required>
        </div>
        <button class="btn block" type="submit">Masuk</button>
      </form>
    </div>
  </section>
</body>
</html>
