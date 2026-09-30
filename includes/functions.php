<?php
/**
 * Kumpulan fungsi bantu (helper) untuk aplikasi Moma Bread.
 *
 * Semua halaman me-load file ini. Cakupannya:
 *  - Keamanan output (e), token CSRF, session & flash message
 *  - Query singkat berbasis prepared statement (q/all/one/val)
 *  - Pengaturan situs yang disimpan di tabel "settings" (s)
 *  - Format tampilan (rupiah, bintang, tanggal Indonesia)
 *  - Pembantu URL/aset gambar dan tautan WhatsApp
 *  - Penanganan file upload gambar
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ==================================================================
 |  OUTPUT & KEAMANAN
 * ================================================================== */

/**
 * Escape output HTML (proteksi XSS).
 *
 * @param  mixed $value
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* ==================================================================
 |  QUERY SINGKAT  (semua berbasis prepared statement)
 * ================================================================== */

/**
 * Jalankan query dengan prepared statement.
 *
 * CATATAN PENTING:
 * Koneksi ini memakai native prepare (ATTR_EMULATE_PREPARES = false), dan
 * pdo_mysql pada mode tersebut hanya menerima array parameter BERINDEKS
 * BERURUTAN untuk placeholder "?". Bila diberikan array asosiatif, PDO
 * akan melempar "SQLSTATE[HY093] Invalid parameter number".
 * Karena itu array dinormalisasi menjadi list sebelum dieksekusi.
 *
 * Query dalam aplikasi ini WAJIB memakai placeholder "?" (positional),
 * bukan named placeholder seperti :nama.
 *
 * @param  array<int|string, mixed> $params
 */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);

    // Normalisasi ke list bila bukan array berindeks 0..n-1.
    if ($params !== [] && array_keys($params) !== range(0, count($params) - 1)) {
        $params = array_values($params);
    }

    $stmt->execute($params);

    return $stmt;
}

/**
 * Ambil banyak baris.
 *
 * @return array<int, array<string, mixed>>
 */
function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/**
 * Ambil satu baris, atau null bila tidak ada.
 *
 * @return array<string, mixed>|null
 */
function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();

    return $row === false ? null : $row;
}

/**
 * Ambil satu nilai kolom pertama, atau $default bila tidak ada.
 *
 * @param  mixed $default
 * @return mixed
 */
function val(string $sql, array $params = [], $default = null)
{
    $value = q($sql, $params)->fetchColumn();

    return $value === false ? $default : $value;
}

/* ==================================================================
 |  PENGATURAN SITUS (tabel settings)
 * ================================================================== */

/**
 * Muat seluruh pengaturan sekali saja lalu cache di memori.
 *
 * @return array<string, string>
 */
function settings(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = [];

    try {
        foreach (all('SELECT skey, svalue FROM settings') as $row) {
            $cache[(string) $row['skey']] = (string) $row['svalue'];
        }
    } catch (PDOException $ex) {
        // Database belum di-install -> memakai nilai bawaan.
        $cache = [];
    }

    return $cache;
}

/**
 * Ambil nilai pengaturan dari tabel settings.
 *
 * Contoh: s('site_name', 'Moma Bread')
 */
function s(string $key, string $default = ''): string
{
    $all   = settings();
    $value = trim((string) ($all[$key] ?? ''));

    return $value === '' ? $default : $value;
}

/* ==================================================================
 |  FORMAT TAMPILAN
 * ================================================================== */

/**
 * Format angka menjadi Rupiah. Contoh: 15000 -> "Rp 15.000"
 */
function rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Render bintang penilaian (menghasilkan HTML).
 */
function stars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $out    = '<span class="stars" role="img" aria-label="Penilaian ' . $rating . ' dari 5">';

    for ($i = 1; $i <= 5; $i++) {
        $out .= $i <= $rating ? '&#9733;' : '&#9734;';
    }

    return $out . '</span>';
}

/**
 * Potong teks panjang dengan rapi di batas kata.
 */
