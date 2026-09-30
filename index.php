<?php
/**
 * Landing page Moma Bread.
 * Seluruh isi section diambil dari database (tabel menu, keunggulan,
 * cara_pesan, galeri, testimonials) dan pengaturan situs (tabel settings).
 *
 * @package MomaBread
 */

require_once __DIR__ . '/includes/functions.php';

/* ---------------------------------------------------------------------
 |  Muat data dari database
 | -------------------------------------------------------------------- */
$dbReady     = db_is_installed();
$menuItems   = [];
$advantages  = [];
$steps       = [];
$gallery     = [];
$testimonials = [];

if ($dbReady) {
    // Level 1: kategori / menu utama (tanpa harga)
    $menuItems = all(
        'SELECT id, name, slug, description, image, is_featured
           FROM menu
          WHERE is_active = 1
       ORDER BY sort_order ASC, id ASC'
    );

    // Level 2: jenis / varian roti, diambil sekaligus lalu dikelompokkan
    $jenisByMenu = [];

    if ($menuItems) {
        $ids = array_map('intval', array_column($menuItems, 'id'));
        $ph  = implode(', ', array_fill(0, count($ids), '?'));

        $jenisRows = all(
            "SELECT id, menu_id, name, description, price, image
               FROM jenis_rote
              WHERE is_active = 1
                AND menu_id IN ($ph)
           ORDER BY sort_order ASC, id ASC",
            $ids
        );

        foreach ($jenisRows as $row) {
            $jenisByMenu[(int) $row['menu_id']][] = $row;
        }
    }

    $advantages = all(
        'SELECT id, title, description, icon
           FROM keunggulan
          WHERE is_active = 1
       ORDER BY sort_order ASC, id ASC'
    );

    $steps = all(
        'SELECT id, step_no, title, description
           FROM cara_pesan
          WHERE is_active = 1
       ORDER BY sort_order ASC, id ASC'
    );

    $gallery = all(
        'SELECT id, title, caption, image
           FROM galeri
          WHERE is_active = 1
       ORDER BY sort_order ASC, id ASC'
    );

    $testimonials = all(
        'SELECT id, name, role, quote, rating, image
           FROM testimonials
          WHERE is_active = 1
       ORDER BY sort_order ASC, id ASC'
    );
}

$waDefault = wa_link();
$pageTitle = s('site_name', 'Moma Bread') . ' â€“ ' . s('site_tagline', 'Roti Hangat Rasa Rumahan');

$bodyClass = 'home';
require __DIR__ . '/includes/header.php';
?>

<?php if (!$dbReady): ?>
  <div class="wrap">
    <div class="notice">
      Database belum siap. Jalankan <a href="<?= e(url('install.php')) ?>"><strong>install.php</strong></a>
      untuk membuat tabel, data awal, dan akun admin.
    </div>
  </div>
<?php endif; ?>

<!-- ============================ HERO ============================ -->
<div class="wrap">
  <div class="hero">
    <div>
      <span class="badge">&#128293; Dipanggang setiap hari</span>
      <h1><?= e(s('hero_title', 'Roti hangat, rasa seperti buatan Mama.')) ?></h1>
      <p><?= e(s('hero_text')) ?></p>

      <div class="cta">
        <a class="btn" href="#menu">Lihat Menu</a>
        <a class="btn o" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">Pesan via WhatsApp</a>
      </div>

      <ul class="points">
        <li>Fresh setiap pagi</li>
        <li>Bahan pilihan</li>
        <li>Antar ke rumah</li>
      </ul>
    </div>

    <div class="heroimg">
      <img src="<?= e(img(s('banner'), 'banner.jpg')) ?>" alt="Roti hangat segar dari dapur Moma Bread">
    </div>
  </div>
</div>

<div class="check"></div>

