# Deploy Moma Bread dengan Docker di Windows 11

Konfigurasi ini membuat container web dan container MariaDB khusus Moma Bread.
Database memakai Docker volume persisten terpisah dari kode aplikasi dan dari
database milik stack `pamit`. Upload gambar dan backup aplikasi juga disimpan
di volume tersendiri. MariaDB tidak membuka port ke jaringan host; web
terhubung ke database melalui network internal Compose.

## Prasyarat

- Docker Desktop berjalan di server Windows 11.
- Git for Windows sudah terpasang.
- File backup database `.sql` jika ingin memindahkan data dari tzserver.

Repository tidak menyertakan `install.php` atau dump database. Untuk
memindahkan situs yang sudah ada, unduh dump melalui fitur **Backup Database**
di panel admin sebelum memulai.

## Deploy pertama

1. **Push konfigurasi Docker ke GitHub.** Dari komputer pengembangan, commit
   dan push perubahan yang ingin dipakai server ke branch `main`. Jangan
   sertakan `config/config.php`, `.env`, atau file backup.

2. **Clone repository di server.** Buka PowerShell:

   ```powershell
   New-Item -ItemType Directory -Force C:\apps
   Set-Location C:\apps
   git clone https://github.com/opiktz-glitch/moma_bread.git
   Set-Location C:\apps\moma_bread
   ```

3. **Siapkan rahasia environment.** Salin template lalu edit:

   ```powershell
   Copy-Item .env.example .env
   notepad .env
   ```

   Ganti `DB_PASSWORD` dan `DB_ROOT_PASSWORD` dengan password kuat dan berbeda.
   Atur `APP_PORT=8081`; port `8080` sudah digunakan `pamit-app`. File `.env`
   hanya disimpan di server dan tidak boleh di-commit.

   Kredensial `DB_NAME`, `DB_USER`, dan password akan dipakai untuk inisialisasi
   database khusus pertama kali. Setelah database dibuat, mengubah nilai
   environment database saja tidak mengubah akun/kata sandi yang sudah ada
   dalam volume MariaDB.

4. **Build dan jalankan kedua container:**

   ```powershell
   docker compose up -d --build
   docker compose ps
   ```

   Tunggu sampai service `db` berstatus `healthy`. Container MariaDB ini
   terpisah dari `pamit-db`; Moma Bread tidak bergantung pada network atau
   database stack `pamit`. Buka `http://localhost:8081` di server. Dari komputer
   lain di kantor, gunakan `http://IP-SERVER:8081`. Jika tidak dapat diakses,
   izinkan port `8081` pada Windows Firewall.

5. **Pindahkan data lama jika diperlukan.** Jika ingin memakai database dari
   tzserver, unduh dump `.sql` dari fitur **Backup Database** di panel admin.
   Dari PowerShell pada folder repository, sesuaikan lokasi file:

   ```powershell
   $dbContainer = docker compose ps -q db
   docker cp C:\path\ke\moma_bread.sql "${dbContainer}:/tmp/moma_bread.sql"
   docker compose exec db sh -c 'mariadb -u"$MARIADB_USER" -p"$MARIADB_PASSWORD" "$MARIADB_DATABASE" < /tmp/moma_bread.sql'
   docker compose exec db rm /tmp/moma_bread.sql
   ```

   Import dapat mengganti data yang sudah ada dalam database Moma Bread.
   Lewati langkah ini untuk instalasi database baru. Setelah import, buka
   `/admin/login.php` dan gunakan akun admin dari dump.

6. **Pindahkan gambar upload bila dibutuhkan.** Dump database tidak menyertakan
   file gambar. Salin gambar lama ke volume upload Moma Bread secara terpisah.
   Panduan ini menggunakan Docker volume; folder upload dapat dipindah ke bind
   mount Windows kemudian, dengan menyalin data terlebih dahulu.

## Update setelah push

Workflow GitHub Actions akan otomatis berjalan setiap kali ada push ke branch
`main`. Workflow memakai self-hosted runner Windows yang sudah terpasang di
server, lalu memanggil `C:\TZ_FIles\Moma_bread\update.bat`. Runner harus
memiliki label standar `self-hosted`, `windows`, dan `x64`, serta berjalan
dengan akun Windows yang dapat mengakses folder aplikasi dan Docker Desktop.

Pastikan runner terlihat **Online** di **GitHub → Settings → Actions → Runners**.
Setelah workflow di-push, uji dengan push perubahan ke `main`, lalu pantau
hasilnya di tab **Actions**. Jika job gagal karena label, sesuaikan
`runs-on` pada `.github/workflows/deploy-docker.yml` dengan label runner Anda.

Untuk menjalankan update manual di PowerShell server:

```powershell
Set-Location C:\TZ_FIles\Moma_bread
.\update.bat
```

Script memeriksa Docker Desktop, file `.env`, branch `main`, dan perubahan
tracked lokal; lalu mengambil perubahan terbaru dengan `git pull --ff-only`,
memvalidasi Compose, serta menjalankan `docker compose up -d --build`. Perintah
memperbarui service web dan mempertahankan database, upload, serta backup di
volumes.

Jika checkout memiliki perubahan lokal, script berhenti agar tidak menimpa
perubahan tersebut. Simpan atau commit perubahan terlebih dahulu, lalu jalankan
kembali.

Self-hosted runner memberi workflow hak untuk menjalankan perintah pada server.
Batasi akses push ke `main` hanya untuk maintainer tepercaya, dan jangan aktifkan
workflow deployment ini untuk pull request dari kontributor yang tidak dipercaya.

## Operasi dan keamanan data

- Status container: `docker compose ps`
- Log aplikasi: `docker compose logs -f web`
- Log database: `docker compose logs -f db`
- Hentikan container tanpa menghapus data: `docker compose down`
- Jangan gunakan `docker compose down -v`: opsi `-v` menghapus volume database,
  upload, dan backup.

Nama project Compose dikunci sebagai `moma-bread`. Volume database bernama
`moma-bread_database`, sehingga tidak bergantung pada MariaDB atau volume stack
`pamit`. Menghapus container web saja tidak menghapus database. Tetap buat
backup database berkala dan simpan di luar server Docker; volume bukan backup.
