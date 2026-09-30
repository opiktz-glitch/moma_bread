# Moma Bread - Aplikasi PHP Landing Page + Panel Admin

Aplikasi PHP untuk unmanaged landing page **Moma Bread**, dibangun dari desain
`momabread.html`. Seluruh isi halaman sekarang berasal dari **MySQL/MariaDB**
dan dapat dikelola dari **panel admin** tanpa menyentuh kode.

## Fitur

**Halaman publik**
- **Level 1** — `index.php`: hero, daftar menu roti (Signature, Classic, Tawar, Kukus), keunggulan, cara pesan, galeri, testimoni
- **Level 2** — `menu.php`: daftar jenis roti per kategori lengkap dengan harga, breadcrumb, dan navigasi ke kategori lain
- Semua teks, judul section, gambar, dan nomor WhatsApp dapat diedit dari panel admin
- Tombol "Pesan" membuka WhatsApp dengan pesan otomatis berisi nama & harga roti
- Dark / light mode (mengikuti sistem, bisa ditoggle manual, disimpan di `localStorage`)
- Tetap tampil rapi di ponsel (responsive), plus lightbox untuk galeri

**Panel admin**
- Login dengan password ter-hash (`password_hash` / bcrypt)
- CRUD lengkap (tambah, ubah, hapus, tampilkan/sembunyikan) untuk 6 entitas:
  Menu Roti, Jenis Roti, Keunggulan, Cara Pesan, Galeri, dan Testimoni
- Jenis Roti memakai dropdown relasi ke Menu Roti
- Kategori roti tidak bisa dihapus selama masih memiliki jenis roti di dalamnya
- Upload gambar (JPG/PNG/WEBP, maks 2 MB) dengan validasi tipe berkas
- Halaman Pengaturan untuk mengubah teks situs, kontak, dan judul section
- Dashboard berisi ringkasan jumlah data
- **Backup Database** — buat & unduh berkas `.sql` (maks 15 arsip tertua)
- **Backup Gambar** — buat & unduh arsip `.zip` seluruh `assets/img` (maks 10 arsip terbaru)
- **Status Server** — cek PHP, ekstensi, database, izin tulis folder, `.htaccess`,
  dan `mod_rewrite` dalam satu halaman. Berguna sekali saat pasang di hosting baru
- **Ganti Password** — dengan verifikasi password lama dan syarat minimal 8 karakter

---

## Deploy ke Hosting

Panduan lengkap untuk hosting gratis **TinkerHost** ada di
[`DEPLOY-TINKERHOST.md`](DEPLOY-TINKERHOST.md).

Cara rutin memperbarui website:

```bash
git add .
git commit -m "perubahan"
git push
powershell -ExecutionPolicy Bypass -File tools\deploy.ps1
```

Skrip deploy mengambil file langsung dari git lewat `git archive`, jadi yang
online selalu sama dengan yang sudah di-commit. `config/config.php` tidak
pernah ikut terkirim.

> TinkerHost tidak menyediakan terminal/SSH, jadi `git pull` di server tidak
> bisa dipakai. Git dipakai di komputer, lalu di-upload lewat FTP.

## Upgrade dari Versi 1-Tingkat

Bila aplikasi Anda sebelumnya memakai menu 1 tingkat (menu langsung punya harga),
jalankan **`database/upgrade_1_jenis_rote.sql`** satu kali lewat phpMyAdmin
(tab **Import**) atau CLI:

```
mysql -u root < database/upgrade_1_jenis_rote.sql
```

Skrip tersebut membuat tabel `jenis_rote`, memindahkan data lama ke struktur baru,
lalu menghapus kolom `price` dan `category` dari tabel `menu`. Struktur dan isi
akhirnya akan sama persis dengan `database/momabread.sql`.

## Kebutuhan Sistem

| Komponen | Versi |
|---|---|
| PHP | 8.0+ (diuji di 8.2.12) + ekstensi `pdo_mysql`, `mbstring`, `fileinfo` |
| Database | MySQL 5.7+ / MariaDB 10.x |
| Web server | Apache (XAMPP) |

