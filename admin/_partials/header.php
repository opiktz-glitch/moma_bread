<?php
/**
 * Layout panel admin.
 *
 * Variabel yang diharapkan:
 *   $pageTitle string   Judul halaman
 *   $activeTab string   Tab menu yang aktif (dashboard|manage.php?tab=..|settings)
 *
 * @package MomaBread
 */

// Cegah browser menyimpan halaman admin. Tanpa baris ini, Chrome bisa
// menampilkan data lama (mis. daftar jenis) padahal isinya sudah berubah.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../_schema.php';

$pageTitle   = $pageTitle ?? 'Panel Admin';
$activeTab   = $activeTab ?? '';
$adminUser   = current_user();
$entities    = admin_entities();
$flashes     = take_flashes();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> - Admin Moma Bread</title>
<link rel="icon" href="<?= e(asset('img/logo.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="adm">

<aside class="side">
  <div class="brand">
    <img src="<?= e(asset('img/logo.png')) ?>" alt="Moma Bread">
    <span>Panel Admin</span>
  </div>

  <nav class="sidenav">
    <a href="<?= e(url('admin/index.php')) ?>" class="<?= $activeTab === 'dashboard' ? 'on' : '' ?>">
      <span>🏠</span> Dashboard
    </a>

    <!-- Variabel loop memakai $ent (bukan $def) agar tidak menimpa
         $def milik manage.php yang dipakai setelah include file ini. -->
    <?php foreach ($entities as $table => $ent): ?>
      <?php $entKey = entity_key($table); ?>
      <a href="<?= e(url('admin/manage.php?tab=' . $entKey)) ?>" class="<?= $activeTab === $entKey ? 'on' : '' ?>">
        <span><?= e($ent['icon']) ?></span> <?= e($ent['label']) ?>
      </a>
    <?php endforeach; ?>

    <a href="<?= e(url('admin/settings.php')) ?>" class="<?= $activeTab === 'settings' ? 'on' : '' ?>">
      <span>⚙️</span> Pengaturan
    </a>

    <div class="navsep">Keamanan</div>

    <a href="<?= e(url('admin/backup.php')) ?>" class="<?= $activeTab === 'backup' ? 'on' : '' ?>">
      <span>💾</span> Backup Database
    </a>

    <a href="<?= e(url('admin/backup-gambar.php')) ?>" class="<?= $activeTab === 'backup-gambar' ? 'on' : '' ?>">
      <span>🖼️</span> Backup Gambar
    </a>

    <a href="<?= e(url('admin/password.php')) ?>" class="<?= $activeTab === 'password' ? 'on' : '' ?>">
      <span>🔒</span> Ganti Password
    </a>
  </nav>

  <div class="sidefoot">
    <a class="btn ghost sm block" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">Lihat Landing Page ↗</a>
    <a class="btn ghost sm block" href="<?= e(url('admin/logout.php')) ?>">Keluar</a>
  </div>
</aside>

<main class="main">
  <header class="topbar">
    <h1><?= e($pageTitle) ?></h1>
    <div class="user">
      <span>Halo, <strong><?= e($adminUser['full_name'] ?: $adminUser['username']) ?></strong></span>
    </div>
  </header>

  <?php foreach ($flashes as $f): ?>
    <div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div>
  <?php endforeach; ?>
