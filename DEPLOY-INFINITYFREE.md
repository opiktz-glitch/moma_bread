# Deploy Moma Bread ke InfinityFree

Panduan ini menjelaskan persiapan database dan deploy otomatis aplikasi Moma
Bread ke InfinityFree menggunakan GitHub Actions. Aplikasi memerlukan PHP 8.0
atau lebih baru, MySQL, serta ekstensi `pdo_mysql`, `mbstring`, dan `fileinfo`.

## 1. Siapkan database

1. Di panel InfinityFree, buka **MySQL Databases** dan buat database serta user.
2. Catat hostname MySQL, nama database, username, dan password persis seperti
   yang ditampilkan panel. Nama database/user dapat memiliki prefix akun.
3. Buka phpMyAdmin dari panel, pilih database yang baru dibuat, lalu impor
   backup `.sql` dari komputer Anda.

Jangan masukkan file dump `.sql` ke repository atau folder publik hosting.

## 2. Atur FTP secrets di GitHub

Workflow [deploy.yml](.github/workflows/deploy.yml) berjalan setiap kali ada
push ke branch `main`, dan juga dapat dijalankan manual dari tab **Actions**.
Workflow menggunakan FTP secrets berikut:

1. Buka repository di GitHub, lalu pilih **Settings -> Secrets and variables
   -> Actions**.
2. Buat repository secrets berikut menggunakan nilai FTP dari panel
   InfinityFree:

| Nama secret | Nilai |
|---|---|
| `FTP_HOST` | FTP hostname yang ditampilkan panel |
| `FTP_USER` | Username FTP |
| `FTP_PASS` | Password FTP, bukan password database |
| `FTP_PATH` | Folder website di FTP, biasanya `/htdocs` untuk domain utama |

Pastikan `FTP_PATH` menunjuk ke folder document root yang benar untuk domain
Anda. Periksa lokasinya melalui detail FTP atau File Manager panel sebelum
menjalankan deploy.

## 3. Siapkan konfigurasi aplikasi

Workflow tidak mengunggah `config/config.php`. Setelah deploy pertama, di
File Manager salin `config/config.example.php` menjadi `config/config.php`,
lalu isi nilai database yang dicatat pada langkah 1:

```php
define('DB_HOST', 'hostname-mysql-dari-panel');
define('DB_PORT', '3306');
define('DB_NAME', 'prefix_nama_database');
define('DB_USER', 'prefix_nama_user');
define('DB_PASS', 'password-database');
```

Gunakan hostname yang diberikan panel, bukan `localhost` kecuali panel
menyatakan demikian. Pastikan `APP_DEBUG` tetap `false`. Jangan membagikan
atau meng-commit `config/config.php`.

## 4. Jalankan dan periksa deploy

Setelah secrets disimpan, push perubahan ke `main`, atau buka **Actions ->
Deploy ke InfinityFree -> Run workflow**. Periksa log run di tab **Actions**
untuk memastikan upload berhasil.

Workflow mengunggah kode aplikasi dan gambar. Workflow mengecualikan
`.git`, `.github`, `config/config.php`, isi `backups/`, `tools/`, serta file
`README.md` dan `DEPLOY-INFINITYFREE.md`. Jangan menganggap deploy pertama
aman untuk file server yang belum dibackup; simpan salinan file penting
sebelum menjalankannya.

## 5. Verifikasi situs

1. Buka domain dan pastikan halaman utama tampil.
2. Buka `/admin/login.php` dan uji login menggunakan akun admin dari database
   yang diimpor.
3. Buka **Status Server** di panel admin untuk memeriksa PHP, ekstensi,
   koneksi database, dan izin tulis.
4. Jika URL `/menu/<slug>` tidak berfungsi, set `PRETTY_MENU_URL` menjadi
   `false` di `config/config.php`; tautan kategori akan memakai
   `menu.php?kategori=...`.
5. Jika upload gambar gagal, periksa izin tulis folder `assets/img/uploads`.

## Upload manual (opsional)

Unggah isi proyek ke folder document root domain sehingga `index.php` berada
langsung di document root. Sertakan file `.htaccess`. Jangan unggah folder
`.git/`, `config/config.php`, isi `backups/`, atau file dump `.sql`.
