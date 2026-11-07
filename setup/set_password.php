<?php
// Hanya untuk terminal. Contoh:
//   php setup/set_password.php SA001 "PasswordKuat123!"
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/database.php';

[$self, $nim, $pass] = $argv + [null, null, null];
if (!$nim || !$pass) { fwrite(STDERR, "Pemakaian: php setup/set_password.php <nim_nip> <password_baru>\n"); exit(1); }
if (strlen($pass) < 8) { fwrite(STDERR, "Password minimal 8 karakter.\n"); exit(1); }

$st = db()->prepare('UPDATE users SET password = ? WHERE nim_nip = ?');
$st->execute([password_hash($pass, PASSWORD_DEFAULT), $nim]);
echo $st->rowCount() ? "Password untuk {$nim} berhasil diubah.\n" : "User dengan NIM/NIP {$nim} tidak ditemukan.\n";
