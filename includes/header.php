<?php
/**
 * Header landing page.
 *
 * Variabel opsional yang bisa diset sebelum include:
 *   $pageTitle  string  Judul halaman
 *   $pageDesc   string  Meta description
 *   $bodyClass  string  Kelas tambahan pada <body>
 *   $canonicalUrl string URL kanonis absolut
 *   $robots     string  Instruksi pengindeksan
 *   $structuredData array Data terstruktur Schema.org
 *
 * @package MomaBread
 */

require_once __DIR__ . '/functions.php';

$siteName    = s('site_name', 'Moma Bread');
$pageTitle   = $pageTitle ?? ($siteName . ' – ' . s('site_tagline', 'Roti Hangat Rasa Rumahan'));
$pageDesc    = $pageDesc ?? s('hero_text', 'Roti hangat Fresh dari dapur Moma.');
$bodyClass   = $bodyClass ?? '';
$canonicalUrl = $canonicalUrl ?? site_url();
$robots       = $robots ?? 'index,follow';
$socialImage  = site_url(img(s('banner'), 'banner.jpg'));

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
<meta name="robots" content="<?= e($robots) ?>">
<meta name="theme-color" content="#B8241B">
<?php if ($canonicalUrl !== ''): ?>
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<?php if ($canonicalUrl !== ''): ?>
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<meta property="og:image" content="<?= e($socialImage) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="id_ID">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($pageTitle) ?>">
<meta name="twitter:description" content="<?= e($pageDesc) ?>">
<meta name="twitter:image" content="<?= e($socialImage) ?>">
<link rel="icon" href="<?= e(img(s('logo'), 'logo.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<?php if (!empty($structuredData)): ?>
<script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?php endif; ?>

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

<a class="skip" href="#konten">Lompat ke konten</a>

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
