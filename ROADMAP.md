# Roadmap Moma Bread

Roadmap ini adalah daftar usulan pengembangan, bukan janji rilis atau jadwal.
Urutan dapat berubah sesuai kebutuhan operasional, kapasitas, dan keputusan
pemilik aplikasi. Semua item berstatus **Usulan** sampai disetujui dan
dikerjakan.

## Prioritas

### 1. Keamanan akses admin dan penanganan error

**Status: Usulan · Prioritas tinggi**

- Batasi percobaan login dengan jeda bertahap atau rate limit, tanpa
  mengungkapkan apakah username tertentu terdaftar.
- Atur cookie sesi secara eksplisit: `HttpOnly`, `SameSite`, `Secure` saat
  HTTPS, strict session mode, dan kebijakan masa tidak aktif yang sesuai.
- Tampilkan pesan umum untuk kegagalan koneksi database kepada pengunjung;
  simpan detail teknis di log yang tidak dapat diakses publik.
- Ubah logout menjadi POST dengan token CSRF agar logout tidak dipicu oleh
  permintaan GET lintas situs.

**Selesai bila:** alur login/logout dan sesi diuji untuk sukses, gagal,
kedaluwarsa, dan permintaan lintas situs; halaman publik tidak membocorkan
detail internal saat database gagal.

### 2. Keandalan rilis dan validasi otomatis

**Status: Usulan · Prioritas tinggi**

- Tambahkan pemeriksaan otomatis untuk sintaks PHP dan whitespace pada pull
  request sebelum deploy.
- Bangun pengujian regresi untuk helper penting dan alur publik/admin yang
  paling sering digunakan.
- Dokumentasikan langkah smoke test sesudah deployment dan langkah pemulihan
  ketika update gagal.

**Selesai bila:** perubahan mendapat pemeriksaan konsisten sebelum merge, dan
prosedur verifikasi pascadeploy dapat diikuti tanpa menebak langkah.

### 3. Perubahan skema dan pemulihan data

**Status: Usulan · Prioritas tinggi**

- Dokumentasikan skema database terkini dan cara menerapkan perubahan skema
  secara aman pada instalasi yang sudah berjalan.
- Evaluasi mekanisme migrasi yang sederhana sebelum menambah atau mengubah
  kolom/tabel.
- Uji restore backup database dan gambar pada lingkungan non-produksi, bukan
  hanya pembuatan arsipnya.
- Tetapkan retensi, lokasi salinan off-site, dan checklist pemulihan.

**Selesai bila:** perubahan database dapat diterapkan tanpa kehilangan data dan
backup terbukti dapat dipulihkan.

### 4. Validasi upload dan integritas pengaturan

**Status: Usulan · Prioritas menengah**

- Perkuat validasi gambar dengan MIME berdasarkan isi file, batas dimensi/pixel,
  dan konfigurasi server yang mencegah eksekusi file pada direktori upload.
- Evaluasi decode/encode ulang gambar jika dukungan runtime dan kualitas hasil
  dapat dipastikan.
- Validasi seluruh nilai pengaturan sebelum menyimpan; gunakan transaksi agar
  satu pengiriman tidak meninggalkan sebagian perubahan saat validasi gagal.

**Selesai bila:** upload yang tidak valid ditolak tanpa meninggalkan file, dan
pengaturan tersimpan seluruhnya atau tidak tersimpan sama sekali.

### 5. Penguatan header keamanan

**Status: Usulan · Prioritas menengah**

- Uji Content Security Policy dalam mode `Report-Only` terlebih dahulu;
  inventarisasi JavaScript inline dan dependensi sebelum menerapkan kebijakan
  pemblokiran.
- Pertimbangkan HSTS hanya setelah HTTPS dipastikan aktif dan konsisten di semua
  host yang tercakup.

**Selesai bila:** header diuji pada konfigurasi produksi tanpa merusak alur
halaman dan perubahan hanya diaktifkan dengan cakupan yang tepat.

### 6. Pengalaman mobile dan aksesibilitas panel admin

**Status: Usulan · Prioritas menengah**

- Ganti deretan tautan admin yang membungkus pada layar kecil dengan navigasi
  yang lebih ringkas dan mudah dibuka/tutup.
- Sajikan tabel CRUD sebagai kartu atau pertahankan scroll horizontal yang jelas
  serta mudah digunakan dengan sentuhan.
- Uji tombol simpan sticky pada layar pendek saat keyboard ponsel terbuka agar
  tidak menutupi field.
- Periksa kontras, fokus keyboard, label kontrol, dan umpan balik status di
  seluruh halaman admin.