## Setup dari Git

Repository ini sengaja **tidak** memuat `config/config.php` karena file itu berisi
kredensial database. Yang tersedia adalah `config/config.example.php` sebagai template.

```bash
git clone <url-repository-ini> moma_bread
cd moma_bread

# 1. Buat file konfigurasi sendiri dari template
copy config\config.example.php config\config.php      # Windows
cp config/config.example.php config/config.php        # Linux/Mac

# 2. Edit config/config.php, ganti bagian "1. KONEKSI DATABASE"
#    DB_HOST untuk hosting biasanya BUKAN 127.0.0.1,
#    melainkan sqlXXX.websitetools.com / sqlXXX.infinityfree.com

# 3. Import database lewat phpMyAdmin, lalu jalankan website
```

> Kalau `config/config.php` ikut ter-commit, kredensial database Anda akan bocor
> dan bisa dipakai orang lain untuk mengakses database Anda. `.gitignore` sudah
> melindungi file ini secara otomatis.

## Cara Instalasi (XAMPP lokal)

1. Letakkan folder ini di `C:\xampp\htdocs\moma_bread`, lalu jalankan **Apache** dan **MySQL** di XAMPP.
2. Salin `config/config.example.php` menjadi `config/config.php` bila belum ada.
3. Import `database/momabread.sql` melalui phpMyAdmin (`http://localhost/phpmyadmin`).
4. Buka `http://localhost/moma_bread/install.php`, isi akun admin, klik **Pasang Sekarang**.
5. **Hapus `install.php` dan `install.lock`** setelah selesai.

### Login

- Alamat: `http://localhost/moma_bread/admin/login.php`
- Username & password: sesuai yang diisi saat instalasi

## Struktur Folder

```
moma_bread/
├── index.php              Landing page / level 1 (semua section dari database)
├── menu.php               Halaman level 2 (daftar jenis roti + harga)
├── .htaccess              Pretty URL /menu/<slug> + blokir berkas sensitif
├── install.php            Installer - HAPUS setelah aplikasi online
├── config/
│   ├── config.php         Kredensial database, path, timezone, PRETTY_MENU_URL
│   ├── database.php       Koneksi PDO (db(), db_server(), db_is_installed())
│   └── .htaccess
├── includes/
│   ├── functions.php      Helper: query, settings, format, CSRF, upload, menu_url()
│   ├── header.php         <head> + navigasi
│   ├── footer.php         Footer + info kontak
│   └── .htaccess
├── admin/
│   ├── login.php          Login
│   ├── logout.php         Keluar
│   ├── index.php          Dashboard
│   ├── manage.php         CRUD generik semua entitas
│   ├── settings.php       Pengaturan situs
│   ├── backup.php         Buat / unduh backup database
│   ├── backup-gambar.php  Buat / unduh backup gambar (.zip)
│   ├── password.php       Ganti password admin
│   ├── status.php         Cek kesehatan server (PHP, DB, izin, rewrite)
│   ├── _schema.php        Definisi entitas (label, kolom, field, relasi)
│   └── _partials/         Layout panel admin
├── backups/               Arsip .sql + .zip (tidak bisa diakses langsung via browser)
├── assets/
│   ├── css/style.css      Tampilan halaman publik
│   ├── css/admin.css      Tampilan panel admin
│   ├── js/main.js         Dark mode, drawer menu, scroll spy, lightbox
│   └── img/               Logo, banner, gambar bawaan, uploads/
└── config/
    ├── config.php         KREDENSIAL - tidak ikut ter-commit (lihat .gitignore)
    ├── config.example.php Template setup, aman untuk repository
    ├── database.php       Koneksi PDO
    └── .htaccess          Cegah akses langsung ke folder ini
```

> Folder `database/` (berkas .sql) dan `install.php` sengaja tidak ada di
> repository ini karena tidak dibutuhkan saat website sudah berjalan.
> Gunakan fitur **Backup Database** di panel admin untuk membuat salinan .sql.

## Struktur Menu (2 Tingkat)

Menu roti dipisah menjadi dua tingkat agar rapi dan mudah dirawat:

