<?php
/**
 * Header landing page.
 *
 * Variabel opsional yang bisa diset sebelum include:
 *   $pageTitle  string  Judul halaman
 *   $pageDesc   string  Meta description
 *   $bodyClass  string  Kelas tambahan pada <body>
 *
 * @package MomaBread
 */

require_once __DIR__ . '/functions.php';

$siteName    = s('site_name', 'Moma Bread');
$pageTitle   = $pageTitle ?? ($siteName . ' – ' . s('site_tagline', 'Roti Hangat Rasa Rumahan'));
$pageDesc    = $pageDesc ?? s('hero_text', 'Roti hangat Fresh dari dapur Moma.');
$bodyClass   = $bodyClass ?? '';

/**
 * Menu navigasi utama.
 *
 * Di halaman utama tautannya berupa anchor (#menu) agar tidak memuat halaman
 * baru. Pada halaman lain (mis. menu.php) anchor tersebut diarahkan ke
 * index.php#menu supaya tetap berfungsi.
 */
$isHome   = ($bodyClass === 'home');
$navItems = [
    '#menu'      => 'Menu',
    '#keunggulan' => 'Keunggulan',
    '#pesan'     => 'Cara Pesan',
    '#galeri'    => 'Galeri',
    '#testimoni' => 'Testimoni',
    '#kontak'    => 'Kontak',
];

/** Ubah "#menu" menjadi "/index.php#menu" bila sedang bukan di halaman utama. */
$navHref = static function (string $anchor) use ($isHome): string {
    return $isHome ? $anchor : url('index.php') . $anchor;
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta name="theme-color" content="#B8241B">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:image" content="<?= e(img(s('banner'), 'banner.jpg')) ?>">
<meta property="og:type" content="website">
<link rel="icon" href="<?= e(img(s('logo'), 'logo.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">

<!-- Terapkan tema tersimpan sebelum halaman digambar agar tidak berkedip. -->
<script>
(function () {
    try {
        var saved = localStorage.getItem('mb-theme');
        if (saved === 'dark' || saved === 'light') {
            document.documentElement.setAttribute('data-theme', saved);
        }
    } catch (e) { /* localStorage diblokir, abaikan */ }
})();
</script>
</head>
<body class="<?= e($bodyClass) ?>">

<a class="skip" href="#menu">Lompat ke konten</a>

<header>
  <div class="wrap">
    <nav aria-label="Navigasi utama">
      <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="<?= e($siteName) ?> - Beranda">
        <img src="<?= e(img(s('logo'), 'logo.png')) ?>" alt="<?= e($siteName) ?>">
      </a>

      <div class="navlinks" id="navlinks">
        <button type="button" class="navclose" id="navClose" aria-label="Tutup menu">&times;</button>

        <?php foreach ($navItems as $href => $label): ?>
          <a class="h" href="<?= e($navHref($href)) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>

        <button type="button" class="themebtn" id="themeToggle"
                title="Ganti terang/gelap" aria-label="Ganti tema terang atau gelap">&#9788;</button>

        <a class="btn sm" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">Pesan</a>
      </div>

      <button type="button" class="navtoggle" id="navToggle"
              aria-label="Buka menu" aria-expanded="false" aria-controls="navlinks">&#9776;</button>
    </nav>
  </div>
</header>

<div class="navoverlay" id="navOverlay"></div>
