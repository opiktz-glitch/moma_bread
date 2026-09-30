<?php
/**
 * Halaman Status Server.
 *
 * Alat bantu utama saat memasang website di hosting (TinkerHost, dll).
 * Semua yang dibutuhkan aplikasi dicek dalam satu halaman, jadi tidak perlu
 * akses FTP atau shell hanya untuk memastikan server sudah cocok.
 *
 * Yang diperiksa:
 *  - Versi PHP dan ekstensi wajib (pdo_mysql, mbstring, fileinfo, zip)
 *  - Koneksi database, versi MySQL, tabel inti, dan isi konten
 *  - Folder yang harus bisa ditulisi (uploads & backups)
 *  - Status .htaccess dan URL bersih
 *  - Batas upload PHP dibandingkan batas aplikasi
 *  - Jumlah file, relevan terhadap kuota inode hosting gratis
 *
 * Halaman ini butuh login, jadi dipakai setelah koneksi database berhasil.
 * Bila koneksi database sendiri yang gagal, pengunjung akan melihat halaman
 * penjelasan dari db_connection_error_page() di config/database.php.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';
require_login();

/** Batas versi PHP minimum agar aplikasi bisa berjalan. */
const STATUS_MIN_PHP = '8.0';

/** Batas jumlah file - peringatan bila mendekati kuota inode hosting. */
const STATUS_INODE_WARN = 20000;

/**
 * Bangun satu baris pemeriksaan.
 *
 * @param string $label
 * @param string $value
 * @param string $status ok|warn|bad
 * @param string $hint
 * @return array<string, string>
 */
function status_row(string $label, string $value, string $status, string $hint): array
{
    return ['label' => $label, 'value' => $value, 'status' => $status, 'hint' => $hint];
}
/**
 * Pemeriksaan PHP dan ekstensi.
 *
 * @return array<int, array<string, string>>
 */
function status_php_checks(): array
{
    $rows = [];

    $versi = PHP_VERSION;
    $cukup = version_compare($versi, STATUS_MIN_PHP, '>=');

    $rows[] = status_row(
        'Versi PHP',
        $versi . ' (' . PHP_SAPI . ')',
        $cukup ? 'ok' : 'bad',
        $cukup
            ? 'Sudah memenuhi syarat minimal PHP ' . STATUS_MIN_PHP . '.'
            : 'Harus PHP ' . STATUS_MIN_PHP . ' atau lebih baru. Ganti lewat Select PHP Version di cPanel.'
    );

    // Ekstensi wajib agar fitur inti bekerja.
    foreach ([
        'pdo_mysql' => 'Koneksi database',
        'mbstring'  => 'Fungsi string multibyte untuk slug dan pencarian',
        'fileinfo'  => 'Deteksi tipe file saat upload',
    ] as $ext => $kegunaan) {
        $ada = extension_loaded($ext);

        $rows[] = status_row(
            'Ekstensi ' . $ext,
            $ada ? 'aktif' : 'tidak ada',
            $ada ? 'ok' : 'bad',
            $ada ? $kegunaan . ' siap.' : 'Wajib aktif - ' . $kegunaan . '.'
        );
    }

    // zip hanya untuk backup gambar, jadi tidak dianggap fatal.
    $zip = class_exists('ZipArchive');

    $rows[] = status_row(
        'Ekstensi zip',
        $zip ? 'aktif' : 'tidak ada',
        $zip ? 'ok' : 'warn',
        $zip
            ? 'Backup gambar (.zip) bisa dibuat.'
            : 'Backup gambar tidak bisa dibuat, fitur lain tetap normal.'
    );

    return $rows;
}


/**
 * Pemeriksaan database: koneksi, versi, tabel inti, dan isi konten.
 *
 * Satu-satunya bagian yang menyentuh koneksi, dan satu-satunya tempat di
 * halaman ini yang menangkap error agar halaman tetap bisa tampil.
 *
 * @return array<int, array<string, string>>
 */