function excerpt(?string $text, int $limit = 120): string
{
    $text = trim((string) preg_replace('/\s+/', ' ', (string) $text));

    if ($text === '' || mb_strlen($text) <= $limit) {
        return $text;
    }

    $cut = mb_substr($text, 0, $limit);
    $pos = mb_strrpos($cut, ' ');

    return rtrim($pos !== false ? mb_substr($cut, 0, $pos) : $cut, " ,.;:-") . '…';
}

/**
 * Ubah teks menjadi slug URL: "Roti Cokelat" -> "roti-cokelat"
 */
function slugify(string $text): string
{
    $text = trim((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $text), '-');

    return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
}

/**
 * Format ukuran berkas yang mudah dibaca.
 * Contoh: 1536 -> "1,5 KB"
 */
function size_format(int|float $bytes, int $precision = 1): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max(0, (float) $bytes);
    $i     = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, $i === 0 ? 0 : $precision, ',', '.') . ' ' . $units[$i];
}

/**
 * Format tanggal Indonesia, contoh: "9 Sep 2026, 14.30"
 */
function tgl(?string $datetime, bool $withTime = true): string
{
    if (empty($datetime)) {
        return '-';
    }

    $ts = strtotime($datetime);

    if ($ts === false) {
        return '-';
    }

    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    $out = date('j', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);

    return $withTime ? $out . ', ' . date('H.i', $ts) : $out;
}

/* ==================================================================
 |  URL & ASET
 * ================================================================== */

/**
 * URL aplikasi. Contoh: url('index.php') -> "/moma_bread/index.php"
 */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * URL file aset. Contoh: asset('css/style.css')
 *
 * Ditambahi "?v=<waktu ubah file>" supaya browser langsung mengambil
 * versi terbaru begitu file aset diubah (tanpa perlu hard refresh).
 */
function asset(string $path): string
{
    $path  = ltrim($path, '/');
    $file  = APP_ROOT . '/assets/' . $path;
    $stamp = is_file($file) ? (string) filemtime($file) : '';

    return url('assets/' . $path) . ($stamp !== '' ? '?v=' . $stamp : '');
}

/**
 * URL gambar.
 *
 * Nilai kolom image di database disimpan relatif terhadap folder assets/img:
 *   "banner.jpg"    -> gambar bawaan
 *   "uploads/x.jpg" -> hasil upload dari panel admin
 *   "https://..."   -> URL eksternal
 *
 * @param string|null $file     Nilai kolom image dari database.
 * @param string      $fallback Dipakai bila file tidak ditemukan.
 */
function img(?string $file, string $fallback = 'banner.jpg'): string
{
    $clean = ltrim(str_replace(['..', '\\'], ['', '/'], trim((string) $file)), '/');

    if ($clean !== '' && preg_match('#^https?://#i', $clean)) {
        return $clean;
    }

    if ($clean !== '' && is_file(APP_ROOT . '/assets/img/' . $clean)) {
        return asset('img/' . $clean);
    }

    return asset('img/' . $fallback);
}

/**
 * Link WhatsApp dengan pesan otomatis.
 *
 * @param string $message Pesan khusus (opsional).
 */
function wa_link(string $message = ''): string
{
    $number = preg_replace('/\D+/', '', s('wa_number', '628129578513'));
    $number = $number === '' ? '628129578513' : $number;
    $text   = $message !== '' ? $message : s('wa_message', 'Halo Moma Bread, saya mau pesan roti');

    return 'https://wa.me/' . $number . '?text=' . rawurlencode($text);
}

/**
 * URL halaman level 2 (daftar jenis roti) untuk sebuah kategori.
 *
 * Bentuk pretty : /moma_bread/menu/signature
 * Bentuk biasa : /moma_bread/menu.php?kategori=signature
 *
 * @param string $slug Slug kategori.
 */
function menu_url(string $slug): string
{
    $slug = slugify($slug);

    if (PRETTY_MENU_URL) {
        return url('menu/' . rawurlencode($slug));
    }

    return url('menu.php') . '?kategori=' . rawurlencode($slug);
}

/**
 * URL halaman utama (daftar kategori / level 1).
 */
function menu_index_url(): string
{
    return url('index.php') . '#menu';
}

