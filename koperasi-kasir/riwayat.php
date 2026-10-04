<?php
$judul = 'Riwayat Transaksi';
$aktif = 'riwayat';
require __DIR__ . '/includes/header.php';
$pdo = db();

// Batalkan transaksi (admin): stok dikembalikan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (!is_admin()) { http_response_code(403); exit('Khusus admin.'); }
    $id = (int)($_POST['id'] ?? 0);
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT no_struk, status FROM transaksi WHERE id = ? FOR UPDATE");
    $st->execute([$id]);
    $t = $st->fetch();
    if ($t && $t['status'] === 'selesai') {
        $now = date('Y-m-d H:i:s');
        $items = $pdo->prepare('SELECT produk_id, qty FROM transaksi_item WHERE transaksi_id = ? AND produk_id IS NOT NULL');
        $items->execute([$id]);
        $upd = $pdo->prepare('UPDATE produk SET stok = stok + ? WHERE id = ?');
        $log = $pdo->prepare("INSERT INTO stok_log (produk_id, jumlah, tipe, keterangan, user_id, created_at) VALUES (?, ?, 'batal', ?, ?, ?)");
        foreach ($items->fetchAll() as $i) {
            $upd->execute([$i['qty'], $i['produk_id']]);
            $log->execute([$i['produk_id'], $i['qty'], 'Batal ' . $t['no_struk'], user()['id'], $now]);
        }
        $pdo->prepare("UPDATE transaksi SET status = 'batal' WHERE id = ?")->execute([$id]);
        $pdo->commit();
        flash('Transaksi ' . $t['no_struk'] . ' dibatalkan dan stok dikembalikan.');
    } else {
        $pdo->rollBack();
        flash('Transaksi tidak bisa dibatalkan.', 'warning');
    }
    redirect('riwayat.php?' . http_build_query(['dari' => $_GET['dari'] ?? '', 'sampai' => $_GET['sampai'] ?? '', 'q' => $_GET['q'] ?? '']));
}

$dari   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : date('Y-m-d', strtotime('-6 days'));
$sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : date('Y-m-d');
$q      = trim($_GET['q'] ?? '');

$where = "t.status IN ('selesai','batal') AND DATE(t.tanggal) BETWEEN ? AND ?";
$par = [$dari, $sampai];
if ($q !== '') { $where .= ' AND t.no_struk LIKE ?'; $par[] = '%' . $q . '%'; }
if (!is_admin()) { $where .= ' AND t.user_id = ?'; $par[] = user()['id']; }

$st = $pdo->prepare("SELECT t.*, u.nama AS kasir, (SELECT SUM(qty) FROM transaksi_item WHERE transaksi_id = t.id) AS jml
                     FROM transaksi t LEFT JOIN users u ON u.id = t.user_id
                     WHERE $where ORDER BY t.tanggal DESC LIMIT 300");
$st->execute($par);
$rows = $st->fetchAll();

$trx = 0; $omzet = 0; $untung = 0;
foreach ($rows as $r) if ($r['status'] === 'selesai') { $trx++; $omzet += $r['total']; $untung += $r['untung']; }
$metodeLabel = ['tunai' => 'Tunai', 'qris' => 'QRIS', 'transfer' => 'Transfer'];
$qs = http_build_query(['dari' => $dari, 'sampai' => $sampai, 'q' => $q]);
?>
<form class="kartu kartu-body mb-3 row g-2 align-items-end" method="get">
  <div class="col-6 col-md-3"><label class="form-label small fw-semibold mb-1">Dari</label><input type="date" name="dari" class="form-control" value="<?= e($dari) ?>"></div>
  <div class="col-6 col-md-3"><label class="form-label small fw-semibold mb-1">Sampai</label><input type="date" name="sampai" class="form-control" value="<?= e($sampai) ?>"></div>
  <div class="col-8 col-md-4"><label class="form-label small fw-semibold mb-1">No. struk</label><input name="q" class="form-control" value="<?= e($q) ?>" placeholder="Contoh: KS260921"></div>
  <div class="col-4 col-md-2"><button class="btn btn-merah w-100">Tampilkan</button></div>
</form>

<div class="row g-3 mb-3">
  <div class="col-6 col-md-<?= is_admin() ? 4 : 6 ?>"><?= kpi('Transaksi', (string)$trx, 'file-text', 'abu') ?></div>
  <div class="col-6 col-md-<?= is_admin() ? 4 : 6 ?>"><?= kpi('Omzet', rp($omzet), 'dollar-sign', 'merah') ?></div>
  <?php if (is_admin()): ?><div class="col-12 col-md-4"><?= kpi('Untung', rp($untung), 'trending-up', 'hijau', '', 'text-untung') ?></div><?php endif; ?>
</div>

<div class="kartu"><div class="tabel-scroll">
<table class="tabel">
  <thead><tr><th>No. struk</th><th>Waktu</th><th>Kasir</th><th>Bayar</th><th class="num">Item</th><th class="num">Total</th><?php if (is_admin()): ?><th class="num">Untung</th><?php endif; ?><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $batal = $r['status'] === 'batal'; ?>
    <tr class="<?= $batal ? 'text-muted' : '' ?>">
      <td class="fw-semibold"><?= e($r['no_struk']) ?></td>
      <td><?= e(tgl_pendek($r['tanggal'])) ?>, <?= e(date('H:i', strtotime($r['tanggal']))) ?></td>
      <td><?= e($r['kasir'] ?? '-') ?></td>
      <td><?= e($metodeLabel[$r['metode']]) ?></td>
      <td class="num"><?= (int)$r['jml'] ?></td>
      <td class="num"><?= rp($r['total']) ?></td>
      <?php if (is_admin()): ?><td class="num <?= $batal ? '' : 'text-untung fw-semibold' ?>"><?= rp($r['untung']) ?></td><?php endif; ?>
      <td><?= $batal ? '<span class="chip merah">Batal</span>' : '<span class="chip hijau">Selesai</span>' ?></td>
      <td class="text-nowrap text-end">
        <a class="btn btn-sm btn-garis" href="struk.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener" aria-label="Lihat struk"><?= ic('file-text') ?></a>
        <?php if (is_admin() && !$batal): ?>
        <form method="post" action="riwayat.php?<?= e($qs) ?>" class="d-inline" data-confirm="Batalkan transaksi <?= e($r['no_struk']) ?>? Stok akan dikembalikan.">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-sm btn-garis text-danger" aria-label="Batalkan"><?= ic('x-circle') ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if (!$rows): ?><div class="kosong"><?= ic('file-text') ?>Belum ada transaksi pada periode ini.</div><?php endif; ?>
</div></div>
<?php if (count($rows) === 300): ?><p class="small text-muted mt-2">Menampilkan 300 transaksi terbaru. Persempit rentang tanggal untuk melihat yang lain.</p><?php endif; ?>
<?php require __DIR__ . '/includes/footer.php';
