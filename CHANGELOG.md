# Changelog

Perubahan penting pada Moma Bread dicatat di sini, dari yang terbaru ke yang
lebih lama. Format tanggal memakai `YYYY-MM-DD`; perubahan baru yang belum
dirilis dicatat di bagian **Unreleased**.

## [Unreleased]

### Changed

- Metadata SEO, canonical, social preview, data terstruktur, sitemap, dan
  robots.txt untuk domain momabread.web.id.
- Tautan “Lompat ke konten” menuju konten utama pada beranda, halaman kategori,
  dan halaman kategori yang tidak ditemukan.
- Bagian Kontak ikut ditandai aktif pada navigasi saat terlihat.
- Drawer navigasi ponsel memenuhi tinggi layar dan dapat digulir pada layar
  pendek; efek blur header dinonaktifkan di ponsel agar drawer tidak terpotong.
- Jarak footer menjaga teks copyright dari tombol WhatsApp mengambang.
- Tombol WhatsApp pada ponsel menggunakan ikon kecil mengambang.

## [2026-11-04]

### Added

- Backup database dan gambar terjadwal.
- Penyimpanan unggahan gambar pada folder Windows host.
- Deploy Docker melalui self-hosted Windows runner.

### Fixed

- Backup database kosong dan penanganan gambar lightbox.
- Peringatan informasi Docker yang tidak fatal pada skrip backup.

## [2026-11-02]

### Added

- Panduan deploy Docker dan skrip pembaruan aplikasi.
- Panduan serta skrip deploy ke server Ubuntu tzserver.
