<?php
/**
 * Halaman pengaturan situs (kelola tabel settings).
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_schema.php';
require_login();

/** Daftar setting beserta jenis inputnya. */
function settings_fields(): array
{
    return [
        'site_name'      => ['label' => 'Nama Toko',            'group' => 'Identitas',  'type' => 'text'],
        'site_tagline'   => ['label' => 'Tagline / Deskripsi Singkat', 'group' => 'Identitas', 'type' => 'text'],
        'logo'           => ['label' => 'Logo (nama file di assets/img)', 'group' => 'Identitas', 'type' => 'text'],
        'banner'         => ['label' => 'Banner Hero (nama file di assets/img)', 'group' => 'Identitas', 'type' => 'text'],

        'hero_title'     => ['label' => 'Judul Hero',          'group' => 'Hero', 'type' => 'text', 'max' => 160],
        'hero_text'      => ['label' => 'Teks Hero',           'group' => 'Hero', 'type' => 'textarea'],

        'wa_number'      => ['label' => 'Nomor WhatsApp',      'group' => 'Pemesanan', 'type' => 'text', 'hint' => 'Format internasional tanpa tanda +, contoh 628129578513'],
        'wa_message'     => ['label' => 'Pesan Default WhatsApp', 'group' => 'Pemesanan', 'type' => 'textarea'],

        'menu_title'     => ['label' => 'Judul Section Menu',  'group' => 'Judul Section', 'type' => 'text'],
        'menu_subtitle'  => ['label' => 'Subjudul Menu',       'group' => 'Judul Section', 'type' => 'text'],
        'keunggulan_title' => ['label' => 'Judul Keunggulan', 'group' => 'Judul Section', 'type' => 'text'],
        'pesan_title'    => ['label' => 'Judul Cara Pesan',    'group' => 'Judul Section', 'type' => 'text'],
        'galeri_title'   => ['label' => 'Judul Galeri',        'group' => 'Judul Section', 'type' => 'text'],
        'galeri_subtitle' => ['label' => 'Subjudul Galeri',    'group' => 'Judul Section', 'type' => 'text'],
        'testimoni_title' => ['label' => 'Judul Testimoni',    'group' => 'Judul Section', 'type' => 'text'],
        'testimoni_subtitle' => ['label' => 'Subjudul Testimoni', 'group' => 'Judul Section', 'type' => 'text'],
        'kontak_title'   => ['label' => 'Judul Section Kontak', 'group' => 'Judul Section', 'type' => 'text'],
        'kontak_subtitle' => ['label' => 'Subjudul Kontak',   'group' => 'Judul Section', 'type' => 'text'],

        'cta_title'      => ['label' => 'Judul Blok Pesan',    'group' => 'Call To Action', 'type' => 'text'],
        'cta_text'       => ['label' => 'Teks Blok Pesan',     'group' => 'Call To Action', 'type' => 'textarea'],

        'address'        => ['label' => 'Alamat',              'group' => 'Kontak', 'type' => 'textarea'],
        'hours'          => ['label' => 'Jam Buka',            'group' => 'Kontak', 'type' => 'text'],
        'email'          => ['label' => 'Email',               'group' => 'Kontak', 'type' => 'text'],
        'instagram'      => ['label' => 'Instagram (tanpa @)', 'group' => 'Kontak', 'type' => 'text'],
        'footer_text'    => ['label' => 'Teks Footer',         'group' => 'Kontak', 'type' => 'text'],
    ];
}

$fields = settings_fields();
$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();

    foreach ($fields as $key => $meta) {
        $value = trim((string) ($_POST[$key] ?? ''));

        if (!empty($meta['max'])) {
            $value = mb_substr($value, 0, (int) $meta['max']);
        }

        if ($key === 'wa_number') {
            $value = preg_replace('/\D+/', '', $value);

            if ($value !== '' && (strlen($value) < 9 || strlen($value) > 15)) {
                $errors[] = 'Nomor WhatsApp tidak valid (harus 9-15 digit).';
                continue;
            }
        }

        q(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [$key, $value]
        );
    }

    if ($errors) {
        foreach ($errors as $er) {
            flash('error', $er);
        }
    } else {
        flash('success', 'Pengaturan situs berhasil disimpan.');
    }

    redirect('admin/settings.php');
}

/* Susun form per kelompok. */
$groups = [];
foreach ($fields as $key => $meta) {
    $groups[$meta['group']][] = ['key' => $key] + $meta;
}

$pageTitle = 'Pengaturan Situs';
$activeTab = 'settings';
require __DIR__ . '/_partials/header.php';
?>

<div class="toolbar">
  <div class="count">Perubahan langsung dipakai oleh landing page.</div>
  <a class="btn ghost sm" href="<?= e(url('index.php')) ?>" target="_blank">Lihat Hasil ↗</a>
</div>

<form method="post" class="form">
  <?= csrf_field() ?>

  <?php foreach ($groups as $group => $items): ?>
    <section class="panel">
      <div class="panelhead"><h2><?= e($group) ?></h2></div>
      <div class="panelbody">

        <?php foreach ($items as $meta): ?>
          <?php $key = $meta['key']; ?>
          <div class="field">
            <label for="s_<?= e($key) ?>"><?= e($meta['label']) ?></label>

            <?php if ($meta['type'] === 'textarea'): ?>
              <textarea id="s_<?= e($key) ?>" name="<?= e($key) ?>" rows="3"><?= e(s($key)) ?></textarea>
            <?php else: ?>
              <input type="text" id="s_<?= e($key) ?>" name="<?= e($key) ?>"
                     value="<?= e(s($key)) ?>"
                     <?= !empty($meta['max']) ? 'maxlength="' . (int) $meta['max'] . '"' : '' ?>>
            <?php endif; ?>

            <?php if (!empty($meta['hint'])): ?>
              <small class="hint"><?= e($meta['hint']) ?></small>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

      </div>
    </section>
  <?php endforeach; ?>

  <div class="formfoot sticky">
    <button class="btn" type="submit">Simpan Pengaturan</button>
  </div>
</form>

<?php require __DIR__ . '/_partials/footer.php'; ?>