<!-- ============================ MENU ============================ -->
<?php if ($menuItems): ?>
<section id="menu">
  <div class="wrap">
    <div class="t">
      <span class="eyebrow">Menu Roti</span>
      <h2><?= e(s('menu_title', 'Menu Favorit')) ?></h2>
      <?php if (s('menu_subtitle') !== ''): ?><p><?= e(s('menu_subtitle')) ?></p><?php endif; ?>
    </div>

    <div class="grid">
      <?php foreach ($menuItems as $cat): ?>
        <?php
        $catId    = (int) $cat['id'];
        $catUrl   = menu_url((string) $cat['slug']);
        $varian   = $jenisByMenu[$catId] ?? [];
        $jmlJenis = count($varian);

        // Tampilkan maksimal 3 varian pertama, sisanya sebagai "+n".
        $chipVarian = array_slice($varian, 0, 3);
        $sisa       = $jmlJenis - count($chipVarian);

        // Harga terendah pada kategori ini (hanya sebagai petunjuk, bukan harga kategori).
        $hargaMin = null;
        foreach ($varian as $v) {
            $h = (int) $v['price'];
            if ($h > 0 && ($hargaMin === null || $h < $hargaMin)) {
                $hargaMin = $h;
            }
        }
        ?>
        <article class="card menu-card">
          <a class="thumb" href="<?= e($catUrl) ?>" tabindex="-1" aria-hidden="true">
            <?php if (!empty($cat['image'])): ?>
              <img src="<?= e(img($cat['image'])) ?>" alt="" loading="lazy">
            <?php else: ?>
              <span class="ph"><?= e(mb_strtoupper(mb_substr($cat['name'], 0, 1))) ?></span>
            <?php endif; ?>

            <?php if ((int) $cat['is_featured'] === 1): ?>
              <span class="flag">Unggulan</span>
            <?php endif; ?>
          </a>

          <div class="catbody">
            <h3><a href="<?= e($catUrl) ?>"><?= e($cat['name']) ?></a></h3>
            <p><?= e($cat['description']) ?></p>

            <?php if ($chipVarian): ?>
              <ul class="variants">
                <?php foreach ($chipVarian as $v): ?>
                  <li><?= e($v['name']) ?></li>
                <?php endforeach; ?>
                <?php if ($sisa > 0): ?>
                  <li class="more">+<?= e((string) $sisa) ?></li>
                <?php endif; ?>
              </ul>
            <?php endif; ?>

            <div class="catfoot">
              <?php if ($hargaMin !== null): ?>
                <span class="from">Mulai dari<b><?= e(rupiah($hargaMin)) ?></b></span>
              <?php else: ?>
                <span class="count"><?= e((string) $jmlJenis) ?> jenis roti</span>
              <?php endif; ?>

              <a class="btn o sm" href="<?= e($catUrl) ?>">
                Lihat <?= e((string) $jmlJenis) ?> Jenis
              </a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ========================= KEUNGGULAN ========================= -->
