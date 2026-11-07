<?php
require __DIR__ . '/../../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('/');
csrf_check();
logout_user();
redirect('/modules/auth/login.php');
