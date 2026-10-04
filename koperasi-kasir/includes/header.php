<?php
require_once __DIR__ . '/init.php';
require_login();
$judul = $judul ?? 'Kasir';
$aktif = $aktif ?? '';
$pos   = $pos ?? false;
$u     = user();
$namaKop  = setting('nama_koperasi', 'Kasir KKWU SMKN 1 Slawi');
$jmlDraft = (int)db()->query("SELECT COUNT(*) FROM transaksi WHERE status='tersimpan'")->fetchColumn();

// Menu Sidebar
$grup = [
  'Operasional' => [
    ['beranda',   'index.php',              'home',          'Beranda',             false],
    ['kasir',     'kasir.php',              'shopping-cart', 'Transaksi',           false],
    ['tersimpan', 'transaksi_tersimpan.php','bookmark',      'Transaksi Tersimpan', false],
    ['riwayat',   'riwayat.php',            'file-text',     'Riwayat Transaksi',   false],
  ],
  'Manajemen' => [
    ['produk',     'produk.php',     'package',     'Kelola Produk',      true],
    ['laporan',    'laporan.php',    'trending-up', 'Laporan Keuntungan', true],
    ['pengaturan', 'pengaturan.php', 'settings',    'Pengaturan',         true],
  ],
];
$inisial = strtoupper(mb_substr($u['nama'], 0, 1));
$peran   = $u['role'] === 'admin' ? 'Admin / Pembina' : 'Kasir / Siswa';
?>
<!doctype html>
<html lang="id">
<head>
<?php include __DIR__ . '/head.php'; ?>
<title><?= e($judul) ?> · <?= e($namaKop) ?></title>
<!-- Memuat Library Pop-up SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="<?= $pos ? 'mode-pos' : '' ?>">

<div class="offcanvas <?= $pos ? '' : 'offcanvas-lg' ?> offcanvas-start sidebar" tabindex="-1" id="sidebar" aria-label="Menu utama">
  <div class="sidebar-inner">
    <div class="brand">
      <div class="brand-logo"><?= ic('briefcase') ?></div>
      <div class="min-w-0">
          <div class="brand-nama text-truncate">KKWU SMEA</div>
          <div class="brand-sub">SMK Negeri 1 Slawi</div>
      </div>
    </div>
    
    <nav class="side-menu mt-3">
      <?php foreach ($grup as $mGrup => $mItems):
        $mTampil = array_filter($mItems, fn($i) => !$i[4] || is_admin());
        if (!$mTampil) continue; ?>
        <div class="side-label"><?= e($mGrup) ?></div>
        <?php foreach ($mTampil as [$mKey, $mHref, $mIcon, $mLabel]): ?>
          <a href="<?= $mHref ?>" class="side-link <?= $aktif === $mKey ? 'active' : '' ?>" <?= $aktif === $mKey ? 'aria-current="page"' : '' ?>>
            <?= ic($mIcon) ?><span><?= e($mLabel) ?></span>
            <?php if ($mKey === 'tersimpan' && $jmlDraft > 0): ?><span class="badge ms-auto badge-draft"><?= $jmlDraft ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="side-user">
      <div class="avatar avatar-side"><?= e($inisial) ?></div>
      <div class="min-w-0 flex-grow-1"><div class="fw-semibold text-truncate"><?= e($u['nama']) ?></div><div class="small text-muted"><?= e($peran) ?></div></div>
      <a href="logout.php" class="btn-icon-sm logout" title="Keluar" aria-label="Keluar"><?= ic('log-out') ?></a>
    </div>
  </div>
</div>

<div class="main-wrap">
  <header class="topbar">
    <button class="btn-icon <?= $pos ? '' : 'd-lg-none' ?>" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Buka menu"><?= ic('menu') ?></button>
    <h1 class="topbar-title"><?= e($judul) ?></h1>
    
    <div class="ms-auto d-flex align-items-center gap-3">
      <!-- Area Jam Real-Time & Sapaan Dinamis -->
      <div class="d-none d-md-flex flex-column align-items-end jam-wrap">
         <div id="sapaan-waktu" class="fw-bold d-flex align-items-center sapaan-teks">Halo!</div>
         <div id="jam-realtime" class="text-muted small fw-semibold" data-tgl="<?= e(tgl_id(date('Y-m-d'))) ?>"><?= e(tgl_id(date('Y-m-d'))) ?></div>
      </div>
      
      <span class="topbar-user d-none d-sm-inline-flex border-start ps-3">
          <span class="avatar avatar-sm avatar-top"><?= e($inisial) ?></span>
          <span class="fw-semibold text-truncate nama-top"><?= e($u['nama']) ?></span>
      </span>
    </div>
  </header>
  
  <main class="content <?= $pos ? 'content-pos' : '' ?>">
  <!-- Elemen penampung pesan flash -->
  <?php if (!empty($_SESSION['flash'])): [$fm, $ft] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div id="flash-data" data-pesan="<?= e($fm) ?>" data-tipe="<?= e($ft) ?>" class="d-none"></div>
  <?php endif; ?>