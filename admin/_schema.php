<?php
/**
 * Definisi entitas untuk CRUD generik di admin/manage.php.
 *
 * Satu entitas = satu tabel. Definisi di sini menentukan:
 *   - label   : judul di menu & halaman
 *   - order   : urutan tampil di listing
 *   - columns : kolom yang ditampilkan pada tabel listing
 *   - fields  : field form tambah/ubah
 *   - unique  : kolom yang nilainya harus unik
 *
 * Menambah entitas baru cukup dengan menambah satu blok di dalam
 * fungsi admin_entities() - tanpa perlu menambah file baru.
 *
 * @package MomaBread
 */

/**
 * Daftar seluruh entitas yang dikelola panel admin.
 *
 * Kunci array adalah NAMA TABEL di database. Alamat URL memakai
 * "key" (bukan underscore), contoh: tabel `jenis_rote` -> URL ?tab=jenis-roti
 *
 * @return array<string, array<string, mixed>>
 */
function admin_entities(): array
{
    return [

        /* ==============================================================
         |  MENU ROTI - LEVEL 1 (kategori)
         |  Contoh: Signature, Classic, Tawar
         |  Kategori TIDAK memiliki harga, hanya deskripsi.
         * ============================================================== */
        'menu' => [
            'label'    => 'Menu Roti',
            'icon'     => '🍞',
            'order'    => 'sort_order ASC, id ASC',
            'unique'   => ['slug'],
            'columns'  => [
                ['name' => 'image',       'label' => 'Gambar',    'type' => 'thumb'],
                ['name' => 'name',        'label' => 'Nama',      'type' => 'text'],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'text'],
                ['name' => 'jumlah',      'label' => 'Jenis',     'type' => 'count',
                 'sql'  => 'SELECT COUNT(*) FROM jenis_rote WHERE menu_id = ? AND is_active = 1'],
                ['name' => 'is_featured', 'label' => 'Unggulan',  'type' => 'bool'],
                ['name' => 'is_active',   'label' => 'Aktif',     'type' => 'bool'],
            ],
            'fields'   => [
                ['name' => 'name',        'label' => 'Nama Kategori', 'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'slug',        'label' => 'Slug URL',       'type' => 'slug', 'from' => 'name', 'hint' => 'Dipakai pada alamat halaman, dibuat otomatis dari nama bila dikosongkan.'],
                ['name' => 'description', 'label' => 'Deskripsi',      'type' => 'textarea'],
                ['name' => 'image',       'label' => 'Gambar Kategori', 'type' => 'image', 'hint' => 'JPG/PNG/WEBP, maksimal 2 MB.'],
                ['name' => 'is_featured', 'label' => 'Tampilkan sebagai unggulan', 'type' => 'bool'],
                ['name' => 'is_active',   'label' => 'Tampilkan di landing page', 'type' => 'bool', 'default' => 1],
                ['name' => 'sort_order',  'label' => 'Urutan tampil',  'type' => 'number', 'hint' => 'Angka kecil tampil lebih dulu.'],
            ],
        ],

        /* ==============================================================
         |  JENIS ROTI - LEVEL 2 (varian, ADA harga)
         |  Contoh: Signature - Kopi / Signature / Matcha
         * ============================================================== */
        'jenis_rote' => [
            'label'     => 'Jenis Roti',
            'add_label' => 'Tambah Jenis',
            'key'       => 'jenis-roti',
            'icon'      => '🥐',
            'order'     => 'menu_id ASC, sort_order ASC, id ASC',
            'columns'   => [
                ['name' => 'image',       'label' => 'Gambar',    'type' => 'thumb'],
                ['name' => 'name',        'label' => 'Nama',      'type' => 'text'],
                ['name' => 'menu_id',     'label' => 'Menu Roti', 'type' => 'fk', 'options' => 'menu'],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'text'],
                ['name' => 'price',       'label' => 'Harga',     'type' => 'price'],
                ['name' => 'is_active',   'label' => 'Aktif',     'type' => 'bool'],
            ],
            'fields'    => [
                ['name' => 'menu_id',     'label' => 'Menu Roti',  'type' => 'select', 'options' => 'menu', 'required' => true,
                 'hint' => 'Pilih Menu Roti tempat jenis roti ini berada.'],
                ['name' => 'name',        'label' => 'Nama Jenis',  'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'description', 'label' => 'Deskripsi',   'type' => 'textarea'],
                ['name' => 'price',       'label' => 'Harga (Rp)', 'type' => 'number', 'min' => 0],
                ['name' => 'image',       'label' => 'Gambar',      'type' => 'image', 'hint' => 'JPG/PNG/WEBP, maksimal 2 MB.'],
                ['name' => 'is_active',   'label' => 'Tampilkan di halaman kategori', 'type' => 'bool', 'default' => 1],
                ['name' => 'sort_order',  'label' => 'Urutan tampil', 'type' => 'number', 'hint' => 'Angka kecil tampil lebih dulu.'],
            ],
        ],

        /* ==============================================================
         |  KEUNGGULAN
         * ============================================================== */
        'keunggulan' => [
            'label'    => 'Keunggulan',
            'icon'     => '⭐',
            'order'    => 'sort_order ASC, id ASC',
            'columns'  => [
                ['name' => 'icon',        'label' => 'Ikon', 'type' => 'text'],
                ['name' => 'title',       'label' => 'Judul', 'type' => 'text'],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'text'],
                ['name' => 'is_active',   'label' => 'Aktif', 'type' => 'bool'],
            ],
            'fields'   => [
                ['name' => 'title',       'label' => 'Judul',   'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'icon',        'label' => 'Ikon',    'type' => 'text', 'max' => 16, 'hint' => 'Emoji atau satu huruf, contoh: 🔥 atau S'],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
                ['name' => 'is_active',   'label' => 'Tampilkan di landing page', 'type' => 'bool', 'default' => 1],
                ['name' => 'sort_order',  'label' => 'Urutan tampil', 'type' => 'number'],
            ],
        ],

        /* ==============================================================
         |  CARA PESAN
         * ============================================================== */
        'cara_pesan' => [
            'label'    => 'Cara Pesan',
            'icon'     => '🛵',
            'order'    => 'sort_order ASC, id ASC',
            'columns'  => [
                ['name' => 'step_no',     'label' => 'No', 'type' => 'number'],
                ['name' => 'title',       'label' => 'Judul', 'type' => 'text'],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'text'],
                ['name' => 'is_active',   'label' => 'Aktif', 'type' => 'bool'],
            ],
            'fields'   => [
                ['name' => 'step_no',     'label' => 'Nomor langkah', 'type' => 'number', 'min' => 1],
                ['name' => 'title',       'label' => 'Judul',    'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
                ['name' => 'is_active',   'label' => 'Tampilkan di landing page', 'type' => 'bool', 'default' => 1],
                ['name' => 'sort_order',  'label' => 'Urutan tampil', 'type' => 'number'],
            ],
        ],

        /* ==============================================================
         |  GALERI
         * ============================================================== */
        'galeri' => [
            'label'    => 'Galeri',
            'icon'     => '🖼️',
            'order'    => 'sort_order ASC, id ASC',
            'columns'  => [
                ['name' => 'image',      'label' => 'Gambar', 'type' => 'thumb'],
                ['name' => 'title',      'label' => 'Judul', 'type' => 'text'],
                ['name' => 'caption',    'label' => 'Keterangan', 'type' => 'text'],
                ['name' => 'is_active',  'label' => 'Aktif', 'type' => 'bool'],
            ],
            'fields'   => [
                ['name' => 'title',      'label' => 'Judul',     'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'image',      'label' => 'Gambar',    'type' => 'image', 'required' => true, 'hint' => 'JPG/PNG/WEBP, maksimal 2 MB.'],
                ['name' => 'caption',    'label' => 'Keterangan', 'type' => 'text', 'max' => 255],
                ['name' => 'is_active',  'label' => 'Tampilkan di landing page', 'type' => 'bool', 'default' => 1],
                ['name' => 'sort_order', 'label' => 'Urutan tampil', 'type' => 'number'],
            ],
        ],

        /* ==============================================================
         |  TESTIMONI
         * ============================================================== */
        'testimonials' => [
            'label'    => 'Testimoni',
            'icon'     => '💬',
            'order'    => 'sort_order ASC, id ASC',
            'columns'  => [
                ['name' => 'name',      'label' => 'Nama', 'type' => 'text'],
                ['name' => 'role',      'label' => 'Keterangan', 'type' => 'text'],
                ['name' => 'rating',    'label' => 'Rating', 'type' => 'stars'],
                ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'bool'],
            ],
            'fields'   => [
                ['name' => 'name',      'label' => 'Nama pelanggan', 'type' => 'text', 'required' => true, 'max' => 120],
                ['name' => 'role',      'label' => 'Keterangan', 'type' => 'text', 'max' => 120, 'hint' => 'Contoh: Pelanggan sejak 2023'],
                ['name' => 'quote',     'label' => 'Testimoni', 'type' => 'textarea', 'required' => true],
                ['name' => 'rating',    'label' => 'Rating', 'type' => 'rating', 'default' => 5],
                ['name' => 'image',     'label' => 'Foto', 'type' => 'image', 'hint' => 'Opsional, dipakai sebagai foto profil.'],
                ['name' => 'is_active', 'label' => 'Tampilkan di landing page', 'type' => 'bool', 'default' => 1],
                ['name' => 'sort_order', 'label' => 'Urutan tampil', 'type' => 'number'],
            ],
        ],

    ];
}

