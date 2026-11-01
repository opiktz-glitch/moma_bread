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
- Akses SSH ke server melalui jaringan Tailscale yang sudah disiapkan. Repository
  GitHub diambil lewat HTTPS; tidak perlu menyiapkan kunci SSH GitHub.
- Update rutin dijalankan dengan skrip `deploy-tzserver.sh` dari checkout
  repository.

## Persiapan satu kali

Repository publik dapat di-clone melalui HTTPS. Clone sekali ke home akun
server, bukan ke document root:

```bash
cd /home/admintz
git clone https://github.com/opiktz-glitch/moma_bread.git moma_bread-repo
```

Clone repository sudah berhasil dilakukan di tzserver. Jangan ulangi langkah
clone tersebut. Untuk memeriksa remote dan konfigurasi aplikasi:

```bash
git -C /home/admintz/moma_bread-repo remote -v
sudo test -f /var/www/moma_bread/config/config.php \
  && echo "config aplikasi tersedia"
git -C /home/admintz/moma_bread-repo status --short
```

## Update rutin

### 1. Kirim perubahan yang sudah diperiksa dari komputer

Di komputer pengembangan, commit dan push perubahan ke branch produksi (`main`,
jika itu branch yang dipakai):

```bash
git add <file-yang-diubah>
git commit -m "Ringkasan perubahan"
git push origin main
```

Jangan memasukkan `config/config.php`, isi `backups/`, dump SQL, atau password
ke commit.

### 2. Deploy dari tzserver

Setelah commit di-push, masuk ke server melalui SSH/Tailscale:

```bash
cd /home/admintz/moma_bread-repo
bash ./deploy-tzserver.sh
```

Skrip memeriksa branch `main`, menghentikan deploy jika checkout memiliki
perubahan lokal, lalu menjalankan `git pull --ff-only origin main` dan
menyalin kode ke `/var/www/moma_bread`. Skrip tidak memakai `rsync --delete`
dan mempertahankan `config/config.php`, gambar upload, serta backup. Jika tidak
ada commit baru, skrip tetap menyinkronkan checkout untuk memastikan situs
sesuai. File dokumentasi, GitHub Actions, dan skrip deploy tidak disalin ke
document root.

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

### 4. Verifikasi situs

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

Perubahan kode biasanya tidak memerlukan reload Nginx atau PHP-FPM. Jika
konfigurasi Nginx memang ikut diubah, uji dahulu dengan `sudo nginx -t`, lalu
reload Nginx. Jika PHP masih menyajikan kode lama karena OPcache, reload
PHP-FPM:

```bash
sudo systemctl reload php8.3-fpm
```

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
