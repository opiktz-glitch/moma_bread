<?php
/**
 * Halaman Backup Gambar.
 *
 * Membuat berkas .zip berisi seluruh gambar di /assets/img (termasuk hasil
 * upload), lalu bisa diunduh dari sini. Berkas zip disimpan di /backups/img
 * yang tidak bisa diakses langsung lewat browser (diletakkan di dalam folder
 * /backups yang sudah dilindungi .htaccess).
 *
 * Hanya admin yang sudah login yang bisa membuat, mengunduh, dan menghapus.
 *
 * Memakai ekstensi ZipArchive. Bila ekstensi tersebut tidak tersedia di
 * hosting, halaman ini akan memberi tahu, bukan gagal diam-diam.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

/** Folder sumber gambar. */
define('IMG_SRC_DIR', APP_ROOT . '/assets/img');

/** Folder penyimpanan zip. Letakkan di dalam /backups agar ikut terlindungi. */
define('IMG_BACKUP_DIR', APP_ROOT . '/backups/img');

/** Jumlah zip lama yang dipertahankan. */
define('IMG_MAX_KEEP', 10);

/** Hanya nama file dengan pola ini yang boleh diunduh/dihapus. */
const IMG_BACKUP_PATTERN = '/^gambar-\d{8}-\d{6}\.zip$/';

/** Ekstensi gambar yang ikut dibackup. */
const IMG_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'ico'];

/**
 * Kumpulkan seluruh file gambar di dalam $dir, relatif terhadap $dir.
 *
 * Mengembalikan array path relatif. Subfolder ikut dijaga agar struktur
 * uploads/ tetap utuh saat dipulihkan.
 *
 * @return array<int, string>
 */
function img_collect(string $dir, string $prefix = ''): array
{
    $out = [];

    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $full = $dir . '/' . $entry;
        $rel  = $prefix === '' ? $entry : $prefix . '/' . $entry;

        if (is_dir($full)) {
            $out = array_merge($out, img_collect($full, $rel));
            continue;
        }

        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));

        if (is_file($full) && in_array($ext, IMG_EXTENSIONS, true)) {
            $out[] = $rel;
        }
    }

    sort($out);

    return $out;
}

/**
 * Buat zip baru berisi seluruh gambar.
 *
 * @return array{0:?string,1:int,2:int,3:?string} [berkas, byte, jumlah, pesan galat]
 */
function img_create_zip(): array
{
    if (!class_exists('ZipArchive')) {
        return [null, 0, 0, 'Ekstensi ZipArchive tidak tersedia di server ini, '
            . 'sehingga backup gambar tidak bisa dibuat.'];
    }

    if (!is_dir(IMG_SRC_DIR)) {
        return [null, 0, 0, 'Folder gambar tidak ditemukan: assets/img'];
    }

    $files = img_collect(IMG_SRC_DIR);

    if (!$files) {
        return [null, 0, 0, 'Tidak ada file gambar di assets/img untuk dibackup.'];
    }

    if (!is_dir(IMG_BACKUP_DIR) && !@mkdir(IMG_BACKUP_DIR, 0755, true) && !is_dir(IMG_BACKUP_DIR)) {
        return [null, 0, 0, 'Folder backup gambar tidak dapat dibuat. Periksa izin tulis.'];
    }

    $name = 'gambar-' . date('Ymd-His') . '.zip';
    $path = IMG_BACKUP_DIR . '/' . $name;

    $zip = new ZipArchive();

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return [null, 0, 0, 'Gagal membuat berkas zip. Periksa izin tulis folder backup.'];
    }

    $bytes = 0;

    foreach ($files as $rel) {
        $full = IMG_SRC_DIR . '/' . $rel;
        // Disimpan sebagai gambar/<rel> supaya isi zip mudah dikenali.
        $zip->addFile($full, 'gambar/' . $rel);
        $bytes += (int) filesize($full);
    }

    // Manifest singkat agar isi backup jelas saat diekstrak di komputer lain.
    $manifest = "BACKUP GAMBAR - Moma Bread\n"
        . 'Dibuat  : ' . date('Y-m-d H:i:s') . "\n"
        . 'Sumber  : assets/img' . "\n"
        . 'Jumlah  : ' . count($files) . " file\n\n"
        . "CARA MEMULIHKAN:\n"
        . "1. Ekstrak file ini.\n"
        . "2. Salin isi folder gambar\ ke folder assets\img\ milik website (pilih Replace).\n"
        . "3. Folder uploads\ di dalam backup sudah sesuai dengan aslinya.\n\n"
        . "DAFTAR FILE:\n";
    foreach ($files as $rel) {
        $manifest .= '  ' . $rel . "\n";
    }
    $zip->addFromString('MANIFEST.txt', $manifest);

    if (!$zip->close()) {
        return [null, 0, 0, 'Gagal menyimpan berkas zip. Periksa izin tulis folder backup.'];
    }

    return [$path, $bytes, count($files), null];
}

