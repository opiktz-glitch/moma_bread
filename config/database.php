<?php
/**
 * Koneksi database (PDO) untuk aplikasi Moma Bread.
 *
 * Fungsi yang tersedia:
 *  - db()          -> koneksi ke database aplikasi (moma_bread)
 *  - db_server()   -> koneksi ke server MySQL tanpa memilih database
 *                    (dipakai install.php untuk membuat database)
 *
 * Semua query di aplikasi ini wajib memakai prepared statement.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/config.php';

/**
 * Buat objek PDO ke server MySQL.
 *
 * @param  string|null $database Nama database, null = tanpa memilih database.
 * @return PDO
 * @throws PDOException
 */
function pdo_connect(?string $database = DB_NAME): PDO
{
    $dsn = $database === null
        ? sprintf('mysql:host=%s;port=%s;charset=%s', DB_HOST, DB_PORT, DB_CHARSET)
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, $database, DB_CHARSET);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

/**
 * Tampilkan halaman error yang informatif saat koneksi database gagal.
 *
 * Kenapa perlu: di hosting, display_errors dimatikan. Bila kredensial pada
 * config/config.php salah, tanpa halaman ini pengunjung hanya melihat layar
 * putih tanpa petunjuk apa pun - sulit sekali ditelusuri penyebabnya.
 *
 * Nilai error PDO ditampilkan karena kondisi ini hanya terjadi saat situs
 * belum bisa dipakai sama sekali. Pesan tersebut tidak pernah memuat
 * password, hanya host / nama database yang memang sudah Anda isi sendiri.
 *
 * @param PDOException $e
 * @return void
 */
function db_connection_error_page(PDOException $e): void
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
    }

    // Tampilkan nama host, tapi jangan sampai password ikut terbawa.
    $detail = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $host    = htmlspecialchars((string) DB_HOST, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dbName  = htmlspecialchars((string) DB_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $dbUser  = htmlspecialchars((string) DB_USER, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database belum terhubung - Moma Bread</title>
<style>
  body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#FFFDF6;
       color:#3a1c18;margin:0;padding:32px 16px;line-height:1.6}
  .box{max-width:720px;margin:0 auto;background:#fff;border:1px solid #eee2d8;
       border-left:4px solid #B8241B;border-radius:10px;padding:24px}
  h1{margin:0 0 4px;font-size:20px}
  .sub{color:#7a5a55;font-size:14px;margin:0 0 20px}
  ol{padding-left:20px}
  li{margin-bottom:8px}
  code{background:#f7f1ea;padding:2px 6px;border-radius:4px;font-size:13px}
  pre{background:#2b1a17;color:#ffd9d5;padding:12px;border-radius:8px;overflow:auto;
      font-size:12px;white-space:pre-wrap;word-break:break-word}
  .cred{background:#f7f1ea;padding:12px;border-radius:8px;margin:16px 0}
</style>
</head>
<body>
<div class="box">
  <h1>Database belum terhubung</h1>
  <p class="sub">Aplikasi tidak bisa menghubungi MySQL, jadi belum ada konten yang bisa ditampilkan.</p>

  <p>Periksa <code>config/config.php</code>. Nilai yang sekarang terbaca:</p>
  <div class="cred">
    DB_HOST = <code>{$host}</code><br>
    DB_NAME = <code>{$dbName}</code><br>
    DB_USER = <code>{$dbUser}</code>
  </div>

  <ol>
    <li>Buka <strong>cPanel &rarr; MySQL Databases</strong>, lalu pastikan database sudah dibuat.</li>
    <li>Salin <strong>Database Server</strong>, <strong>username</strong>, dan <strong>password</strong> dari
        cPanel ke <code>config/config.php</code>.</li>
    <li>Di TinkerHost, host MySQL umumnya berbentuk
        <code>sqlxxx.thsite.top</code> &mdash; <strong>bukan</strong> <code>localhost</code>.</li>
    <li>Pastikan tabel sudah ada dengan melakukan import file backup <code>.sql</code> lewat phpMyAdmin.</li>
  </ol>

  <p>Pesan error dari server:</p>
  <pre>{$detail}</pre>
</div>
</body>
</html>
HTML;
}

/**
 * Koneksi ke database aplikasi (di-cache di static variable).
 *
 * @return PDO
 * @throws PDOException tetap dilempar bila halaman pemanggil sudah tahu
 *                      cara menanganinya (mis. admin/status.php).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = pdo_connect(DB_NAME);
        } catch (PDOException $e) {
            db_connection_error_page($e);
            exit;
        }
    }

    return $pdo;
}

/**
 * Koneksi ke server MySQL tanpa memilih database.
 * Dipakai oleh installer untuk CREATE DATABASE.
 *
 * @return PDO
 * @throws PDOException
 */
function db_server(): PDO
{
    return pdo_connect(null);
}

/**
 * Cek apakah aplikasi sudah ter-install (tabel admin_users & settings tersedia).
 *
 * @return bool
 */
function db_is_installed(): bool
{
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'settings'");

        return (bool) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}
