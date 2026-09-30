<?php
/**
 * Halaman login panel admin.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';

// Halaman login ikut diberi header anti-cache agar tidak menampilkan
// pesan galat atau form lama dari salinan browser.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Sudah login? Langsung ke dashboard.
if (is_logged_in()) {
    redirect('admin/index.php');
}

$error = '';
$username = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $admin = one('SELECT id, username, password FROM admin_users WHERE username = ?', [$username]);

        // password_verify() selalu dijalankan agar waktu respons seragam.
        $valid = $admin !== null && password_verify($password, (string) $admin['password']);

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin_id']  = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];

            q('UPDATE admin_users SET last_login = NOW() WHERE id = ?', [(int) $admin['id']]);

            redirect('admin/index.php');
        }

        $error = 'Username atau password salah.';
    }
}

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk - Admin Moma Bread</title>
<link rel="icon" href="<?= e(asset('img/logo.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="login">

<div class="loginbox">
  <img src="<?= e(asset('img/logo.png')) ?>" alt="Moma Bread" class="loginlogo">
  <h1>Masuk Panel Admin</h1>
  <p class="muted">Gunakan akun yang dibuat saat instalasi.</p>

  <?php foreach ($flashes as $f): ?>
    <div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>

  <?php if ($error !== ''): ?>
    <div class="alert error"><?= e($error) ?></div>
  <?php endif; ?>

  <form method="post" autocomplete="on">
    <?= csrf_field() ?>

    <label for="username">Username</label>
    <input type="text" id="username" name="username" value="<?= e($username) ?>"
           required autocomplete="username" autofocus>

    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">

    <button class="btn block" type="submit">Masuk</button>
  </form>

  <a class="muted" href="<?= e(url('index.php')) ?>">← Kembali ke landing page</a>
</div>

</body>
</html>
