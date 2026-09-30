<?php
/**
 * Halaman Backup Database.
 *
 * Membuat berkas .sql berisi seluruh isi database, lalu bisa diunduh.
 * Berkas disimpan di folder /backups yang tidak dapat diakses langsung lewat
 * browser (lihat .htaccess di sana). Pengunduhan dilakukan lewat skrip ini,
 * sehingga tetap aman dan hanya bisa dilakukan admin yang sudah login.
 *
 * Cara ini tidak bergantung pada mysqldump atau exec(), sehingga tetap
 * berjalan di hosting yang menutup fungsi tersebut.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

/** Folder penyimpanan backup. */
define('BACKUP_DIR', APP_ROOT . '/backups');

/** Jumlah backup lama yang dipertahankan sebelum yang paling lama dihapus. */
define('MAX_KEEP', 15);

/**
 * Susun dump .sql dari seluruh tabel di database.
 */
function db_dump(): string
{
    $pdo = db();

    $out  = "-- ==================================================\n";
    $out .= "-- Backup Moma Bread\n";
    $out .= '-- Dibuat  : ' . date('Y-m-d H:i:s') . "\n";
    $out .= '-- Database: ' . DB_NAME . "\n";
    $out .= "-- ==================================================\n\n";
    $out .= "SET NAMES utf8mb4;\n";
    $out .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        // WAJIB PDO::FETCH_NUM: default fetch mode aplikasi ini adalah FETCH_ASSOC,
        // sehingga indeks angka $create[1] tidak akan ada.
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        $out   .= "DROP TABLE IF EXISTS `$table`;\n" . $create[1] . ";\n\n";
    }

    foreach ($tables as $table) {
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        $out .= "-- Data tabel `$table`\n";
        $out .= "INSERT INTO `$table` VALUES\n";

        $values = [];

        foreach ($rows as $row) {
            $cells = [];

            foreach ($row as $value) {
                $cells[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
            }

            $values[] = '(' . implode(', ', $cells) . ')';
        }

        $out .= ($values ? implode(",\n", $values) : '') . ";\n\n";
    }

    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";

    return $out;
}

/**
 * Daftar berkas backup, terbaru lebih dulu.
 *
 * @return array<int, array{name:string,size:int,time:int}>
 */
function backup_files(): array
{
    if (!is_dir(BACKUP_DIR)) {
        return [];
    }

    $out = [];

    foreach (glob(BACKUP_DIR . '/backup-*.sql') ?: [] as $file) {
        $out[] = [
            'name' => basename($file),
            'size' => (int) filesize($file),
            'time' => (int) filemtime($file),
        ];
    }

    usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);

    return $out;
}

/**
 * Hapus backup lama, sisakan MAX_KEEP berkas terbaru.
 */
function backup_prune(): void
{
    foreach (array_slice(backup_files(), MAX_KEEP) as $file) {
        @unlink(BACKUP_DIR . '/' . $file['name']);
    }
}

/* ==================================================================
 |  PROSES POST
 * ================================================================== */
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $do = (string) ($_POST['do'] ?? '');

    // ---- Buat backup baru -------------------------------------------
    if ($do === 'create') {
        if (!is_dir(BACKUP_DIR) && !@mkdir(BACKUP_DIR, 0755, true) && !is_dir(BACKUP_DIR)) {
            $errors[] = 'Folder backup tidak dapat dibuat. Periksa izin tulis.';
        } else {
            $path  = BACKUP_DIR . '/backup-' . date('Ymd-His') . '.sql';
            $bytes = @file_put_contents($path, db_dump());

            if ($bytes === false) {
                $errors[] = 'Gagal menulis berkas backup. Periksa izin tulis folder.';
            } else {
                backup_prune();
                flash('success', 'Backup berhasil dibuat (' . size_format($bytes) . ').');
            }
        }

        redirect('admin/backup.php');
    }

    // ---- Hapus backup -----------------------------------------------
    if ($do === 'delete') {
        $name = basename((string) ($_POST['name'] ?? ''));

        // basename() + pemeriksaan awalan/akhiran mencegah path traversal.
        if (strpos($name, 'backup-') === 0 && str_ends_with($name, '.sql')) {
            @unlink(BACKUP_DIR . '/' . $name);
            flash('success', 'Backup ' . $name . ' dihapus.');
        }

        redirect('admin/backup.php');
    }
}

