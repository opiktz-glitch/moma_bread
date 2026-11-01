# Deploy Moma Bread ke tzserver

Panduan ini untuk server internal `tzserver` yang menjalankan Ubuntu Server
24.04, Nginx, PHP 8.3-FPM, dan MySQL. Aplikasi berada di
`/var/www/moma_bread`. Nginx tidak membaca `.htaccess`; konfigurasi Nginx
di server tetap dikelola terpisah dari repository aplikasi.

## Prinsip penting

- Git menyimpan dan mengambil kode; `rsync` menyalin kode ke document root.
- Jangan clone repository langsung ke `/var/www/moma_bread`.
- Pertahankan `config/config.php`, gambar upload, dan backup yang hanya ada di
  server. Jangan commit kredensial atau dump database.
- Jangan memakai `rsync --delete` tanpa meninjau file yang akan dihapus.
- Akses SSH ke server melalui jaringan Tailscale yang sudah disiapkan.

## Persiapan satu kali

Pastikan akun server dapat membaca repository GitHub melalui SSH. Untuk
repository privat, gunakan deploy key khusus dengan akses baca saja; simpan
private key di server dan jangan pernah memasukkannya ke repository atau chat.
Uji akses GitHub:

```bash
ssh -T git@github.com
```

Clone repository ke home akun SSH, bukan ke document root:

```bash
cd /home/admintz
git clone git@github.com:opiktz-glitch/moma_bread.git moma_bread-repo
```

Jika repository sudah pernah di-clone, lewati langkah ini. Pastikan konfigurasi
server tetap tersedia dan tidak dilacak Git:

```bash
sudo test -f /var/www/moma_bread/config/config.php \
  && echo "config aplikasi tersedia"
git -C /home/admintz/moma_bread-repo status --short
```

## Update rutin

### 1. Kirim perubahan yang sudah diperiksa

Di komputer pengembangan, commit dan push perubahan ke branch produksi (`main`,
jika itu branch yang dipakai):

```bash
git add <file-yang-diubah>
git commit -m "Ringkasan perubahan"
git push origin main
```

Jangan memasukkan `config/config.php`, isi `backups/`, dump SQL, atau password
ke commit.

### 2. Ambil perubahan di server

Masuk ke server melalui SSH/Tailscale, lalu jalankan:

```bash
cd /home/admintz/moma_bread-repo
git status --short
git fetch origin
git switch main
git pull --ff-only origin main
git log -1 --oneline
```

Jika `git status` menunjukkan perubahan lokal yang tidak dikenal, berhenti dan
periksa dulu; jangan menimpanya dengan reset atau checkout paksa.

### 3. Backup sebelum perubahan database

Jika rilis mengubah skema atau data database, buat backup sebelum deploy.
Perintah berikut sengaja memastikan `/backup` adalah mount tersendiri, agar
backup tidak diam-diam tersimpan di disk sistem jika drive backup tidak terpasang:

```bash
mountpoint -q /backup || { echo "/backup tidak ter-mount; hentikan deploy."; exit 1; }
backup="/backup/moma_bread-$(date +%F-%H%M).sql"
sudo mysqldump --single-transaction --routines --triggers moma_bread \
  --result-file="$backup"
sudo test -s "$backup"
```

Periksa bahwa file backup terbentuk dan ukurannya masuk akal sebelum
melanjutkan. Simpan salinan penting di luar server juga. Jika perubahan hanya
berisi kode dan tidak mengubah database, backup database tetap disarankan
sebelum rilis besar.

### 4. Salin kode ke document root

Jalankan `rsync` di server:

```bash
sudo rsync -a --chown=www-data:www-data \
  --exclude='.git/' \
  --exclude='config/config.php' \
  --exclude='assets/img/uploads/' \
  --exclude='backups/' \
  /home/admintz/moma_bread-repo/ /var/www/moma_bread/
```

Folder upload dan konfigurasi lokal tidak akan ditimpa. Perintah ini juga tidak
menghapus file yang sudah ada di server tetapi tidak ada di repository. Jika
rilis memang menghapus atau memindahkan file, tinjau file spesifik tersebut
terlebih dahulu dan hapus hanya setelah memastikan file itu bukan konfigurasi,
upload, atau data server.

Perubahan pada kode aplikasi tidak memerlukan reload Nginx. Jika setelah deploy
PHP masih menampilkan kode lama karena OPcache, reload PHP-FPM:

```bash
sudo systemctl reload php8.3-fpm
```

Jangan reload Nginx kecuali konfigurasi Nginx memang ikut diubah; setelah
mengubahnya, uji konfigurasi dahulu dengan `sudo nginx -t`.

### 5. Verifikasi situs

- Buka beranda melalui alamat server yang digunakan tim.
- Uji `/admin/login.php` dan fitur yang terdampak perubahan.
- Dari panel admin, buka **Status Server** untuk memeriksa PHP, database,
  ekstensi, dan izin folder.
- Jika terjadi error, lihat log Nginx:

```bash
sudo tail -n 50 /var/log/nginx/error.log
```

Untuk perubahan database, verifikasi tabel dan fungsi aplikasi yang
bersangkutan sebelum menganggap deploy selesai.

## Pemulihan

Jika rilis kode bermasalah, pilih commit terakhir yang diketahui baik di
checkout repository deploy, lalu sinkronkan ulang dengan perintah `rsync` yang
sama. Jangan gunakan `git reset --hard` pada checkout yang berisi perubahan
lokal. Pemulihan database adalah langkah terpisah: pulihkan hanya dari dump
yang sudah diverifikasi dan pahami bahwa impor dapat mengganti data saat ini.

## Keamanan kredensial

`config/config.php` hanya berada di server dan tidak ikut deploy. Jika password
database pernah dibagikan atau terungkap, ganti password user MySQL di server
dan segera perbarui `DB_PASS` pada konfigurasi aplikasi agar keduanya tetap
cocok. Jangan menaruh password database di perintah shell yang tersimpan,
commit, dokumentasi publik, atau chat.
