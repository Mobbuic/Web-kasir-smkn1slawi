<?php
require_once __DIR__ . '/includes/init.php';
require_login();

$st = db()->prepare("SELECT t.*, u.nama AS kasir FROM transaksi t LEFT JOIN users u ON u.id = t.user_id WHERE t.id = ? AND t.status <> 'tersimpan'");
$st->execute([(int)($_GET['id'] ?? 0)]);
$t = $st->fetch();
if (!$t) { http_response_code(404); exit('Struk tidak ditemukan.'); }
$it = db()->prepare('SELECT nama_produk, qty, harga_jual, subtotal FROM transaksi_item WHERE transaksi_id = ?');
$it->execute([$t['id']]);
$items = $it->fetchAll();
$metode = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'][$t['metode']];
?>
<!doctype html>
<html lang="id">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<title>Struk <?= e($t['no_struk']) ?></title>
</head>
<body<?= !empty($_GET['print']) ? ' data-autoprint="1"' : '' ?>>
<div class="struk">
  <div class="tengah"><strong><?= e(setting('nama_koperasi', 'Koperasi Sekolah')) ?></strong><br><?= e(setting('alamat')) ?></div>
  <hr>
  <div class="row2"><span>No</span><span><?= e($t['no_struk']) ?></span></div>
  <div class="row2"><span>Waktu</span><span><?= e(date('d/m/Y H:i', strtotime($t['tanggal']))) ?></span></div>
  <div class="row2"><span>Kasir</span><span><?= e($t['kasir'] ?? '-') ?></span></div>
  <?php if ($t['status'] === 'batal'): ?><div class="tengah"><strong>*** DIBATALKAN ***</strong></div><?php endif; ?>
  <hr>
  <?php foreach ($items as $i): ?>
    <div><?= e($i['nama_produk']) ?></div>
    <div class="row2"><span><?= (int)$i['qty'] ?> x <?= number_format($i['harga_jual'], 0, ',', '.') ?></span><span><?= number_format($i['subtotal'], 0, ',', '.') ?></span></div>
  <?php endforeach; ?>
  <hr>
  <div class="row2"><strong>Total</strong><strong><?= rp($t['total']) ?></strong></div>
  <div class="row2"><span><?= e($metode) ?></span><span><?= rp($t['bayar']) ?></span></div>
  <div class="row2"><span>Kembali</span><span><?= rp($t['kembalian']) ?></span></div>
  <hr>
  <div class="tengah"><?= e(setting('footer_struk')) ?></div>
</div>
<div class="text-center mb-4 no-print">
  <button class="btn btn-merah" data-action="print"><?= ic('printer') ?> Cetak</button>
  <button class="btn btn-garis" data-action="close">Tutup</button>
</div>
<script src="assets/js/struk.js"></script>
</body>
</html>
