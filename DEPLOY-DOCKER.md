# Deploy Moma Bread dengan Docker

Panduan ini menjalankan Apache, PHP 8.3, dan MariaDB 10.11 menggunakan Docker
Compose. Database, gambar upload, dan arsip backup disimpan di Docker volumes
agar tetap ada saat container dibuat ulang.

## Prasyarat

- Docker Desktop di Windows/Mac, atau Docker Engine dan Docker Compose v2 di Linux.
- File backup database `.sql` dari instalasi Moma Bread yang sudah berjalan.

> Repository ini tidak menyertakan `install.php` maupun dump database. Untuk
> deploy data yang sudah ada, siapkan dump SQL melalui fitur **Backup Database**
> di panel admin atau dari server database lama.

## Jalankan di Windows (PowerShell) atau Linux

1. Clone repository, lalu masuk ke foldernya:

   ```powershell
   git clone https://github.com/opiktz-glitch/moma_bread.git
   cd moma_bread
   ```

2. Buat file environment lokal. File `.env` tidak masuk Git.

   ```powershell
   Copy-Item .env.example .env
   ```

   Di Linux gunakan `cp .env.example .env`. Untuk menjalankan database bawaan
   Compose, edit `.env` dan ganti `DB_PASSWORD` serta `DB_ROOT_PASSWORD`
   dengan password kuat dan berbeda.

   `APP_PORT` adalah port HTTP di komputer/server (default `8081`). Jika port
   tersebut sudah dipakai, ubah nilainya, misalnya ke `8081`.

3. Bangun dan jalankan aplikasi:

   ```powershell
   docker compose up -d --build
   docker compose ps
   ```

   Tunggu sampai service `db` berstatus `healthy`, lalu buka
   `http://localhost:8080` (atau port yang Anda pilih). Apache memakai
   `mod_rewrite` dan `.htaccess` aplikasi.

4. Restore database. Pastikan dump berasal dari aplikasi Moma Bread dan sesuai
   dengan versi skema aplikasi. Di PowerShell, ganti nama file pada contoh
   berikut jika diperlukan. Jalankan dari PowerShell:

   ```powershell
   $dbContainer = docker compose ps -q db
   docker cp .\moma_bread.sql "${dbContainer}:/tmp/moma_bread.sql"
   docker compose exec db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" < /tmp/moma_bread.sql'
   docker compose exec db rm /tmp/moma_bread.sql
   ```

   Di Linux, file SQL dapat dialirkan langsung:

   ```bash
   docker compose exec -T db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE"' < ./moma_bread.sql
   ```

   Setelah import, muat ulang situs dan buka `/admin/login.php`. Gunakan akun
   admin yang sudah ada dalam dump database.

## Gunakan database Docker yang sudah ada

Database dalam Docker dapat dipakai bersama oleh beberapa aplikasi. Gunakan
database/schema dan user terpisah untuk Moma Bread; jangan memakai database
atau user aplikasi lain. Buat database `moma_bread` dan user khusus di server
MySQL/MariaDB yang sudah berjalan, lalu berikan user tersebut akses hanya ke
database Moma Bread.

Container aplikasi dan database harus bergabung ke satu Docker network. Pada
Docker Anda, container database bernama `pamit-db` dan network-nya
`pamit_pamit_network`. Port `3307:3306` berarti port internal database tetap
`3306`; port `3307` hanya dipakai koneksi dari luar Docker. Moma Bread akan
bergabung ke network yang sama dan memakai `pamit-db:3306`. Jangan membuat
network baru atau menjalankan `docker network connect`.

Atur `.env` untuk database yang sudah ada:

```dotenv
APP_PORT=8081
DB_HOST=pamit-db
DB_PORT=3306
DB_NAME=moma_bread
DB_USER=moma_app
DB_PASSWORD=password-user-moma-bread
DB_NETWORK=pamit_pamit_network
```

Ganti `DB_PASSWORD` dengan password user database Moma Bread yang sebenarnya.
`DB_HOST` harus berupa nama container atau network alias yang dapat di-resolve
di network tersebut. Jangan gunakan `localhost`: dari container aplikasi,
`localhost` menunjuk ke container aplikasi itu sendiri.
Port `8080` sudah dipakai container `pamit-app`, jadi panduan ini memakai
`8081` untuk Moma Bread. Setelah berjalan, buka `http://localhost:8081`.

Jalankan hanya service aplikasi dengan file Compose khusus ini (jangan memakai
`compose.yaml`, karena file tersebut juga menjalankan database baru):

```powershell
docker compose -f compose.shared-db.yaml up -d --build
docker compose -f compose.shared-db.yaml ps
```

Import dump ke database `moma_bread` pada server yang sudah ada, bukan ke
database container baru. Contoh PowerShell:

```powershell
$webContainer = docker compose -f compose.shared-db.yaml ps -q web
docker cp .\moma_bread.sql "${webContainer}:/tmp/moma_bread.sql"
docker compose -f compose.shared-db.yaml exec web sh -c 'mariadb -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < /tmp/moma_bread.sql'
docker compose -f compose.shared-db.yaml exec web rm /tmp/moma_bread.sql
```

Image aplikasi menyertakan client MariaDB untuk perintah restore ini. Jika
database sudah berisi data Moma Bread, lewati langkah import.

## Update dan operasi

- Setelah perubahan di-push ke branch `main`, jalankan `update.bat` di folder
  checkout repository pada Windows server yang menjalankan Docker Desktop.
  Jalankan dari PowerShell:

  ```powershell
  .\update.bat
  ```

  Script memeriksa Docker Desktop dan `.env`, memastikan checkout berada di
  branch `main` tanpa perubahan tracked lokal, mengambil commit terbaru dengan
  `git pull --ff-only`, lalu membangun dan menjalankan ulang hanya service web
  melalui `compose.shared-db.yaml`. Database `pamit-db` dan volume tidak
  dihentikan atau dihapus. Jika perintah gagal, script menampilkan error dan
  berhenti.

- Update tidak berjalan otomatis saat `git push` dilakukan dari komputer lain.
  Jalankan `update.bat` setelah push; otomatisasi dapat dibuat kemudian dengan
  Windows Task Scheduler untuk menjalankannya berkala.
- Log aplikasi: `docker compose logs -f web`
- Log database (hanya untuk database bawaan): `docker compose logs -f db`
- Hentikan container tanpa menghapus data: `docker compose down`
- Jangan menjalankan `docker compose down -v` kecuali memang ingin menghapus
  database, upload, dan backup secara permanen.

Jika memakai database bersama, gunakan `docker compose -f compose.shared-db.yaml`
untuk perintah Compose. Menghentikan aplikasi Moma Bread tidak akan menghentikan
container database yang dipakai aplikasi lain.

Untuk backup, gunakan fitur backup database di panel admin dan simpan hasilnya
di luar mesin Docker. Docker volumes melindungi data dari pembuatan ulang
container, tetapi bukan pengganti backup terpisah.

## Deploy ke server

Di server Linux, clone repository, buat `.env` dengan password yang kuat,
kemudian jalankan langkah build yang sama. Buka hanya port yang diperlukan di
firewall. Untuk domain publik, letakkan reverse proxy dengan HTTPS di depan
aplikasi dan jangan mengekspos port database `3306` ke internet. Pertahankan
Docker volumes dan buat backup database berkala.