function status_db_checks(): array
{
    $rows = [];

    try {
        $pdo = db();

        $rows[] = status_row(
            'Koneksi',
            'berhasil',
            'ok',
            'Host ' . DB_HOST . ', database ' . DB_NAME . ', user ' . DB_USER . '.'
        );

        $versi = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $rows[] = status_row(
            'Versi MySQL',
            $versi,
            version_compare($versi, '5.7', '>=') ? 'ok' : 'warn',
            'Aplikasi butuh MySQL 5.7+ atau MariaDB 10.x ke atas.'
        );

        // Tabel inti wajib ada, kalau tidak landing page tampil kosong.
        $wajib    = ['settings', 'menu', 'jenis_rote', 'admin_users'];
        $hilang   = [];
        foreach ($wajib as $tbl) {
            if (!$pdo->query('SHOW TABLES LIKE ' . $pdo->quote($tbl))->fetchColumn()) {
                $hilang[] = $tbl;
            }
        }
        $rows[] = status_row(
            'Tabel aplikasi',
            $hilang ? count($hilang) . ' tabel hilang' : 'semua ada',
            $hilang ? 'bad' : 'ok',
            $hilang
                ? 'Tabel belum ada: ' . implode(', ', $hilang)
                  . '. Import deploy-moma_bread.sql lewat phpMyAdmin.'
                : 'Tabel inti lengkap: ' . implode(', ', $wajib) . '.'
        );

        $kategori = (int) $pdo->query('SELECT COUNT(*) FROM menu')->fetchColumn();
        $jenis    = (int) $pdo->query('SELECT COUNT(*) FROM jenis_rote')->fetchColumn();
        $rows[] = status_row(
            'Isi konten',
            $kategori . ' kategori, ' . $jenis . ' jenis',
            $kategori > 0 ? 'ok' : 'warn',
            $kategori > 0
                ? 'Data menu sudah masuk ke database.'
                : 'Belum ada kategori. Tambahkan lewat menu Kategori dan Jenis Roti.'
        );
    } catch (Throwable $ex) {
        $rows[] = status_row('Koneksi', 'gagal', 'bad', $ex->getMessage());
    }

    return $rows;
}

/**
 * Ubah nilai ukuran dari php.ini ("2M", "40K", "1G") menjadi jumlah byte.
 *
 * Cast (int) tidak bisa dipakai: (int)"40M" menghasilkan 40, bukan ukuran aslinya.
 *
 * @param string $nilai
 * @return int
 */
function status_ini_bytes(string $nilai): int
{
    $nilai = trim($nilai);

    if ($nilai === '') {
        return 0;
    }

    // Sengaja memakai array, bukan "match", supaya halaman ini tetap jalan
    // di PHP 7 - justru di situ pengguna paling butuh pesan upgrade.
    $multiplier = ['G' => 1073741824, 'M' => 1048576, 'K' => 1024];

    return (int) ((float) $nilai * ($multiplier[strtoupper(substr($nilai, -1))] ?? 1));
}

/**
 * Tampilkan jumlah byte dalam satuan yang mudah dibaca.
 *
 * @param int $bytes
 * @return string
 */
function status_human_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0, ',', '.') . ' KB';
    }

    return $bytes . ' B';
}

/**
 * Pemeriksaan folder yang harus bisa ditulisi.
 *
 * @return array<int, array<string, string>>
 */
function status_write_checks(): array
{
    $rows = [];

    foreach ([
        UPLOAD_DIR            => 'Tempat upload gambar dari panel admin.',
        APP_ROOT . '/backups' => 'Tempat arsip .sql dan .zip.',
    ] as $dir => $kegunaan) {
        $ada     = is_dir($dir);
        $buka    = $ada && is_writable($dir);

        $rows[] = status_row(
            str_replace(APP_ROOT . '/', '', $dir) . '/',
            !$ada ? 'tidak ada' : ($buka ? 'bisa ditulis' : 'TERKUNCI'),
            $buka ? 'ok' : 'bad',
            $buka
                ? $kegunaan
                : (!$ada
                    ? 'Folder belum ada. Buat lewat File Manager, lalu set izin 755.'
                    : 'Ubah izin folder menjadi 755 lewat File Manager di cPanel.')
        );
    }

    // Batas upload di php.ini sering lebih kecil dari yang diizinkan hosting.
    $limitServer = status_ini_bytes((string) ini_get('upload_max_filesize'));
    $limitAppsi  = (int) MAX_UPLOAD_BYTES;
    $cukup       = $limitServer > 0 && $limitAppsi <= $limitServer;

    $rows[] = status_row(
        'Batas upload gambar',
        status_human_bytes($limitAppsi) . ' aplikasi / ' . status_human_bytes($limitServer) . ' server',
        $cukup ? 'ok' : 'warn',
        $cukup
            ? 'Batas server cukup untuk upload gambar.'
            : 'Batas server lebih kecil dari batas aplikasi. Naikkan upload_max_filesize '
              . 'lewat Select PHP Version di cPanel.'
    );

    return $rows;
}

