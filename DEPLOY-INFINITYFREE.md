# Deploy Moma Bread ke InfinityFree

Panduan ini untuk deploy otomatis dari GitHub ke hosting gratis InfinityFree.
Aplikasi membutuhkan PHP 8.0 atau lebih baru, MySQL, dan ekstensi
`pdo_mysql`, `mbstring`, serta `fileinfo`.

## 1. Siapkan salinan database

Situs ini tidak menyertakan installer atau dump database di repository.
Ekspor database dari situs lokal melalui phpMyAdmin, atau unduh backup SQL
dari menu **Backup Database** di panel admin. Simpan file `.sql` itu di
komputer Anda; jangan letakkan di repository atau folder publik hosting.

## 2. Buat database InfinityFree

1. Buka Control Panel InfinityFree dan masuk ke **MySQL Databases**.
2. Buat database dan user, lalu berikan user akses ke database.
3. Catat **MySQL hostname**, nama database, username, dan password yang
   ditampilkan panel. Nama database dan username biasanya memiliki prefix
   akun; jangan menghapus atau menebak prefix tersebut.
4. Buka phpMyAdmin dari panel, pilih database yang baru dibuat, lalu impor
   file `.sql` dari langkah 1.

## 3. Siapkan FTP di GitHub

Workflow [deploy-infinityfree.yml](.github/workflows/deploy-infinityfree.yml)
mengunggah aplikasi setiap kali ada push ke branch `main`. Buka repository
di GitHub, pilih **Settings -> Secrets and variables -> Actions**, lalu
tambahkan repository secrets berikut:

| Nama secret | Nilai |
|---|---|
| `INFINITYFREE_FTP_HOST` | FTP hostname dari panel InfinityFree (biasanya `ftpupload.net`) |
| `INFINITYFREE_FTP_USER` | Username FTP dari panel |
| `INFINITYFREE_FTP_PASS` | Password FTP dari panel, bukan password database |
| `INFINITYFREE_FTP_PATH` | Folder root situs, biasanya `htdocs` |

Isi path sesuai folder tujuan yang terlihat saat login FTP. Jika nilai path
adalah `htdocs`, workflow akan mengunggah ke `/htdocs/`. Pastikan database
dan file `config/config.php` sudah disiapkan sebelum membuka situs; workflow
tidak membuat atau mengunggah konfigurasi database.

Setelah secrets tersimpan, push perubahan ke `main`, atau jalankan manual
dari tab **Actions -> Deploy ke InfinityFree -> Run workflow**. Pantau hasil
upload di tab **Actions**.

## 4. File yang diunggah

Workflow mengunggah kode aplikasi dan gambar, termasuk file `.htaccess`.
Workflow mengecualikan `.git`, `config/config.php`, isi `backups/`, file
Markdown, dan direktori `.github`. Pada deploy berikutnya, file yang pernah
dikelola workflow akan dihapus dari server jika dihapus dari repository.
File yang dikecualikan tidak ikut diunggah maupun dihapus.

Deploy tidak mengosongkan folder tujuan secara menyeluruh; file lain yang
tidak dikelola workflow tetap dibiarkan.

### Upload manual (opsional)

Unggah **isi proyek** ke folder `htdocs` domain yang dituju, agar `index.php`
berada langsung di document root. Jangan unggah folder proyek sebagai
subfolder kecuali memang ingin situs berada di alamat `domain-anda/...`.
Jangan unggah `.git/`, `config/config.php` lokal, isi `backups/`, atau file
dump `.sql`. Pastikan file tersembunyi `.htaccess` ikut diunggah.

## 5. Atur konfigurasi database di server

Di File Manager, salin `config/config.example.php` menjadi
`config/config.php`, lalu isi kredensial dari panel InfinityFree:

```php
define('DB_HOST', 'hostname-dari-panel');
define('DB_PORT', '3306');
define('DB_NAME', 'prefix_nama_database');
define('DB_USER', 'prefix_nama_user');
define('DB_PASS', 'password-database');
```

Jangan gunakan `localhost` kecuali panel InfinityFree secara eksplisit
menampilkan nilai itu sebagai hostname database. Pastikan `APP_DEBUG` tetap
`false`. Jangan membagikan atau meng-commit `config/config.php`.

Path aplikasi dan URL dasar dihitung otomatis. Jika situs dipasang langsung
di `htdocs`, tidak perlu mengubah `APP_ROOT`, `UPLOAD_DIR`, atau `BASE_URL`.

## 6. Tes situs

1. Buka domain dan pastikan halaman utama tampil.
2. Buka `/admin/login.php`, lalu uji login dengan akun admin dari database
   yang diimpor.
3. Coba halaman kategori menu. Bila URL `/menu/<slug>` tidak berfungsi,
   ubah `PRETTY_MENU_URL` menjadi `false` di `config/config.php`; tautan
   kategori akan memakai `menu.php?kategori=...`.
4. Dari panel admin, buka **Status Server** untuk memeriksa PHP, ekstensi,
   koneksi database, dan izin tulis folder.
5. Jika upload gambar gagal, pastikan `assets/img/uploads` dapat ditulisi.
   Jangan membuat folder dapat ditulis publik secara luas jika tidak perlu.

## Catatan penting

- Workflow TinkerHost terpisah. Workflow InfinityFree berjalan setelah
  repository secrets FTP di atas sudah diatur.
- Jangan mengunggah file `.sql` ke `htdocs`; impor hanya melalui phpMyAdmin.
- Jika situs sudah dipasang sebelumnya, buat backup database dan gambar
  sebelum mengganti file.
- Setelah deploy, ganti password admin bila akun tersebut juga dipakai di
  lingkungan lain.
