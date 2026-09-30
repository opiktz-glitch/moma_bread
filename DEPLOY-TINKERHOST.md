# Deploy Moma Bread ke TinkerHost

Panduan ini untuk hosting gratis **TinkerHost** (tinkerhost.net).
Kalau nanti pindah ke hosting lain, bagian 1, 2, 4, dan 5 tetap sama;
cuma nama host MySQL di bagian 3 yang berbeda.

---

## 0. Yang perlu disiapkan dulu

| Kebutuhan | Keterangan |
|---|---|
| Akun TinkerHost | Gratis, tapi butuh minimal 5 pengunjung/bulan |
| File project | Folder `moma_bread` (siap di-commit, tanpa `config.php`) |
| `deploy-moma_bread.sql` | Berisi seluruh tabel + data awal |

> **Jangan pernah commit `config/config.php` ke git.** File itu berisi
> password database. Repo ini sudah menutupnya lewat `.gitignore`.

---

## 1. Upload file project

Login ke cPanel TinkerHost, lalu pakai **File Manager** atau **FTP**.

Upload ke folder `public_html`. Kalau mau memakai subdomain sendiri
(mis. `momabread.tinkerhost.net`), buat folder bernama `momabread` di
dalam `public_html` dan upload ke sana. Keduanya bisa jalan.

### Berkas yang WAJIB ikut terunggah

Berkas bernama titik (`.htaccess`) sering hilang saat upload lewat
drag-and-drop browser. Setelah upload, cek dulu di File Manager:

- `moma_bread/.htaccess`
- `moma_bread/config/.htaccess`
- `moma_bread/includes/.htaccess`
- `moma_bread/backups/.htaccess`
- `moma_bread/admin/_partials/.htaccess`

Kalau ada yang tidak ada, buat file kosong dulu di File Manager
(New File, ketik `.htaccess`), baru salin isinya dari project lokal.

> Jangan ikut mengunggah folder `.git` bila menyalin folder dari
> komputer sendiri. Repository di GitHub sudah memuat salinannya.

---

## 2. Buat `config/config.php`

File ini tidak ada di repository, jadi harus dibuat manual di server:

1. Buka **File Manager**, masuk ke `moma_bread/config/`
2. Klik **New File**, ketik `config.php`
3. Salin isi `config/config.example.php` dari komputer Anda
4. Tempel di File Manager, lalu ubah bagian koneksi database (bagian 3)

---

## 3. Isi kredensial database

Buat database dulu lewat **cPanel -> MySQL Databases**:

- Buat database, misalnya `thsi_12345678_momabread`
- Buat user, lalu **WAJIB** tambahkan user itu ke database dengan
  semua privilege. Kalau lupa, situs akan "Access denied"

Lalu catat dari cPanel:

```
Database Server : sqlxxx.thsite.top
Database name   : thsi_12345678_momabread
MySQL username  : thsi_12345678_pengguna
MySQL password  : (password yang Anda buat)
```

Isi ke `config/config.php`:

```php
define('DB_HOST', 'sqlxxx.thsite.top');   // BUKAN localhost
define('DB_PORT', '3306');
define('DB_NAME', 'thsi_12345678_momabread');
define('DB_USER', 'thsi_12345678_pengguna');
define('DB_PASS', 'password-anda');
```

---

## 4. Import database

**cPanel -> phpMyAdmin**, pilih database yang tadi dibuat, tab **Import**.

- Pilih file `deploy-moma_bread.sql`
- Klik **Go**, tunggu sampai muncul pesan hijau

File ini ukurannya kecil, jadi seharusnya aman. Kalau tetap gagal karena
batas waktu phpMyAdmin, coba lewat menu **Terminal** di cPanel:

```bash
mysql -u thsi_12345678_pengguna -p thsi_12345678_momabread < deploy-moma_bread.sql
```

---

## 5. Pilih versi PHP

**cPanel -> Select PHP Version**, pilih **PHP 8.0** atau lebih baru
(disarankan 8.1 / 8.2). Lalu aktifkan ekstensi:

- `pdo_mysql`  (wajib)
- `mbstring`   (wajib)
- `fileinfo`   (wajib)
- `zip`        (untuk Backup Gambar)
- `gd`         (opsional)

Buka tab **Options** di halaman yang sama, pastikan:

- `upload_max_filesize` minimal `2M` (aplikasi membatasi 2 MB)
- `memory_limit` minimal `128M`
- `post_max_size` minimal `8M`

---

## 6. Set izin folder (writable)

Aplikasi butuh dua folder yang bisa ditulisi:

| Folder | Untuk apa |
|---|---|
| `assets/img/uploads/` | tempat upload gambar dari panel admin |
| `backups/` | tempat arsip `.sql` dan `.zip` |

Di File Manager: klik kanan folder -> **Change Permissions** -> `755`.

Kalau `backups/` tidak ada, buat dulu (New Folder). Berkas `.htaccess` di
dalamnya ikut terunggah dan jangan dihapus.

---

## 7. Login dan verifikasi

Buka `https://alamat-anda/admin/login.php`, lalu masuk.

Segera setelah login, buka menu **Status Server** di sidebar. Halaman itu
memeriksa PHP, ekstensi, database, izin tulis, `.htaccess`, dan URL bersih
dalam satu tampilan. Klik **Uji sekarang** untuk memastikan `mod_rewrite`
bekerja.

Semua hasil pemeriksaan harus hijau. Kalau ada yang merah, halaman tersebut
sudah menuliskan cara memperbaikinya.

> **Ganti password admin Anda.** Password bawaan masih `admin123`.
> Gunakan menu **Ganti Password** sebelum website dipublikasikan.

---

## 8. Kalau ada masalah

| Gejala | Penyebab & solusi |
|---|---|
| "Database belum terhubung" | `config.php` salah. Pesan di layar menyebut host & database yang terbaca |
| "Access denied for user" | User database belum ditambahkan ke database, atau password salah |
| Tabel tidak ada di phpMyAdmin | SQL belum di-import (bagian 4) |
| `/menu/tawar` memberi 404 | `mod_rewrite` tidak aktif. Set `PRETTY_MENU_URL` jadi `false` di `config.php` - situs tetap jalan dengan URL `menu.php?kategori=...` |
| Gagal upload gambar | `upload_max_filesize` terlalu kecil (bagian 5), atau `assets/img/uploads/` tidak writable |
| Backup gambar gagal | Ekstensi `zip` belum aktif, atau `backups/` tidak writable |
| HTTP 500 saat membuka halaman | Biasanya karena salah ketik di `config.php` |

---

## 9. Batas hosting gratis TinkerHost

| Batas | Nilai | Catatan |
|---|---|---|
| Jumlah file (inode) | 30.000 | Arsip backup menumpuk bisa menghabiskan kuota. Sistem otomatis menyimpan 10 zip terbaru |
| Ukuran file PHP/HTML | 1 MiB | Project ini jauh di bawah batas |
| Pengunjung | 5/bulan | Akun bisa dihapus jika tidak tercapai |
| Penyimpanan | 5 GB | Cukup untuk website ini |
| `.htaccess` | 10 kB | Dipakai di semua paket, termasuk gratis |

Project ini memakai sekitar 166 file dan 6,7 MB, jadi jauh di bawah kuota.