/**
 * Ambil definisi satu entitas.
 *
 * @throws RuntimeException Bila tab tidak dikenal.
 */
function entity(string $tab): array
{
    $entities = admin_entities();

    if (!isset($entities[$tab])) {
        http_response_code(404);
        exit('Halaman tidak ditemukan.');
    }

    return $entities[$tab];
}

/**
 * Nama tabel SQL untuk sebuah entitas.
 *
 * Nilai ini selalu berasal dari daftar putih di admin_entities(),
 * sehingga aman dipakai pada query.
 */
function entity_table(string $tab): string
{
    entity($tab);

    return $tab;
}

/**
 * Kunci URL untuk sebuah entitas.
 *
 * Dipakai agar alamat terlihat rapi, contoh:
 *   tabel jenis_rote  ->  ?tab=jenis-roti
 * Bila "key" tidak diisi, underscore otomatis diganti tanda hubung.
 */
function entity_key(string $table): string
{
    $def = entity($table);

    return $def['key'] ?? str_replace('_', '-', $table);
}

/**
 * Terjemahkan kunci URL menjadi nama tabel.
 *
 * Kunci lama (mis. ?tab=jenis_rote) tetap dikenali agar tautan lama
 * atau bookmark tidak ikut rusak.
 */
function entity_resolve(string $key): string
{
    foreach (admin_entities() as $table => $def) {
        if ($key === ($def['key'] ?? str_replace('_', '-', $table))) {
            return $table;
        }
    }

    // Fallback: izinkan nama tabel langsung demi kompatibilitas.
    return $key;
}

