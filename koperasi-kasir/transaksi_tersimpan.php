<?php
$judul = 'Transaksi Tersimpan';
$aktif = 'tersimpan';
require __DIR__ . '/includes/header.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pdo->prepare("DELETE FROM transaksi WHERE id = ? AND status = 'tersimpan'")->execute([(int)($_POST['id'] ?? 0)]);
    flash('Transaksi tersimpan dihapus.');
    redirect('transaksi_tersimpan.php');
}

// Draft > 6 bulan dihapus otomatis (stok tidak dipotong sebelum dibayar, jadi aman)
$pdo->exec("DELETE FROM transaksi WHERE status = 'tersimpan' AND tanggal < (NOW() - INTERVAL 6 MONTH)");

$rows = $pdo->query("SELECT t.id, t.tanggal, t.total, t.catatan,
        (SELECT GROUP_CONCAT(nama_produk ORDER BY id SEPARATOR ', ') FROM transaksi_item WHERE transaksi_id = t.id) AS item
        FROM transaksi t WHERE t.status = 'tersimpan' ORDER BY t.tanggal DESC")->fetchAll();
$grup = [];
foreach ($rows as $r) $grup[date('Y-m-d', strtotime($r['tanggal']))][] = $r;
?>
<div class="info-bar mb-3"><?= ic('info', 'fs-5') ?><span>Transaksi yang tersimpan lebih dari 6 bulan akan otomatis dihapus.</span></div>
<div class="input-group mb-3">
  <span class="input-group-text bg-white"><?= ic('search') ?></span>
  <input id="cari" class="form-control" placeholder="Cari catatan atau nama produk" autocomplete="off">
</div>

<div class="kartu overflow-hidden" id="daftar">
<?php if (!$rows): ?>
  <div class="kosong"><?= ic('bookmark') ?>Belum ada transaksi tersimpan.<br>Tekan <strong>Simpan</strong> di layar Transaksi untuk menyimpan pesanan yang belum dibayar.</div>
<?php endif; ?>
<?php foreach ($grup as $tgl => $list): ?>
  <div class="grup-tgl" data-grup><?= e(tgl_id($tgl)) ?></div>
  <?php foreach ($list as $r): $judulRow = $r['catatan'] ?: $r['item']; ?>
    <div class="tersimpan-row" data-teks="<?= e(mb_strtolower(($r['catatan'] ?? '') . ' ' . $r['item'])) ?>">
      <?= ic('file-text') ?>
      <a href="kasir.php?lanjut=<?= (int)$r['id'] ?>" class="text-decoration-none text-reset flex-grow-1 min-w-0">
        <div class="fw-semibold text-truncate"><?= e($judulRow) ?></div>
        <?php if ($r['catatan']): ?><div class="small text-muted text-truncate"><?= e($r['item']) ?></div><?php endif; ?>
        <div><?= rp($r['total']) ?></div>
      </a>
      <span class="jam"><?= e(date('H:i', strtotime($r['tanggal']))) ?></span>
      <form method="post" data-confirm="Hapus transaksi tersimpan ini?">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <button class="btn btn-sm btn-garis" aria-label="Hapus"><?= ic('trash-2') ?></button>
      </form>
    </div>
  <?php endforeach; ?>
<?php endforeach; ?>
</div>
<?php
$extraLibs = ['assets/js/transaksi_tersimpan.js'];
require __DIR__ . '/includes/footer.php';