/* ==================================================================
 |  UNDUH BACKUP
 |  Dievaluasi sebelum output HTML agar header terkirim dengan benar.
 * ================================================================== */
if (($_GET['download'] ?? '') !== '') {
    $name = basename((string) $_GET['download']);

    if (strpos($name, 'backup-') !== 0 || !str_ends_with($name, '.sql')) {
        http_response_code(404);
        exit('Berkas tidak ditemukan.');
    }

    $path = BACKUP_DIR . '/' . $name;

    if (!is_file($path)) {
        http_response_code(404);
        exit('Berkas tidak ditemukan.');
    }

    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

$files      = backup_files();
$totalBytes = array_sum(array_column($files, 'size'));
$installAda = is_file(APP_ROOT . '/install.php');

$pageTitle = 'Backup Database';
$activeTab = 'backup';
require __DIR__ . '/_partials/header.php';
?>

<?php if ($errors): ?>
  <div class="alert error">
    <?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($installAda): ?>
  <div class="alert error">
    <strong>Installer masih ada.</strong> Untuk keamanan, hapus berkas
    <code>install.php</code> dan <code>install.lock</code> sebelum aplikasi dipublikasikan,
    supaya tidak ada orang lain yang bisa memasang ulang database Anda.
  </div>
<?php endif; ?>

<div class="toolbar">
  <div class="count">
    <?= e((string) count($files)) ?> berkas backup<?= $totalBytes > 0 ? ' &middot; total ' . e(size_format($totalBytes)) : '' ?>
  </div>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <button class="btn" type="submit">Buat Backup Sekarang</button>
  </form>
</div>

<section class="panel">
  <div class="panelhead"><h2>Daftar Backup</h2></div>

  <?php if (!$files): ?>
    <div class="panelbody">
      <p class="muted">Belum ada backup. Klik "Buat Backup Sekarang" untuk membuat yang pertama.</p>
    </div>
  <?php else: ?>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr><th>Nama Berkas</th><th>Dibuat</th><th>Ukuran</th><th class="right">Aksi</th></tr>
        </thead>
        <tbody>
          <?php foreach ($files as $file): ?>
            <tr>
              <td><code><?= e($file['name']) ?></code></td>
              <td><?= e(tgl(date('Y-m-d H:i:s', $file['time']))) ?></td>
              <td><?= e(size_format($file['size'])) ?></td>
              <td class="right nowrap">
                <a class="btn ghost xs" href="<?= e(url('admin/backup.php?download=' . rawurlencode($file['name']))) ?>">Unduh</a>

                <form method="post" class="inline" onsubmit="return confirm('Hapus backup ini?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="do" value="delete">
                  <input type="hidden" name="name" value="<?= e($file['name']) ?>">
                  <button class="btn danger xs" type="submit">Hapus</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="panel">
  <div class="panelhead"><h2>Cara Memulihkan (Restore)</h2></div>
  <div class="panelbody">
    <ol class="steps">
      <li>Buka <strong>phpMyAdmin</strong> di <code>http://localhost/phpmyadmin</code>.</li>
      <li>Pilih database <code><?= e(DB_NAME) ?></code>, lalu klik tab <strong>Import</strong>.</li>
      <li>Pilih berkas <code>.sql</code> hasil unduhan backup, lalu klik <strong>Go</strong>.</li>
      <li>Tabel lama akan ditimpa dengan isi backup tersebut.</li>
    </ol>
    <p class="muted">
      Sebaiknya buat backup secara berkala, lalu simpan berkasnya di luar server
      (misalnya di Google Drive atau flashdisk).
    </p>
  </div>
</section>

<?php require __DIR__ . '/_partials/footer.php'; ?>

