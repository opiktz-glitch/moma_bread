<?php
/**
 * Footer landing page.
 *
 * @package MomaBread
 */

$siteName  = s('site_name', 'Moma Bread');
$address   = s('address');
$hours     = s('hours');
$email     = s('email');
$instagram = s('instagram');

$footerText = s('footer_text', '© ' . date('Y') . ' ' . $siteName);
?>
<div class="check"></div>

<footer>
  <div class="wrap">
    <img src="<?= e(img(s('logo'), 'logo.png')) ?>" alt="<?= e($siteName) ?>">

    <?php if ($address !== '' || $hours !== '' || $email !== '' || $instagram !== ''): ?>
      <div class="finfo">
        <?php if ($address !== ''): ?>
          <p><strong>Alamat</strong><br><?= e($address) ?></p>
        <?php endif; ?>
        <?php if ($hours !== ''): ?>
          <p><strong>Buka</strong><br><?= e($hours) ?></p>
        <?php endif; ?>
        <?php if ($email !== ''): ?>
          <p><strong>Email</strong><br>
            <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>
          </p>
        <?php endif; ?>
        <?php if ($instagram !== ''): ?>
          <p><strong>Instagram</strong><br>
            <a href="https://instagram.com/<?= e(ltrim($instagram, '@')) ?>" target="_blank" rel="noopener">@<?= e(ltrim($instagram, '@')) ?></a>
          </p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="fbot">
      <span><?= e($footerText) ?></span>
    </div>
  </div>
</footer>

<!-- Tombol pesan WhatsApp yang menempel di layar ponsel -->
<a class="wafloat" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener">
  Pesan via WhatsApp
</a>

<button type="button" class="totop" id="toTop" aria-label="Kembali ke atas" title="Kembali ke atas">&uarr;</button>

<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
