<?php
/**
 * Sitemap XML halaman publik.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/xml; charset=utf-8');

$pages = [site_url()];
if (db_is_installed()) {
    foreach (all('SELECT slug FROM menu WHERE is_active = 1 ORDER BY sort_order ASC, id ASC') as $category) {
        $pages[] = site_url(menu_url((string) $category['slug']));
    }
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as $page): ?>
  <url><loc><?= e($page) ?></loc></url>
<?php endforeach; ?>
</urlset>