/**
 * Pemeriksaan URL dan berkas .htaccess.
 *
 * @return array<int, array<string, string>>
 */
function status_url_checks(): array
{
    $rows = [];

    $rows[] = status_row(
        'BASE_URL',
        BASE_URL === '' ? '(akar domain)' : BASE_URL,
        'ok',
        'Dihitung otomatis dari lokasi folder, jadi aman di domain maupun sub-folder.'
    );

    $adaHt = is_file(APP_ROOT . '/.htaccess');
    $rows[] = status_row(
        '.htaccess',
        $adaHt ? 'ada' : 'tidak ada',
        $adaHt ? 'ok' : 'bad',
        $adaHt
            ? 'Berisi aturan URL bersih dan pemblokiran berkas sensitif.'
            : 'Wajib ikut terunggah. Pastikan bukan file tersembunyi (nama diawali titik).'
    );

    $rows[] = status_row(
        'URL bersih (mod_rewrite)',
        PRETTY_MENU_URL ? 'aktif' : 'dimatikan',
        PRETTY_MENU_URL ? 'ok' : 'warn',
        PRETTY_MENU_URL
            ? 'Alamat level 2 memakai /menu/nama-kategori. Bila tidak bisa dibuka, '
              . 'setel PRETTY_MENU_URL menjadi false - situs tetap jalan normal.'
            : 'Semua tautan memakai menu.php?kategori=...'
    );

    return $rows;
}

/* -------------------------------------------------------------------- */
/*  Uji URL bersih: request ke /menu/<slug>, lalu cari tandanya.          */
/* -------------------------------------------------------------------- */
$rewriteTest = null;

