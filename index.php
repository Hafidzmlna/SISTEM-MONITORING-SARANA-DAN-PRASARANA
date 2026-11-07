<?php
require __DIR__ . '/core/bootstrap.php';

$u = current_user();
redirect($u ? role_home($u['role_kode']) : '/modules/auth/login.php');
