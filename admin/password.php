<?php
/**
 * Halaman ganti password admin.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

$errors  = [];
$current = current_user();

/**
 * Hash password diambil terpisah, bukan dari current_user(),
 * supaya hash tidak ikut terbawa di setiap halaman admin.
 */
$passwordHash = (string) val('SELECT password FROM admin_users WHERE id = ?', [(int) ($current['id'] ?? 0)], '');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();

    $lama  = (string) ($_POST['password_lama'] ?? '');
    $baru  = (string) ($_POST['password_baru'] ?? '');
    $ulang = (string) ($_POST['password_ulang'] ?? '');

    // Password lama harus benar.
    if ($passwordHash === '' || !password_verify($lama, $passwordHash)) {
        $errors[] = 'Password lama salah.';
    }

    if (strlen($baru) < 8) {
        $errors[] = 'Password baru minimal 8 karakter.';
    } elseif ($baru === $lama) {
        $errors[] = 'Password baru harus berbeda dari password lama.';
    }

    if ($baru !== $ulang) {
        $errors[] = 'Ulangi password tidak sama.';
    }

    if (!$errors) {
        q('UPDATE admin_users SET password = ? WHERE id = ?', [
            password_hash($baru, PASSWORD_DEFAULT),
            (int) $current['id'],
        ]);

        flash('success', 'Password berhasil diubah. Gunakan password baru saat login berikutnya.');
        redirect('admin/password.php');
    }
}

$pageTitle = 'Ganti Password';
$activeTab = 'password';
require __DIR__ . '/_partials/header.php';
?>

<?php if ($errors): ?>
  <div class="alert error">
    <strong>Gagal mengubah password:</strong>
    <ul>
      <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="grid2">
  <section class="panel">
    <div class="panelhead"><h2>Ganti Password Anda</h2></div>
    <div class="panelbody">
      <form method="post" class="form" autocomplete="off">
        <?= csrf_field() ?>

        <div class="field">
          <label for="password_lama">Password saat ini</label>
          <input type="password" id="password_lama" name="password_lama" required autocomplete="current-password">
        </div>

        <div class="field">
          <label for="password_baru">Password baru</label>
          <input type="password" id="password_baru" name="password_baru" required minlength="8" autocomplete="new-password">
          <small class="hint">Minimal 8 karakter. Campur huruf, angka, dan tanda baca.</small>
        </div>

        <div class="field">
          <label for="password_ulang">Ulangi password baru</label>
          <input type="password" id="password_ulang" name="password_ulang" required minlength="8" autocomplete="new-password">
        </div>

        <div class="formfoot">
          <button class="btn" type="submit">Simpan Password Baru</button>
          <a class="btn ghost" href="<?= e(url('admin/index.php')) ?>">Batal</a>
        </div>
      </form>
    </div>
  </section>

  <section class="panel">
    <div class="panelhead"><h2>Tips Keamanan</h2></div>
    <div class="panelbody">
      <ul class="infolist">
        <li><strong>Jangan pakai password bawaan</strong><span>admin123 hanya untuk saat instalasi</span></li>
        <li><strong>Panjang lebih penting</strong><span>Minimal 8 karakter, idealnya 12 karakter atau lebih</span></li>
        <li><strong>Jangan dipakai ulang</strong><span>Berbeda dari akun email atau media sosial Anda</span></li>
        <li><strong>Cadangkan data</strong><span>Rutin buat backup di menu Backup Database</span></li>
        <li><strong>Hapus installer</strong><span>Hapus install.php sebelum aplikasi online</span></li>
      </ul>
    </div>
  </section>
</div>

<?php require __DIR__ . '/_partials/footer.php'; ?>
