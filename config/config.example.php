<?php
/**
 * TEMPLATE konfigurasi Moma Bread.
 * ---------------------------------------------------------------------
 * File ini BOLEH masuk ke repository git karena tidak berisi kredensial
 * asli. File yang dipakai aplikasi adalah config/config.php, yang
 * sengaja tidak ikut ter-commit (lihat .gitignore).
 *
 * Cara memakai:
 *   1. Salin file ini menjadi  config/config.php
 *   2. Ganti nilai pada bagian "1. KONEKSI DATABASE" dengan data server Anda
 *   3. Selesai - seluruh isi file lain sama persis dengan config.php
 *
 * @package MomaBread
 */

/* =====================================================================
 *  1. KONEKSI DATABASE
 *
 *  Ambil semua nilai ini dari cPanel TinkerHost:
 *    cPanel -> MySQL Databases
 *      - "Database Server"  -> DB_HOST
 *      - Database name      -> DB_NAME   (yang Anda buat sendiri)
 *      - MySQL username     -> DB_USER
 *      - MySQL password     -> DB_PASS
 *
 *  PENTING - host MySQL TinkerHost bentuknya:
 *      sqlxxx.thsite.top
 *  BUKAN "localhost" dan BUKAN "127.0.0.1". Mengisi localhost akan
 *  membuat situs gagal konek.
 *
 *  Nama database dan user biasanya berawalan "thsi_".
 *  Buat keduanya lewat cPanel, jangan menebak.
 * =================================================================== */
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'moma_bread');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/* =====================================================================
 *  2. PATH & URL
 *  (bagian ini tidak perlu diubah, identik dengan config.php)
 * =================================================================== */

/** Folder fisik aplikasi (tanpa trailing slash). */
define('APP_ROOT', dirname(__DIR__));

/** Lokasi penyimpanan file upload. */
define('UPLOAD_DIR', APP_ROOT . '/assets/img/uploads');

/** URL folder upload ( relatif terhadap BASE_URL ). */
define('UPLOAD_PATH', 'assets/img/uploads');

/** Ukuran maksimal satu file upload: 2 MB. */
define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024);

/**
 * BASE_URL - URL dasar aplikasi, otomatis mengikuti lokasi instalasi,
 * sehingga tetap jalan di root domain maupun di sub-folder.
 */
if (!defined('BASE_URL')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $dir    = rtrim(str_replace('\\', '/', dirname($script)), '/');

    // Bila file yang sedang jalan berada di /config atau /admin, naik satu level.
    if (preg_match('#/(config|admin)$#', $dir)) {
        $pos = strrpos($dir, '/');
        $dir = $pos === false ? '' : substr($dir, 0, $pos);
    }

    define('BASE_URL', ($dir === '' || $dir === '.') ? '' : $dir);
}

/* =====================================================================
 *  3. PENGATURAN APLIKASI
 * =================================================================== */

/** Mode pengembangan: true = tampilkan pesan error detail. */
define('APP_DEBUG', false);

/** Zona waktu aplikasi. */
define('APP_TIMEZONE', 'Asia/Jakarta');

/** Jumlah item per halaman pada daftar admin. */
define('ADMIN_PER_PAGE', 20);

/**
 * URL bersih untuk level 2, contoh: /moma_bread/menu/signature
 * Bila hosting tidak mendukung mod_rewrite, setel false - tautan akan
 * otomatis memakai menu.php?kategori=... dan tetap berjalan normal.
 */
define('PRETTY_MENU_URL', true);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}

date_default_timezone_set(APP_TIMEZONE);
