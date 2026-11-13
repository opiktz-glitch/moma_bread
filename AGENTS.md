# Panduan Agen

Panduan ini berlaku untuk seluruh repository Moma Bread. Baca
[`README.md`](README.md) untuk setup dan arsitektur aplikasi, serta
[`CONTRIBUTING.md`](CONTRIBUTING.md) untuk alur kerja dan pemeriksaan perubahan.
Arsitektur terperinci ada di [`ARCHITECTURE.md`](ARCHITECTURE.md); prioritas
usulan ada di [`ROADMAP.md`](ROADMAP.md) dan bukan komitmen jadwal.

## Gambaran proyek

- Aplikasi PHP 8+ tanpa framework, menggunakan PDO dan MySQL/MariaDB.
- `index.php` adalah landing page; `menu.php` menampilkan jenis roti per kategori.
- `admin/` berisi panel pengelolaan dan `admin/_schema.php` mendefinisikan
  entitas CRUD.
- `includes/functions.php` menyediakan helper umum; `includes/header.php` dan
  `includes/footer.php` adalah layout halaman publik.
- CSS dan JavaScript publik berada di `assets/css/` dan `assets/js/`.
- Deploy Docker ke server Windows berjalan otomatis ketika ada push ke `main`;
  lihat [workflow deploy](.github/workflows/deploy-docker.yml).

## Aturan perubahan

- Ikuti pola procedural, penamaan, bahasa, dan format kode yang sudah ada.
- Untuk SQL gunakan helper proyek dan prepared statement dengan placeholder `?`;
  jangan menyambungkan input pengguna ke query.
- Escape output HTML dengan helper `e()`. Pertahankan pemeriksaan autentikasi,
  otorisasi, CSRF, dan validasi input pada fitur admin.
- Perlakukan perubahan skema sebagai perubahan yang perlu kompatibel dengan
  database yang sudah terpasang; periksa `admin/_schema.php` dan
  `config/database.php` sebelum mengubah struktur data.
- Periksa halaman publik, admin, dan tampilan responsif yang terdampak; jangan
  mengubah perilaku halaman lain tanpa alasan.
- Perbarui dokumentasi yang terkait. Catat perubahan yang terlihat pengguna di
  [`CHANGELOG.md`](CHANGELOG.md).
- Jangan menambahkan kredensial, dump database, backup, atau unggahan pengguna ke
  Git. `config/config.php`, `.env`, `backups/`, dan `Uploads/` bersifat lokal /
  sensitif.
- Jangan melakukan deploy atau operasi database produksi sebagai bagian dari
  perubahan kode tanpa permintaan dan persetujuan yang jelas.

## Validasi

- Tidak ada Composer, framework test, atau test runner khusus yang dikonfigurasi.
- Untuk setiap file PHP yang diubah, jalankan `php -l <file>`. Di instalasi
  XAMPP Windows, PHP CLI biasanya tersedia di `C:\xampp\php\php.exe`.
- Jalankan pemeriksaan whitespace dengan `git diff --check`.
- Untuk perubahan UI atau alur, uji halaman terkait di browser dan pada lebar
  desktop serta ponsel. Bila perubahan memerlukan database, validasi dengan
  database pengembangan lokal.
