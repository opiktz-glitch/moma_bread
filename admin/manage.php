<?php
/**
 * CRUD generik untuk seluruh entitas konten.
 *
 * Dipakai lewat parameter GET:
 *   ?tab=menu                  -> daftar data
 *   ?tab=menu&action=new       -> form tambah
 *   ?tab=menu&action=edit&id=3 -> form ubah
 *
 * Form dikirim lewat POST ke file yang sama dengan field "do":
 *   save | delete | toggle
 *
 * @package MomaBread
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_schema.php';
require_login();

/* $tabKey = yang tertulis di URL (?tab=jenis-roti)
   $tab    = nama tabel di database (jenis_rote)
   $tabUrl = kunci URL untuk tautan (jenis-roti)                */
$tabKey = (string) ($_GET['tab'] ?? 'menu');
$tab    = entity_resolve($tabKey);
$tabUrl = entity_key($tab);
$def    = entity($tab);
$table  = entity_table($tab);
$fields = $def['fields'];
$action = (string) ($_GET['action'] ?? 'list');
$id     = (int) ($_GET['id'] ?? 0);

$errors = [];
$form   = [];

/* ==================================================================
 |  PROSES POST (hapus / aktif-nonaktif / simpan)
 * ================================================================== */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();

    $do    = (string) ($_POST['do'] ?? '');
    $rowId = (int) ($_POST['id'] ?? 0);

    /* ---- Hapus satu data ------------------------------------------ */
    if ($do === 'delete' && $rowId > 0) {
        $row = one('SELECT * FROM `' . $table . '` WHERE id = ?', [$rowId]);

        if ($row) {
            // Kategori roti tidak boleh dihapus selama masih ada jenis roti di dalamnya.
            if ($tab === 'menu') {
                $jumlah = (int) val('SELECT COUNT(*) FROM `jenis_rote` WHERE menu_id = ?', [$rowId], 0);

                if ($jumlah > 0) {
                    flash('error', 'Kategori "' . $row['name'] . '" masih memiliki ' . $jumlah
                        . ' jenis roti. Hapus atau pindahkan jenis roti tersebut terlebih dahulu.');
                    redirect('admin/manage.php?tab=menu');
                }
            }

            if (isset($row['image'])) {
                delete_upload($row['image']);   // bersihkan file hasil upload
            }

            q('DELETE FROM `' . $table . '` WHERE id = ?', [$rowId]);
            flash('success', entity_item_name($def, $row) . ' berhasil dihapus.');
        } else {
            flash('error', 'Data tidak ditemukan.');
        }

        redirect('admin/manage.php?tab=' . $tabUrl);
    }

    /* ---- Aktif / nonaktif ----------------------------------------- */
    if ($do === 'toggle' && $rowId > 0) {
        q('UPDATE `' . $table . '` SET is_active = 1 - is_active WHERE id = ?', [$rowId]);
        flash('success', 'Status tampilan berhasil diperbarui.');

        redirect('admin/manage.php?tab=' . $tabUrl);
    }

    /* ---- Simpan (tambah / ubah) ----------------------------------- */
    if ($do === 'save') {
        $isUpdate = $rowId > 0;
        $data     = [];

        // Galat upload (mis. berkas bukan gambar / terlalu besar) ditangkap
        // lalu ditampilkan sebagai pesan di formulir, bukan fatal error.
        try {
            foreach ($fields as $field) {
                $name = $field['name'];
                $type = $field['type'];

                // --- Gambar: upload baru, hapus, atau pertahankan yang lama
                if ($type === 'image') {
                    $current = $isUpdate
                        ? (string) (val('SELECT image FROM `' . $table . '` WHERE id = ?', [$rowId]) ?? '')
                        : '';

                    if (!empty($_POST['remove_image'])) {
                        delete_upload($current);
                        $data[$name] = null;
                    } else {
                        $data[$name] = handle_image_upload($name, $current !== '' ? $current : null);
                    }

                    continue;
                }

                // --- Checkbox
                if ($type === 'bool') {
                    $data[$name] = isset($_POST[$name]) ? 1 : 0;
                    continue;
                }

                $raw = trim((string) ($_POST[$name] ?? ''));

                // --- Angka
                if ($type === 'number') {
                    $raw        = preg_replace('/[^0-9-]/', '', $raw);
                    $data[$name] = $raw === '' || $raw === '-' ? 0 : (int) $raw;
                    continue;
                }

                // --- Rating 0..5
                if ($type === 'rating') {
                    $data[$name] = max(0, min(5, (int) $raw));
                    continue;
                }

                // --- Relasi ke entitas lain (mis. jenis roti -> kategori)
                if ($type === 'select') {
                    $options     = admin_options($field['options']);
                    $data[$name] = isset($options[(int) $raw]) ? (int) $raw : 0;
                    continue;
                }

                // --- Slug dibuat otomatis bila kosong
                if ($type === 'slug') {
                    $base = $raw !== '' ? slugify($raw) : slugify((string) ($_POST[$field['from']] ?? ''));
                    $data[$name] = $base !== '' ? $base : 'item-' . time();
                    continue;
                }

                $data[$name] = empty($field['max']) ? $raw : mb_substr($raw, 0, (int) $field['max']);
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }

        // --- Validasi field wajib -----------------------------------
        foreach ($fields as $field) {
            if (empty($field['required'])) {
                continue;
            }

            $value   = $data[$field['name']] ?? '';
            $isEmpty = $field['type'] === 'image' ? empty($value) : trim((string) $value) === '';

            if ($isEmpty) {
                $errors[] = 'Field "' . $field['label'] . '" wajib diisi.';
            }
        }

        // --- Nilai kolom unik tidak boleh bentrok -------------------
        if (!$errors && !empty($def['unique'])) {
            foreach ($def['unique'] as $uniqueCol) {
                $value = (string) ($data[$uniqueCol] ?? '');

                if ($value === '') {
                    continue;
                }

                $sql    = 'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $uniqueCol . '` = ?';
                $params = [$value];

                if ($isUpdate) {
                    $sql     .= ' AND id <> ?';
                    $params[] = $rowId;
                }

                if ((int) val($sql, $params, 0) > 0) {
                    $errors[] = 'Nilai "' . $value . '" sudah dipakai data lain.';
                }
            }
        }

        // --- Simpan, atau kembali ke form dengan pesan galat ---------
        if ($errors) {
            $action = $isUpdate ? 'edit' : 'new';
            $id     = $rowId;
            $form   = $data;
        } elseif ($isUpdate) {
            $set  = implode(', ', array_map(static fn ($c) => '`' . $c . '` = ?', array_keys($data)));
            $data['__id'] = $rowId;

            q('UPDATE `' . $table . '` SET ' . $set . ' WHERE id = ?', $data);
            flash('success', entity_item_name($def, $data) . ' berhasil diperbarui.');

            redirect('admin/manage.php?tab=' . $tabUrl);
        } else {
            $cols  = implode(', ', array_map(static fn ($c) => '`' . $c . '`', array_keys($data)));
            $marks = implode(', ', array_fill(0, count($data), '?'));

            q('INSERT INTO `' . $table . '` (' . $cols . ') VALUES (' . $marks . ')', $data);

            // Ambil id hasil insert SETELAH query dijalankan.
            $newId = (int) db()->lastInsertId();

            flash('success', entity_item_name($def, $data) . ' berhasil ditambahkan.');

            redirect('admin/manage.php?tab=' . $tabUrl . '&action=edit&id=' . $newId);
        }
    }
}

/* ==================================================================
 |  PERSIAPAN DATA UNTUK DITAMPILKAN
 * ================================================================== */
$isForm = in_array($action, ['new', 'edit'], true);

/* Nilai form: dari $_POST (setelah galat) atau dari database (mode ubah). */
$form = $form ?: ($isForm
    ? ($id > 0 ? (one('SELECT * FROM `' . $table . '` WHERE id = ?', [$id]) ?? []) : [])
    : []);

/* Nilai bawaan untuk mode tambah. */
if ($isForm && !$form) {
    foreach ($fields as $field) {
        $form[$field['name']] = $field['default'] ?? '';
    }
}

/* Daftar data untuk mode list. */
$rows = $isForm ? [] : all('SELECT * FROM `' . $table . '` ORDER BY ' . $def['order']);

$pageTitle = $def['label'];
$activeTab = $tabUrl;
require __DIR__ . '/_partials/header.php';
?>

<?php if ($errors): ?>
  <div class="alert error">
    <strong>Periksa kembali isian berikut:</strong>
    <ul>
      <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- ========================== DAFTAR DATA ========================= -->
<?php if (!$isForm): ?>
  <div class="toolbar">
    <div class="count"><?= e((string) count($rows)) ?> data</div>
    <a class="btn" href="<?= e(url('admin/manage.php?tab=' . $tabUrl . '&action=new')) ?>">
      + <?= e($def['add_label'] ?? ('Tambah ' . $def['label'])) ?>
    </a>
  </div>

  <section class="panel">
    <?php if (!$rows): ?>
      <div class="panelbody">
        <p class="muted">Belum ada data. Klik tombol "Tambah" untuk menambahkan.</p>
      </div>
    <?php else: ?>
      <div class="tablewrap">
        <table class="table">
          <thead>
            <tr>
              <?php foreach ($def['columns'] as $col): ?>
                <th><?= e($col['label']) ?></th>
              <?php endforeach; ?>
              <th class="right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <?php foreach ($def['columns'] as $col): ?>
                  <td>
                    <?php
                    $value = $row[$col['name']] ?? null;

                    switch ($col['type']) {
                        case 'thumb':
                            echo empty($value)
                                ? '<span class="nothumb">-</span>'
                                : '<img class="thumb" src="' . e(img($value)) . '" alt="">';
                            break;

                        case 'price':
                            echo (int) $value > 0 ? e(rupiah($value)) : '<span class="muted">-</span>';
                            break;

                        case 'bool':
                            echo '<span class="pill ' . ((int) $value === 1 ? 'ok' : 'off') . '">'
                                . ((int) $value === 1 ? 'Ya' : 'Tidak') . '</span>';
                            break;

                        case 'stars':
                            echo stars((int) $value);
                            break;

                        case 'count':
                            $jml = (int) val($col['sql'], [(int) $row['id']], 0);
                            echo $jml > 0
                                ? '<a href="' . e(url('admin/manage.php?tab=' . entity_key('jenis_rote'))) . '">' . $jml . ' jenis</a>'
                                : '<span class="muted">0 jenis</span>';
                            break;

                        case 'fk':
                            $options = admin_options($col['options']);
                            echo isset($options[(int) $value])
                                ? e($options[(int) $value])
                                : '<span class="muted">-</span>';
                            break;

                        case 'number':
                            echo e((string) $value);
                            break;

                        default:
                            $text = trim((string) $value);
                            echo $text === '' ? '<span class="muted">-</span>' : e(excerpt($text, 70));
                    }
                    ?>
                  </td>
                <?php endforeach; ?>

                <td class="right nowrap">
                  <a class="btn ghost xs" href="<?= e(url('admin/manage.php?tab=' . $tabUrl . '&action=edit&id=' . (int) $row['id'])) ?>">Ubah</a>

                  <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="toggle">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn ghost xs" type="submit">
                      <?= (int) ($row['is_active'] ?? 0) === 1 ? 'Sembunyikan' : 'Tampilkan' ?>
                    </button>
                  </form>

                  <form method="post" class="inline"
                        onsubmit="return confirm('Hapus data ini? Tindakan tidak dapat dibatalkan.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="delete">
                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                    <button class="btn danger xs" type="submit">Hapus</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

<!-- ============================= FORM ============================ -->
<?php else: ?>
  <div class="toolbar">
    <a class="btn ghost sm" href="<?= e(url('admin/manage.php?tab=' . $tabUrl)) ?>">← Kembali ke daftar</a>
    <div><?= $id > 0 ? 'Mengubah data #' . e((string) $id) : 'Menambah data baru' ?></div>
  </div>

  <section class="panel">
    <div class="panelbody">
      <!-- action ditulis eksplisit agar tab tidak hilang saat form dikirim -->
      <form method="post" action="<?= e(url('admin/manage.php?tab=' . $tabUrl)) ?>"
            enctype="multipart/form-data" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="save">
        <input type="hidden" name="id" value="<?= (int) $id ?>">

        <?php foreach ($fields as $field): ?>
          <?php
          $name  = $field['name'];
          $type  = $field['type'];
          $value = $form[$name] ?? ($field['default'] ?? '');
          ?>
          <div class="field">
            <?php if ($type === 'bool'): ?>
              <label class="checkline">
                <input type="checkbox" name="<?= e($name) ?>" value="1" <?= (int) $value === 1 ? 'checked' : '' ?>>
                <span><?= e($field['label']) ?></span>
              </label>

            <?php else: ?>
              <label for="f_<?= e($name) ?>">
                <?= e($field['label']) ?>
                <?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?>
              </label>

              <?php if ($type === 'textarea'): ?>
                <textarea id="f_<?= e($name) ?>" name="<?= e($name) ?>" rows="4"
                          <?= !empty($field['required']) ? 'required' : '' ?>><?= e((string) $value) ?></textarea>

              <?php elseif ($type === 'image'): ?>
                <div class="imagefield">
                  <img class="preview" src="<?= e(img((string) $value, 'logo.png')) ?>" alt="Pratinjau gambar">
                  <div>
                    <input type="file" id="f_<?= e($name) ?>" name="<?= e($name) ?>" accept="image/*">
                    <?php if (!empty($value)): ?>
                      <label class="checkline">
                        <input type="checkbox" name="remove_image" value="1">
                        <span>Hapus gambar lama saat disimpan</span>
                      </label>
                    <?php endif; ?>
                  </div>
                </div>

              <?php elseif ($type === 'rating'): ?>
                <select id="f_<?= e($name) ?>" name="<?= e($name) ?>">
                  <?php for ($r = 1; $r <= 5; $r++): ?>
                    <option value="<?= $r ?>" <?= (int) $value === $r ? 'selected' : '' ?>><?= $r ?> bintang</option>
                  <?php endfor; ?>
                </select>

              <?php elseif ($type === 'select'): ?>
                <?php $options = admin_options($field['options']); ?>
                <select id="f_<?= e($name) ?>" name="<?= e($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>>
                  <option value="">-- Pilih --</option>
                  <?php foreach ($options as $optId => $optLabel): ?>
                    <option value="<?= (int) $optId ?>" <?= (int) $value === (int) $optId ? 'selected' : '' ?>>
                      <?= e($optLabel) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if (!$options): ?>
                  <small class="hint">Belum ada pilihan. Tambahkan data pada tab
                    "<?= e(entity($field['options'])['label']) ?>" terlebih dahulu.</small>
                <?php endif; ?>

              <?php else: ?>
                <input type="<?= $type === 'number' ? 'number' : 'text' ?>"
                       id="f_<?= e($name) ?>" name="<?= e($name) ?>" value="<?= e((string) $value) ?>"
                       <?= !empty($field['required']) ? 'required' : '' ?>
                       <?= !empty($field['max']) ? 'maxlength="' . (int) $field['max'] . '"' : '' ?>
                       <?= !empty($field['min']) ? 'min="' . (int) $field['min'] . '"' : '' ?>>
              <?php endif; ?>

              <?php if (!empty($field['hint'])): ?>
                <small class="hint"><?= e($field['hint']) ?></small>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <div class="formfoot">
          <button class="btn" type="submit"><?= $id > 0 ? 'Simpan Perubahan' : 'Simpan Data' ?></button>
          <a class="btn ghost" href="<?= e(url('admin/manage.php?tab=' . $tabUrl)) ?>">Batal</a>
        </div>
      </form>
    </div>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/_partials/footer.php'; ?>