**Selesai bila:** alur admin utama dapat diselesaikan pada layar ponsel tanpa
kontrol tertutup, navigasi membingungkan, atau kehilangan akses keyboard.

### 7. Kinerja gambar dan pengalaman mobile publik

**Status: Usulan · Prioritas menengah**

- Sempurnakan palet visual tanpa meninggalkan merah merek pada logo:
  - merah merek `#B8241B` untuk tombol pemesanan dan aksen utama;
  - cokelat gelap `#3A241E` untuk judul dan teks utama;
  - krem hangat `#FFF9F0` untuk latar, putih `#FFFFFF` untuk kartu, dan pasir
    `#E9D9C8` untuk border/pemisah;
  - sage `#58745C` dan madu `#B7792B` hanya sebagai aksen sekunder atau status
    yang sesuai, bukan dekorasi berlebihan.
- Terapkan palet konsisten di halaman publik dan admin; pertahankan admin lebih
  netral dan gunakan warna merah terutama untuk aksi penting.
- Sesuaikan dark mode secara khusus, bukan sekadar membalik warna light mode.
- Ukur rasio kontras teks, tombol, dan indikator status sebelum memilih warna
  final; jangan gunakan warna aksen berkontras rendah untuk teks kecil.
- Ukur ukuran file dan waktu muat gambar pada jaringan ponsel.
- Pertimbangkan resize/thumbnail dan format gambar modern jika dukungan runtime
  dan kualitas gambar sudah diuji.
- Uji halaman katalog dengan jumlah gambar dan kategori yang mendekati data
  produksi.
- Pertahankan pemeriksaan responsif, akses keyboard, dan tautan pemesanan pada
  perubahan UI berikutnya.

**Selesai bila:** halaman tetap mudah digunakan pada ponsel; warna memiliki
kontras yang memadai, konsisten dengan identitas merek, dan nyaman pada tema
terang maupun gelap; beban gambar tidak berlebihan berdasarkan pengukuran nyata.

### 8. Operasional dan pemantauan

**Status: Usulan · Prioritas menengah**

- Tentukan health check aplikasi yang membedakan web aktif dari database siap.
- Dokumentasikan pemantauan status container, ruang disk upload/backup, dan
  status workflow deploy.
- Beri panduan respons untuk kegagalan koneksi database, kapasitas disk, dan
  kegagalan backup.

**Selesai bila:** kondisi operasional penting terlihat dan ada langkah
penanganan yang jelas.

### 9. Penyempurnaan alur panel admin

**Status: Usulan · Prioritas berikutnya**

- Tambahkan pencarian dan filter daftar berdasarkan nama, kategori, dan status
  aktif bila jumlah konten membuat daftar sulit dikelola.
- Lengkapi petunjuk form dengan contoh deskripsi produk, satuan harga, rasio
  foto yang disarankan, dan panduan penulisan langkah pemesanan.
- Evaluasi pratinjau konten sebelum publikasi agar admin dapat memeriksa teks,
  foto, dan harga sebelum menampilkannya ke pelanggan.
- Evaluasi status ketersediaan produk yang terpisah dari status tampil /
  sembunyi, sehingga produk habis sementara tidak perlu dihapus atau
  disembunyikan permanen.
- Tinjau pengurutan item, validasi formulir, dan pesan kesalahan berdasarkan
  penggunaan admin.
- Berikan konfirmasi yang jelas untuk tindakan destruktif dan umpan balik
  setelah operasi berhasil atau gagal.
- Prioritaskan peningkatan berdasarkan masukan pengguna panel, bukan menambah
  fitur yang belum dibutuhkan.

**Selesai bila:** admin dapat menemukan, memperbarui, mengurutkan, dan
memeriksa konten dengan jelas; status ketersediaan tidak menghapus data; dan
aksi destruktif memberi konfirmasi yang memadai.

## Di luar cakupan sampai ada kebutuhan

Belum ada kebutuhan terkonfirmasi untuk memecah aplikasi menjadi layanan/API,
mengganti framework, menambah antrean pekerjaan, atau mengadopsi platform
hosting baru. Pertimbangkan perubahan besar tersebut hanya bila ada masalah
terukur atau kebutuhan bisnis yang membenarkannya.

## Menambahkan atau mengubah item roadmap

- Tulis masalah atau kebutuhan terlebih dahulu, bukan langsung memilih solusi.
- Cantumkan prioritas, status, dan kriteria selesai yang dapat diverifikasi.
- Tandai item selesai setelah perubahan diimplementasikan dan divalidasi;
  hapus atau revisi item yang tidak lagi relevan.
- Untuk permintaan atau insiden spesifik, tautkan issue/pull request terkait
  setelah dibuat.
