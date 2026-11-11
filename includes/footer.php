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

<!-- Tombol WhatsApp mengambang di layar ponsel -->
<a class="wafloat" href="<?= e(wa_link()) ?>" target="_blank" rel="noopener"
   aria-label="Pesan via WhatsApp" title="Pesan via WhatsApp">
  <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path d="M12 2a9.8 9.8 0 0 0-8.42 14.82L2.3 21.7l5.02-1.25A9.8 9.8 0 1 0 12 2Zm0 17.8a8 8 0 0 1-4.08-1.12l-.29-.17-2.98.74.77-2.9-.19-.3A8 8 0 1 1 12 19.8Zm4.4-5.98c-.24-.12-1.41-.7-1.63-.78-.22-.08-.38-.12-.54.12-.16.24-.62.78-.76.94-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.43-1.34-1.67-.14-.24-.02-.37.1-.49.1-.1.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.47-.4-.4-.54-.4h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2s.86 2.32.98 2.48c.12.16 1.69 2.58 4.1 3.62.57.25 1.02.4 1.37.5.58.18 1.1.15 1.52.09.46-.07 1.41-.58 1.61-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28Z"/>
  </svg>
</a>

<button type="button" class="totop" id="toTop" aria-label="Kembali ke atas" title="Kembali ke atas">&uarr;</button>

<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