<?php if ($advantages): ?>
<section id="keunggulan" class="alt">
  <div class="wrap">
    <div class="t">
      <span class="eyebrow">Kenapa Moma</span>
      <h2><?= e(s('keunggulan_title', 'Kenapa Moma Bread?')) ?></h2>
    </div>

    <div class="grid">
      <?php foreach ($advantages as $i => $adv): ?>
        <article class="card">
          <div class="dot"><?= $adv['icon'] !== '' ? e($adv['icon']) : $i + 1 ?></div>
          <h3><?= e($adv['title']) ?></h3>
          <p><?= e($adv['description']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ========================== CARA PESAN ========================== -->
<?php if ($steps): ?>
<section id="pesan">
  <div class="wrap">
    <div class="t">
      <span class="eyebrow">Mudah &amp; cepat</span>
      <h2><?= e(s('pesan_title', 'Cara Pesan')) ?></h2>
    </div>

    <div class="grid">
      <?php foreach ($steps as $i => $step): ?>
        <?php $no = (int) $step['step_no'] > 0 ? (int) $step['step_no'] : $i + 1; ?>
        <article class="card">
          <div class="dot"><?= e((string) $no) ?></div>
          <h3><?= e($step['title']) ?></h3>
          <p><?= e($step['description']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ====================== PANGGILAN AKSI ======================= -->
<section class="cta2-wrap">
  <div class="wrap">
    <div class="cta2">
      <h2><?= e(s('cta_title', 'Lapar? Yuk pesan sekarang!')) ?></h2>
      <p><?= e(s('cta_text')) ?></p>
      <a class="btn" href="<?= e($waDefault) ?>" target="_blank" rel="noopener">Pesan via WhatsApp</a>
    </div>
  </div>
</section>

<!-- =========================== GALERI =========================== -->
<?php if ($gallery): ?>
<section id="galeri">
  <div class="wrap">
    <div class="t">
      <span class="eyebrow">Galeri</span>
      <h2><?= e(s('galeri_title', 'Galeri Dapur')) ?></h2>
      <?php if (s('galeri_subtitle') !== ''): ?><p><?= e(s('galeri_subtitle')) ?></p><?php endif; ?>
    </div>

    <div class="gal">
      <?php foreach ($gallery as $photo): ?>
        <figure class="gal-item">
          <img src="<?= e(img($photo['image'])) ?>" alt="<?= e($photo['title']) ?>" loading="lazy">
          <figcaption>
            <strong><?= e($photo['title']) ?></strong>
            <?php if (!empty($photo['caption'])): ?><span><?= e($photo['caption']) ?></span><?php endif; ?>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ========================= TESTIMONI ========================= -->
<?php if ($testimonials): ?>
<section id="testimoni" class="alt">
  <div class="wrap">
    <div class="t">
      <span class="eyebrow">Testimoni</span>
      <h2><?= e(s('testimoni_title', 'Kata Pelanggan')) ?></h2>
      <?php if (s('testimoni_subtitle') !== ''): ?><p><?= e(s('testimoni_subtitle')) ?></p><?php endif; ?>
    </div>

    <div class="grid">
      <?php foreach ($testimonials as $t): ?>
        <article class="card quote">
          <?= stars((int) $t['rating']) ?>
          <p><?= e($t['quote']) ?></p>
          <div class="who">
            <?php if (!empty($t['image'])): ?>
              <img src="<?= e(img($t['image'], 'logo.png')) ?>" alt="<?= e($t['name']) ?>" loading="lazy">
            <?php endif; ?>
            <div>
              <strong><?= e($t['name']) ?></strong>
              <?php if (!empty($t['role'])): ?><span><?= e($t['role']) ?></span><?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- =========================== KONTAK =========================== -->
<?php
/* Seluruh isi section ini diambil dari tabel settings (Pengaturan Situs),
   jadi bisa diubah dari panel admin tanpa menyentuh kode. */
$kontak = [
    [
        'ikon' => '&#128205;',
        'label' => 'Alamat',
        'isi'  => s('address'),
        'url'  => s('address') !== ''
            ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(s('address'))
            : '',
    ],
    [
        'ikon' => '&#128337;',
        'label' => 'Jam Buka',
        'isi'  => s('hours'),
        'url'  => '',
    ],
    [
        'ikon' => '&#9993;',
        'label' => 'Email',
        'isi'  => s('email'),
        'url'  => s('email') !== '' ? 'mailto:' . s('email') : '',
    ],
    [
        'ikon' => '&#128247;',
        'label' => 'Instagram',
        'isi'  => s('instagram') !== '' ? '@' . ltrim(s('instagram'), '@') : '',
        'url'  => s('instagram') !== ''
            ? 'https://instagram.com/' . ltrim(s('instagram'), '@')
            : '',
    ],
    [
        'ikon' => '&#128172;',
        'label' => 'WhatsApp',
        'isi'  => s('wa_number') !== '' ? '+' . s('wa_number') : '',
        'url'  => s('wa_number') !== '' ? wa_link() : '',
    ],
];

/* Buang entri yang masih kosong supaya tidak tampil kotak kosong. */
$kontak = array_values(array_filter($kontak, static fn (array $k): bool => $k['isi'] !== ''));
?>

<?php if ($kontak): ?>
<section id="kontak" class="alt">
  <div class="wrap">
    <div class="t">
      <span class="eyebrow">Hubungi Kami</span>
      <h2><?= e(s('kontak_title', 'Kontak')) ?></h2>
      <?php if (s('kontak_subtitle') !== ''): ?><p><?= e(s('kontak_subtitle')) ?></p><?php endif; ?>
    </div>

    <div class="kontak">
      <?php foreach ($kontak as $k): ?>
        <div class="card kontak-item">
          <span class="ikon" aria-hidden="true"><?= $k['ikon'] ?></span>
          <strong><?= e($k['label']) ?></strong>

          <?php if ($k['url'] !== ''): ?>
            <p><a href="<?= e($k['url']) ?>"
                   <?= $k['label'] === 'Alamat' ? 'target="_blank" rel="noopener"' : '' ?>>
              <?= e($k['isi']) ?></a></p>
          <?php else: ?>
            <p><?= e($k['isi']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="kontak-aksi">
      <a class="btn" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">
        Pesan via WhatsApp
      </a>
      <?php if (s('instagram') !== ''): ?>
        <a class="btn o" href="https://instagram.com/<?= e(ltrim(s('instagram'), '@')) ?>"
           target="_blank" rel="noopener">Chat Instagram</a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