/**
 * Daftar zip backup gambar, terbaru lebih dulu.
 *
 * @return array<int, array{name:string,size:int,time:int}>
 */
function img_backup_files(): array
{
    if (!is_dir(IMG_BACKUP_DIR)) {
        return [];
    }

    $out = [];

    foreach (glob(IMG_BACKUP_DIR . '/gambar-*.zip') ?: [] as $file) {
        $out[] = [
            'name' => basename($file),
            'size' => (int) filesize($file),
            'time' => (int) filemtime($file),
        ];
    }

    usort($out, static fn ($a, $b) => $b['time'] <=> $a['time']);

    return $out;
}

/** Hapus zip lama, sisakan IMG_MAX_KEEP terbaru. */
function img_backup_prune(): void
{
    foreach (array_slice(img_backup_files(), IMG_MAX_KEEP) as $file) {
        @unlink(IMG_BACKUP_DIR . '/' . $file['name']);
    }
}

/**
 * Pastikan nama file aman dan benar-benar berada di dalam folder backup.
 *
 * Mencegah path traversal lewat parameter ?download= atau name=.
 */
function img_safe_path(string $name): ?string
{
    $name = basename($name);

    if (!preg_match(IMG_BACKUP_PATTERN, $name)) {
        return null;
    }

    $path   = IMG_BACKUP_DIR . '/' . $name;
    $real   = realpath($path);
    $realDir = realpath(IMG_BACKUP_DIR);

    if ($real === false || $realDir === false) {
        return null;
    }

    // Harus berada tepat di dalam folder backup.
    if (strncmp($real, $realDir . DIRECTORY_SEPARATOR, strlen($realDir) + 1) !== 0) {
        return null;
    }

    return $real;
}

/* =====================================================================
 *  PROSES POST
 * ===================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $do = (string) ($_POST['do'] ?? '');

    // ---- Buat backup gambar baru -------------------------------------
    if ($do === 'create') {
        [$path, $bytes, $jml, $err] = img_create_zip();

        if ($err !== null) {
            flash('error', $err);
        } else {
            img_backup_prune();
            flash('success', 'Backup gambar berhasil dibuat: ' . basename($path)
                . ' (' . $jml . ' file, ' . size_format((int) filesize($path)) . ').');
        }

        redirect('admin/backup-gambar.php');
    }

    // ---- Hapus backup gambar ------------------------------------------
    if ($do === 'delete') {
        $path = img_safe_path((string) ($_POST['name'] ?? ''));

        if ($path !== null) {
            @unlink($path);
            flash('success', 'Backup gambar dihapus.');
        }

        redirect('admin/backup-gambar.php');
    }
}

/* =====================================================================
 *  UNDUH BACKUP GAMBAR
 *  Dievaluasi sebelum output HTML agar header terkirim dengan benar.
 * ===================================================================== */