if (isset($_GET['tes_rewrite'])) {
    $slug = (string) val(
        'SELECT slug FROM menu WHERE is_active = 1 ORDER BY sort_order, id LIMIT 1',
        [],
        ''
    );

    if ($slug === '') {
        $rewriteTest = [
            'status' => 'warn',
            'text'   => 'Belum ada kategori untuk diuji. Tambahkan data menu terlebih dulu.',
        ];
    } else {
        $target = url('menu/' . rawurlencode($slug));

        $ctx = stream_context_create(['http' => [
            'timeout'          => 8,
            'ignore_errors'    => true,
            // HTTPS di shared hosting sering memakai sertifikat internal.
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]]);

        $body = @file_get_contents($target, false, $ctx);
        $code = 0;

        if (isset($http_response_header[0])
            && preg_match('#HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }

        if ($body === false) {
            $rewriteTest = [
                'status' => 'warn',
                'text'   => 'Server tidak bisa menguji dirinya sendiri lewat HTTP. '
                          . 'Buka manual: ' . $target,
            ];
        } else {
            $berhasil = ($code === 200) && (stripos($body, $slug) !== false);

            $rewriteTest = $berhasil
                ? [
                    'status' => 'ok',
                    'text'   => 'Berhasil. ' . $target
                              . ' terbuka dan menampilkan isi kategori "' . $slug . '".',
                ]
                : [
                    'status' => 'bad',
                    'text'   => 'Gagal. ' . $target . ' memberi HTTP ' . $code
                              . '. Bila 404, mod_rewrite tidak aktif - '
                              . 'pastikan .htaccess ikut terunggah.',
                ];
        }
    }
}

/* -------------------------------------------------------------------- */
/*  Jumlah file - relevan terhadap kuota inode hosting gratis.            */
/* -------------------------------------------------------------------- */
$fileCount  = 0;
$totalBytes = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $f) {
    if ($f->isFile()) {
        $fileCount++;
        $totalBytes += $f->getSize();
    }
}

$checks = [
    'PHP'            => status_php_checks(),
    'Database'       => status_db_checks(),
    'Izin tulis'     => status_write_checks(),
    'URL & .htaccess' => status_url_checks(),
];

$jumlahBad  = 0;
$jumlahWarn = 0;
foreach ($checks as $rows) {
    foreach ($rows as $row) {
        if ($row['status'] === 'bad')  { $jumlahBad++; }
        if ($row['status'] === 'warn') { $jumlahWarn++; }
    }
}

$pageTitle = 'Status Server';
$activeTab = 'status';
require __DIR__ . '/_partials/header.php';
?>

<?php if ($jumlahBad === 0 && $jumlahWarn === 0): ?>
  <div class="alert success">
    <strong>Semua pemeriksaan lolos.</strong> Website siap dipakai.
  </div>
<?php elseif ($jumlahBad === 0): ?>
  <div class="alert info">
    <strong>Website berjalan.</strong> Ada <?= (int) $jumlahWarn ?> peringatan yang
    sebaiknya dibaca, tetapi tidak menghalangi site.
  </div>
<?php else: ?>
  <div class="alert error">
    <strong>Ada <?= (int) $jumlahBad ?> masalah yang harus diperbaiki.</strong>
    Lihat rinciannya di bawah.
  </div>
<?php endif; ?>

<?php foreach ($checks as $namaGrup => $rows): ?>
  <section class="panel">
    <div class="panelhead">
      <h2><?= e($namaGrup) ?></h2>
      <span class="pill off"><?= (int) count($rows) ?> pemeriksaan</span>
    </div>
    <div class="tablewrap">
      <table class="table">
        <thead>
          <tr>
            <th style="width:24%">Item</th>
            <th style="width:26%">Hasil</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><strong><?= e($row['label']) ?></strong></td>
              <td><span class="pill <?= e($row['status']) ?>"><?= e($row['value']) ?></span></td>
              <td class="muted"><?= $row['hint'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>

<section class="panel">
  <div class="panelhead"><h2>Uji URL bersih (mod_rewrite)</h2></div>
  <div class="panelbody">
    <?php if ($rewriteTest === null): ?>
      <p class="muted" style="margin-top:0">
        Menguji dengan request HTTP ke server sendiri. Bila server tidak mengizinkan,
        buka saja manual alamat <code>/menu/nama-kategori</code> di address bar.
      </p>
      <a class="btn" href="<?= e(url('admin/status.php?tes_rewrite=1')) ?>">Uji sekarang</a>
    <?php else: ?>
      <?php
      $kelas = $rewriteTest['status'] === 'bad' ? 'error'
             : ($rewriteTest['status'] === 'ok' ? 'success' : 'info');
      ?>
      <div class="alert <?= e($kelas) ?>"><?= e($rewriteTest['text']) ?></div>
      <a class="btn ghost" href="<?= e(url('admin/status.php')) ?>">Uji ulang</a>
    <?php endif; ?>
  </div>
</section>

<section class="panel">
  <div class="panelhead"><h2>Ukuran proyek</h2></div>
  <div class="tablewrap">
    <table class="table">
      <tbody>
        <tr>
          <td style="width:24%"><strong>Jumlah file</strong></td>
          <td style="width:26%">
            <span class="pill <?= $fileCount >= STATUS_INODE_WARN ? 'warn' : 'ok' ?>">
              <?= number_format($fileCount, 0, ',', '.') ?> file
            </span>
          </td>
          <td class="muted">
            Hosting gratis umumnya membatasi jumlah file (inode). Kalau sudah di atas
            <?= number_format(STATUS_INODE_WARN, 0, ',', '.') ?>, pastikan folder
            <code>backups/img</code> tidak menumpuk arsip lama.
          </td>
        </tr>
        <tr>
          <td><strong>Total ukuran</strong></td>
          <td>
            <span class="pill ok"><?= number_format($totalBytes / 1048576, 2, ',', '.') ?> MB</span>
          </td>
          <td class="muted">Termasuk seluruh gambar di <code>assets/img</code>.</td>
        </tr>
      </tbody>
    </table>
  </div>
</section>

<?php require __DIR__ . '/_partials/footer.php'; ?>