/**
 * Daftar nama kolom yang boleh diisi lewat form (berdasarkan definisi fields).
 *
 * @return array<int, string>
 */
function entity_writable_columns(string $tab): array
{
    return array_column(entity($tab)['fields'], 'name');
}

/**
 * Nama barang untuk ditampilkan pada pesan flash.
 *
 * Dipakai supaya pesan berbunyi "Kopi berhasil dihapus." dan bukan
 * "Jenis Roti berhasil dihapus."
 *
 * @param array<string, mixed> $def Definisi entitas.
 * @param array<string, mixed> $data Nilai dari $_POST / baris database.
 */
function entity_item_name(array $def, array $data = []): string
{
    foreach (['name', 'title'] as $col) {
        if (!empty($data[$col]) && is_string($data[$col])) {
            return trim($data[$col]);
        }
    }

    // Entitas tanpa kolom name/title (mis. cara_pesan) memakai label.
    return (string) ($def['label'] ?? 'Data');
}

/**
 * Ambil daftar pilihan untuk field bertipe select / fk.
 *
 * Mengembalikan array id => nama dari tabel entitas yang dirujuk.
 * Nama tabel selalu divalidasi terhadap daftar putih admin_entities(),
 * sehingga aman dipakai pada query.
 *
 * @return array<int, string>
 */
function admin_options(string $tab): array
{
    static $cache = [];

    if (isset($cache[$tab])) {
        return $cache[$tab];
    }

    $table = entity_table($tab);   // lempar 404 bila tab tak dikenal
    $out   = [];

    foreach (all('SELECT id, name FROM `' . $table . '` ORDER BY name ASC') as $row) {
        $out[(int) $row['id']] = (string) $row['name'];
    }

    $cache[$tab] = $out;

    return $out;
}

