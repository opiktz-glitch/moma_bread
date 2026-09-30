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
 * Koneksi ke database aplikasi (di-cache di static variable).
 *
 * @return PDO
 * @throws PDOException
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $pdo = pdo_connect(DB_NAME);
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