| Tingkat | Tabel | Isi | Harga | Halaman |
|---|---|---|---|---|
| **Level 1** | `menu` | Menu roti / kategori: Signature, Classic, Tawar, Kukus | ❌ tidak ada | `index.php` (halaman utama) |
| **Level 2** | `jenis_rote` | Jenis / varian: Signature - Kopi / Signature / Matcha, Classic - Coklat / Keju, dst. | ✅ ada | `menu.php` (halaman baru) |

Relasinya: `jenis_rote.menu_id` → `menu.id` (foreign key, `ON DELETE CASCADE`).

### Alamat halaman level 2

| Bentuk | Alamat | Keterangan |
|---|---|---|
| Pretty URL | `/moma_bread/menu/signature` | Digunakan bila `PRETTY_MENU_URL = true` (butuh `mod_rewrite`) |
| Biasa | `/moma_bread/menu.php?kategori=signature` | Selalu berfungsi, tanpa aturan rewrite |

Aturan rewrite-nya ada di `.htaccess`. Bila dipindahkan ke hosting yang tidak
menyediakan `mod_rewrite`, cukup ubah `PRETTY_MENU_URL` menjadi `false` di
`config/config.php` - seluruh tautan otomatis menyesuaikan.

## Struktur Database

| Tabel | Isi |
|---|---|
| `menu` | Menu roti level 1 / kategori (nama, slug, deskripsi, gambar) |
| `jenis_rote` | Jenis roti level 2 (kategori, nama, deskripsi, harga, gambar) |
| `keunggulan` | Keunggulan Moma Bread |
| `cara_pesan` | Langkah pemesanan |
| `galeri` | Foto galeri |
| `testimonials` | Testimoni pelanggan beserta rating |
| `settings` | Pasangan key-value untuk teks situs, kontak, dan judul section |
| `admin_users` | Akun panel admin |

## Menambah Entitas / Kolom Baru

Seluruh CRUD dibangun dari satu berkas: `admin/_schema.php`.
Tambah blok baru di `admin_entities()` (bersama `CREATE TABLE` baru di
`database/momabread.sql`) dan menu admin otomatis muncul - tanpa menulis
kode list/form/hapus yang berulang.

## Keamanan

- Seluruh query memakai **prepared statement** (tidak ada input yang disambung ke SQL)
- Output di-escape dengan `e()` (htmlspecialchars) untuk mencegah XSS
- Token **CSRF** pada setiap form; permintaan tanpa token ditolak HTTP 403
- Password admin disimpan sebagai hash bcrypt, diverifikasi dengan `password_verify()`
- `session_regenerate_id()` setiap kali login berhasil
- Folder `config/`, `includes/`, `database/`, dan `admin/_partials/` dilindungi `.htaccess`
- Upload divalidasi: ekstensi, ukuran, dan `getimagesize()`; nama file diacak

### Langkah wajib sebelum dipublikasikan
- Hapus `install.php` dan `install.lock` (panel admin akan menampilkan peringatan
  selama `install.php` masih ada)
- Ganti password bawaan `admin123` lewat menu **Ganti Password**
- Buat backup pertama lewat menu **Backup Database**, lalu simpan berkasnya di
  luar server (Google Drive / flashdisk) dan lakukan secara berkala
- Pastikan `APP_DEBUG` di `config/config.php` bernilai `false`

## Catatan Teknis

Koneksi PDO memakai `ATTR_EMULATE_PREPARES = false` (native prepare).
Pada mode tersebut pdo_mysql **hanya menerima array parameter berindeks
berurutan** untuk placeholder `?`; array asosiatif akan menghasilkan
`SQLSTATE[HY093] Invalid parameter number`. Fungsi `q()` karena itu
menormalisasi parameter ke list secara otomatis.

Belum ada thumbnail otomatis karena ekstensi **GD tidak terpasang** di XAMPP
bawaan. Ukuran gambar tetap dibatasi 2 MB. Jika GD diaktifkan, thumbnail
sangat mudah ditambahkan di `handle_image_upload()`.