if (($_GET['download'] ?? '') !== '') {
    $path = img_safe_path((string) $_GET['download']);

    if ($path === null) {
        http_response_code(404);
        exit('Berkas tidak ditemukan.');
    }

    // Bersihkan buffer agar header tidak dianggap sudah terkirim.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $name = basename($path);

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: no-store');
    readfile($path);
    exit;
}

$files      = img_backup_files();
$totalBytes = array_sum(array_column($files, 'size'));
$srcFiles   = is_dir(IMG_SRC_DIR) ? img_collect(IMG_SRC_DIR) : [];
$srcBytes   = 0;
foreach ($srcFiles as $rel) {
    $srcBytes += (int) filesize(IMG_SRC_DIR . '/' . $rel);
}
$zipTersedia = class_exists('ZipArchive');
$installAda  = is_file(APP_ROOT . '/install.php');

$pageTitle = 'Backup Gambar';
$activeTab = 'backup-gambar';
require __DIR__ . '/_partials/header.php';
?>

<?php if ($installAda): ?>
  <div class="alert error">
    <strong>Installer masih ada.</strong> Untuk keamanan, hapus berkas
    <code>install.php</code> dan <code>install.lock</code> sebelum aplikasi dipublikasikan,
    supaya tidak ada orang lain yang bisa memasang ulang database Anda.
  </div>
<?php endif; ?>

<?php if (!$zipTersedia): ?>
  <div class="alert error">
    <strong>Ekstensi ZipArchive tidak aktif.</strong> Server ini tidak punya
    <code>ZipArchive</code>, jadi backup gambar belum bisa dibuat. Fitur lain
    tetap berjalan normal.
  </div>
<?php endif; ?>

<div class="toolbar">
  <div class="count">
    Di folder <code>assets/img</code> ada <strong><?= e((string) count($srcFiles)) ?></strong>
    gambar (<?= e(size_format($srcBytes)) ?>)
    <?= $files ? '&middot; ' . count($files) . ' zip backup'
              . ($totalBytes > 0 ? ', total ' . e(size_format($totalBytes)) : '') : '' ?>
  </div>

  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create">
    <button class="btn" type="submit" <?= $zipTersedia ? '' : 'disabled' ?>>
      Buat Backup Gambar
    </button>
  </form>
</div>

<section class="panel">
  <div class="panelhead"><h2>Daftar Backup Gambar</h2></div>

  <?php if (!$files): ?>
    <div class="panelbody">
      <p class="muted">
        Belum ada backup gambar. Klik "Buat Backup Gambar" untuk membuat yang pertama.
        Berkas .zip akan bisa diunduh langsung dari tabel di bawah.
      </p>
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
                <a class="btn ghost xs"
                   href="<?= e(url('admin/backup-gambar.php?download=' . rawurlencode($file['name']))) ?>">
                  Unduh
                </a>

                <form method="post" class="inline" onsubmit="return confirm('Hapus backup gambar ini?');">
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
      <li>Unduh file <code>.zip</code> di atas.</li>
      <li>Ekstrak di komputer. Isinya ada di folder <code>gambar\</code>.</li>
      <li>Buka folder <code>gambar\</code>, lalu salin <strong>seluruh isinya</strong>
          ke folder <code>assets/img</code> website Anda dan pilih <strong>Replace</strong>.</li>
      <li>Folder <code>uploads\</code> sudah terpisah di dalam zip sesuai struktur aslinya,
          jadi tidak perlu dipindah manual.</li>
    </ol>
    <p class="muted">
      Backup menyimpan maksimal <?= (int) IMG_MAX_KEEP ?> zip terbaru; yang lebih lama
      otomatis dihapus. Untuk hemat ruang, zip berisi file gambar saja — bukan database.
      Untuk backup database, gunakan halaman <a href="<?= e(url('admin/backup.php')) ?>">Backup Database</a>.
    </p>
  </div>
</section>

<?php require __DIR__ . '/_partials/footer.php'; ?>
