<?php
/**
 * Partial form akun admin.
 * Dipakai oleh install.php, sehingga TIDAK boleh punya output HTML di luar field.
 *
 * @var array $errors Daftar pesan galat.
 */
?>
<label for="username">Username</label>
<input type="text" id="username" name="username" value="<?= e((string) ($_POST['username'] ?? 'admin')) ?>"
       required minlength="3" maxlength="60" autocomplete="username" placeholder="admin">

<label for="full_name">Nama lengkap</label>
<input type="text" id="full_name" name="full_name" value="<?= e((string) ($_POST['full_name'] ?? '')) ?>"
       maxlength="120" placeholder="Admin Moma Bread">

<label for="password">Password</label>
<input type="password" id="password" name="password" required minlength="6" autocomplete="new-password"
       placeholder="Minimal 6 karakter">

<label for="password2">Ulangi password</label>
<input type="password" id="password2" name="password2" required minlength="6" autocomplete="new-password"
       placeholder="Tulis ulang password di atas">
