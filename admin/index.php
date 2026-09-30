<?php
/**
 * Dashboard panel admin.
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_schema.php';
require_login();

/* Ringkasan jumlah data per entitas. */
$stats = [];
foreach (admin_entities() as $key => $def) {
    $total  = (int) val('SELECT COUNT(*) FROM `' . entity_table($key) . '`', [], 0);
    $stats[$key] = [
        'label'  => $def['label'],
        'icon'   => $def['icon'],
        'total'  => $total,
        'active' => (int) val('SELECT COUNT(*) FROM `' . entity_table($key) . '` WHERE is_active = 1', [], 0),
    ];
}

/* Jenis roti terbaru untuk tabel ringkas. */
$recentJenis = all(
    'SELECT j.id, j.name, j.price, j.is_active, m.name AS kategori
       FROM jenis_rote j
       JOIN menu m ON m.id = j.menu_id
   ORDER BY j.id DESC
      LIMIT 5'
);

$pageTitle = 'Dashboard';
$activeTab = 'dashboard';
require __DIR__ . '/_partials/header.php';
?>

<div class="cards">
  <?php foreach ($stats as $key => $st): ?>
    <a class="stat" href="<?= e(url('admin/manage.php?tab=' . entity_key($key))) ?>">
      <span class="ico"><?= e($st['icon']) ?></span>
      <div>
        <strong><?= e((string) $st['total']) ?></strong>
        <span class="lbl"><?= e($st['label']) ?></span>
        <span class="sub"><?= e((string) $st['active']) ?> aktif di landing page</span>
      </div>
    </a>
  <?php endforeach; ?>
</div>

<div class="grid2">
  <section class="panel">
    <div class="panelhead">
      <h2>Aksi Cepat</h2>
    </div>
    <div class="panelbody quicklinks">
      <?php foreach (admin_entities() as $key => $def): ?>
        <a class="btn ghost sm" href="<?= e(url('admin/manage.php?tab=' . entity_key($key) . '&action=new')) ?>">
          + <?= e($def['add_label'] ?? ('Tambah ' . $def['label'])) ?>
        </a>
      <?php endforeach; ?>
      <a class="btn ghost sm" href="<?= e(url('admin/settings.php')) ?>">⚙️ Ubah Pengaturan Situs</a>
    </div>
  </section>

  <section class="panel">
    <div class="panelhead">
      <h2>Jenis Roti Terakhir Ditambahkan</h2>
      <a class="btn ghost xs" href="<?= e(url('admin/manage.php?tab=jenis_rote')) ?>">Lihat semua</a>
    </div>

    <?php if (!$recentJenis): ?>
      <div class="panelbody">
        <p class="muted">Belum ada jenis roti. Klik "Tambah Jenis" untuk menambahkan.</p>
      </div>
    <?php else: ?>
      <table class="table">
        <thead>
          <tr><th>Nama</th><th>Kategori</th><th>Harga</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php foreach ($recentJenis as $j): ?>
            <tr>
              <td>
                <a href="<?= e(url('admin/manage.php?tab=jenis_rote&action=edit&id=' . (int) $j['id'])) ?>">
                  <?= e($j['name']) ?>
                </a>
              </td>
              <td><?= e($j['kategori']) ?></td>
              <td><?= (int) $j['price'] > 0 ? e(rupiah($j['price'])) : '-' ?></td>
              <td>
                <span class="pill <?= (int) $j['is_active'] === 1 ? 'ok' : 'off' ?>">
                  <?= (int) $j['is_active'] === 1 ? 'Aktif' : 'Nonaktif' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</div>

<section class="panel">
  <div class="panelhead"><h2>Informasi Teknis</h2></div>
  <div class="panelbody">
    <ul class="infolist">
      <li><strong>Versi PHP</strong><span><?= e(PHP_VERSION) ?></span></li>
      <li><strong>Database</strong><span><?= e(DB_NAME) ?> di <?= e(DB_HOST) ?>:<?= e(DB_PORT) ?></span></li>
      <li><strong>Ukuran upload maksimal</strong><span><?= e((string) (MAX_UPLOAD_BYTES / 1024 / 1024)) ?> MB</span></li>
      <li><strong>Folder upload</strong><span><code><?= e(str_replace(APP_ROOT, '', UPLOAD_DIR)) ?></code></span></li>
      <li><strong>Mode debug</strong><span><?= APP_DEBUG ? 'Aktif' : 'Nonaktif' ?></span></li>
    </ul>
  </div>
</section>

<?php require __DIR__ . '/_partials/footer.php'; ?>
