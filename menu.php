<?php
/**
 * Halaman level 2: daftar JENIS ROTI pada sebuah kategori.
 *
 * Alamat halaman:
 *   Bentuk pretty : /moma_bread/menu/signature
 *   Bentuk biasa  : /moma_bread/moma.php?kategori=signature
 *
 * @package MomaBread
 */

require_once __DIR__ . '/includes/functions.php';

$slug   = trim((string) ($_GET['kategori'] ?? ''));
$slug   = slugify($slug);
$kategori = null;
$jenisItems = [];
$lainnya = [];

if ($slug !== '' && db_is_installed()) {
    $kategori = one(
        'SELECT id, name, slug, description, image
           FROM menu
          WHERE slug = ? AND is_active = 1',
        [$slug]
    );

    if ($kategori) {
        $jenisItems = all(
            'SELECT id, name, description, price, image
               FROM jenis_rote
              WHERE menu_id = ? AND is_active = 1
           ORDER BY sort_order ASC, id ASC',
            [(int) $kategori['id']]
        );
    }

    // Kategori lain untuk navigasi silang.
    $lainnya = all(
        'SELECT name, slug
           FROM menu
          WHERE is_active = 1 AND slug <> ?
       ORDER BY sort_order ASC, id ASC',
        [$slug]
    );
}

// 404 bila kategori tidak ada.
if (!$kategori) {
    http_response_code(404);
    $pageTitle = 'Kategori tidak ditemukan';
    require __DIR__ . '/includes/header.php';
    ?>
    <section>
      <div class="wrap">
        <div class="t">
          <h2>Kategori tidak ditemukan</h2>
          <p>Maaf, halaman menu yang Anda cari tidak tersedia atau sudah disembunyikan.</p>
        </div>
        <div style="text-align:center">
          <a class="btn" href="<?= e(menu_index_url()) ?>">← Kembali ke Daftar Menu</a>
        </div>
      </div>
    </section>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$siteName = s('site_name', 'Moma Bread');
$pageTitle = $kategori['name'] . ' - Menu ' . $siteName;
$pageDesc  = (string) $kategori['description'];

require __DIR__ . '/includes/header.php';
?>

<!-- ====================== HEADER KATEGORI ====================== -->
<div class="wrap">
  <nav class="crumbs" aria-label="Breadcrumb">
    <a href="<?= e(url('index.php')) ?>">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2 12h3v8h6v-6h2v6h6v-8h3z"/></svg>
      Beranda
    </a>
    <span class="sep" aria-hidden="true">&rsaquo;</span>
    <a href="<?= e(menu_index_url()) ?>">Menu</a>
    <span class="sep" aria-hidden="true">&rsaquo;</span>
    <span aria-current="page"><?= e($kategori['name']) ?></span>
  </nav>

  <div class="cathead">
    <div class="cathead-text">
      <span class="chip">Menu Roti</span>
      <h1><?= e($kategori['name']) ?></h1>
      <p><?= e($kategori['description']) ?></p>
      <span class="count"><?= e((string) count($jenisItems)) ?> jenis roti</span>
    </div>

    <?php if (!empty($kategori['image'])): ?>
      <img class="cathead-img" src="<?= e(img($kategori['image'])) ?>" alt="<?= e($kategori['name']) ?>">
    <?php endif; ?>
  </div>
</div>

<div class="check"></div>

<!-- ====================== DAFTAR JENIS ======================== -->
<section>
  <div class="wrap">
    <div class="t">
      <h2>Jenis Roti</h2>
      <p>Pilih varian yang Anda sukai, lalu pesan langsung lewat WhatsApp.</p>
    </div>

    <?php if (!$jenisItems): ?>
      <div class="empty">
        <span class="big">&#127838;</span>
        <p>Belum ada jenis roti pada kategori <strong><?= e($kategori['name']) ?></strong>.</p>
        <a class="btn ghost sm" href="<?= e(menu_index_url()) ?>">← Lihat kategori lain</a>
      </div>
    <?php else: ?>
      <div class="grid grid-max4">
        <?php foreach ($jenisItems as $item): ?>
          <?php
          $orderMsg = 'Halo ' . $siteName . ', saya mau pesan ' . $kategori['name'] . ' - ' . $item['name']
              . ((int) $item['price'] > 0 ? ' (' . rupiah($item['price']) . ')' : '')
              . '. Mohon info ketersediaan dan cara pemesanannya, ya.';
          ?>
          <article class="card item-card">
            <?php if (!empty($item['image'])): ?>
              <div class="thumb">
                <img src="<?= e(img($item['image'])) ?>" alt="<?= e($item['name']) ?>" loading="lazy">
              </div>
            <?php endif; ?>

            <div class="catbody">
              <h3><?= e($item['name']) ?></h3>
              <p><?= e($item['description']) ?></p>

              <div class="catfoot">
                <?php if ((int) $item['price'] > 0): ?>
                  <span class="price"><?= e(rupiah($item['price'])) ?></span>
                <?php else: ?>
                  <span class="count">Hubungi untuk harga</span>
                <?php endif; ?>
              </div>

              <a class="btn o sm block" href="<?= e(wa_link($orderMsg)) ?>" target="_blank" rel="noopener">
                Pesan <?= e($item['name']) ?>
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- ================== KATEGORI LAIN (NAVIGASI) =============== -->
<?php if ($lainnya): ?>
<section class="alt">
  <div class="wrap">
    <div class="t">
      <h2>Kategori Lain</h2>
      <p>Lihat pilihan menu lain yang tersedia di Moma Bread.</p>
    </div>

    <div class="grid grid-max4">
      <?php foreach ($lainnya as $other): ?>
        <a class="card linkcard" href="<?= e(menu_url((string) $other['slug'])) ?>">
          <h3><?= e($other['name']) ?></h3>
          <span>Lihat jenis roti →</span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
