# Berkontribusi

Panduan ini berlaku untuk perbaikan bug, perubahan tampilan, fitur admin, dan
perubahan dokumentasi Moma Bread.

## Alur kerja

1. Pastikan working tree bersih dan mulai dari `main` terbaru.
2. Buat branch terpisah untuk satu perubahan yang jelas.
3. Telusuri pola yang sudah ada sebelum menambahkan helper, dependensi, atau
   abstraksi baru.
4. Implementasikan perubahan sekecil mungkin dan perbarui dokumentasi yang
   terdampak.
5. Jalankan pemeriksaan yang sesuai dan uji alur pengguna terkait.
6. Buka pull request dengan ringkasan, hasil pengujian, dan catatan konfigurasi
   atau migrasi bila diperlukan.

Push ke `main` memicu workflow deploy server Windows. Gunakan branch dan pull
request untuk perubahan rutin; jangan push langsung ke `main` kecuali memang
diizinkan dan deploy sudah dipertimbangkan.

## Persyaratan perubahan

- Pertahankan kompatibilitas dengan PHP 8+ dan MySQL/MariaDB yang digunakan
  aplikasi.
- Gunakan helper dan konvensi proyek untuk koneksi database, query, escaping,
  CSRF, autentikasi, dan otorisasi.
- Jangan menyertakan `config/config.php`, `.env`, kredensial, dump database,
  arsip backup, atau unggahan pengguna dalam commit.
- Jika mengubah struktur atau kebutuhan database, jelaskan dampaknya bagi
  instalasi yang sudah berjalan dan langkah pembaruannya.
- Perubahan UI harus tetap berfungsi di ponsel dan desktop serta tidak
  menghilangkan akses keyboard atau label yang dibutuhkan pembaca layar.

## Pemeriksaan

Tidak ada test runner otomatis yang dikonfigurasi saat ini. Pilih pemeriksaan
sesuai perubahan:

- PHP: `php -l <file-yang-diubah.php>` untuk setiap file PHP yang disentuh.
  Pada XAMPP Windows, gunakan `C:\xampp\php\php.exe -l <file>`.
- Semua perubahan: `git diff --check`.
- UI: buka halaman terkait melalui server lokal, uji alur utama, dan periksa
  tampilan ponsel serta desktop.
- Perubahan yang memakai database: uji dengan database pengembangan lokal dan
  jangan menjalankan eksperimen pada database produksi.

Jika pemeriksaan tertentu tidak bisa dijalankan, jelaskan alasannya dan hasil
validasi lain yang telah dilakukan di pull request.

## Checklist pull request

- [ ] Perubahan hanya mencakup lingkup yang diminta.
- [ ] Validasi yang relevan sudah dijalankan atau keterbatasannya dijelaskan.
- [ ] Dokumentasi dan [`CHANGELOG.md`](CHANGELOG.md) diperbarui bila perilaku
      pengguna berubah.
- [ ] Tidak ada kredensial, data produksi, atau file backup di commit.
- [ ] Dampak deploy, konfigurasi, dan database sudah dijelaskan bila relevan.