/* ==================================================================
 |  SESSION, CSRF & FLASH MESSAGE
 * ================================================================== */

/**
 * Token anti-CSRF milik session yang sedang berjalan.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Input hidden CSRF untuk disisipkan di dalam <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validasi token CSRF. Hentikan request bila tidak cocok.
 */
function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        // 403 (Forbidden) dipakai karena status 419 tidak dikenali Apache.
        http_response_code(403);
        exit('Token keamanan tidak valid atau sudah kedaluwarsa. Silakan muat ulang halaman.');
    }
}

/**
 * Simpan pesan singkat untuk ditampilkan pada halaman berikutnya.
 *
 * @param string $type success|error|info
 */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Ambil sekaligus kosongkan antrean flash message.
 *
 * @return array<int, array{type:string,message:string}>
 */
function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($items) ? $items : [];
}

/**
 * Redirect ke URL internal aplikasi lalu hentikan eksekusi.
 */
function redirect(string $path): void
{
    $target = preg_match('#^https?://#i', $path) ? $path : url($path);
    header('Location: ' . $target);
    exit;
}

/* ==================================================================
 |  AUTHENTIKASI ADMIN
 * ================================================================== */

/**
 * Apakah admin sudah login?
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['admin_id']);
}

/**
 * Pastikan admin sudah login, jika tidak arahkan ke halaman login.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        redirect('admin/login.php');
    }
}

/**
 * Data admin yang sedang login.
 *
 * @return array<string, mixed>|null
 */
function current_user(): ?array
{
    static $user = null;

    if ($user === null && is_logged_in()) {
        $user = one(
            'SELECT id, username, full_name, last_login FROM admin_users WHERE id = ?',
            [(int) $_SESSION['admin_id']]
        );
    }

    return $user;
}

/* ==================================================================
 |  UPLOAD GAMBAR
 * ================================================================== */

/** Ekstensi gambar yang diizinkan. */
const ALLOWED_IMAGE_EXT = ['jpg', 'jpeg', 'png', 'webp'];

/**
 * Proses upload satu file gambar dari $_FILES.
 *
 * @param  string      $field   Nama field pada <input type="file">.
 * @param  string|null $current Nilai image lama, dikembalikan bila tidak ada upload baru.
 * @return string|null Path relatif terhadap assets/img, atau null bila tidak ada file.
 * @throws RuntimeException Bila file tidak valid.
 */
function handle_image_upload(string $field, ?string $current = null): ?string
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) {
        return $current;
    }

    $file = $_FILES[$field];
    $err  = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    // Tidak ada file dipilih -> pertahankan gambar lama.
    if ($err === UPLOAD_ERR_NO_FILE) {
        return $current;
    }

    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload gagal (kode error ' . $err . ').');
    }

    if ((int) $file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Ukuran gambar maksimal ' . (MAX_UPLOAD_BYTES / 1024 / 1024) . ' MB.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Berkas upload tidak valid.');
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, ALLOWED_IMAGE_EXT, true)) {
        throw new RuntimeException('Format gambar harus JPG, PNG, atau WEBP.');
    }

    // Pastikan isi berkas benar-benar gambar.
    if (@getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Berkas yang diunggah bukan gambar yang valid.');
    }

    if (!is_dir(UPLOAD_DIR) && !@mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Folder upload tidak dapat dibuat.');
    }

    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = UPLOAD_DIR . '/' . $name;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Gagal menyimpan gambar ke server.');
    }

    // Hapus gambar lama bila ada dan berada di dalam folder upload.
    if (!empty($current)) {
        delete_upload($current);
    }

    return 'uploads/' . $name;
}

/**
 * Hapus file gambar di dalam folder uploads.
 * Path di luar folder uploads diabaikan demi keamanan.
 */
function delete_upload(?string $rel): void
{
    $rel = ltrim(str_replace(['..', '\\'], ['', '/'], trim((string) $rel)), '/');

    if ($rel === '' || strpos($rel, 'uploads/') !== 0) {
        return;
    }

    $path = APP_ROOT . '/assets/img/' . $rel;

    if (is_file($path)) {
        @unlink($path);
    }
}
