<?php
$judul = 'Beranda Dashboard';
$aktif = 'beranda';
$extraCss = ['assets/css/index.css'];
require __DIR__ . '/includes/header.php';
$pdo = db();
$hariIni = date('Y-m-d');

// Data Stok Menipis
$menipis = $pdo->query('SELECT nama, stok, stok_minimum FROM produk WHERE aktif = 1 AND stok <= stok_minimum ORDER BY stok ASC, nama LIMIT 5')->fetchAll();

// Data 5 Transaksi Terakhir untuk Widget Baru
$trxTerbaru = $pdo->query("SELECT no_struk, tanggal, total, metode, status FROM transaksi ORDER BY tanggal DESC LIMIT 5")->fetchAll();

?>
<?php if (is_admin()):
    $hari  = ringkasan($hariIni, $hariIni);
    $bulan = ringkasan(date('Y-m-01'), $hariIni);

    // Hitung Total Kas (All Time)
    $kas_all = $pdo->query("SELECT COALESCE(SUM(untung),0) FROM transaksi WHERE status='selesai'")->fetchColumn();
    // Hitung Total Produk & Fisik Stok
    $infoStok = $pdo->query("SELECT COUNT(id) as jml_produk, COALESCE(SUM(stok),0) as jml_fisik FROM produk WHERE aktif = 1")->fetch();

    $st = $pdo->prepare("SELECT DATE(tanggal) d, SUM(untung) untung FROM transaksi WHERE status='selesai' AND DATE(tanggal) BETWEEN ? AND ? GROUP BY DATE(tanggal)");
    $st->execute([date('Y-m-d', strtotime('-6 days')), $hariIni]);
    $h = $st->fetchAll(PDO::FETCH_KEY_PAIR);
    $lbl = []; $val = [];
    for ($i = 6; $i >= 0; $i--) {
        $t = strtotime("-$i days");
        $lbl[] = HARI[(int)date('w', $t)]; $val[] = (int)($h[date('Y-m-d', $t)] ?? 0);
    }
    
    // Produk Terlaris Bulan Ini
    $top = $pdo->prepare("SELECT ti.nama_produk nama, SUM(ti.qty) qty, SUM(ti.untung) untung FROM transaksi_item ti JOIN transaksi t ON t.id = ti.transaksi_id
        WHERE t.status='selesai' AND DATE(t.tanggal) BETWEEN ? AND ? GROUP BY ti.nama_produk ORDER BY untung DESC LIMIT 5");
    $top->execute([date('Y-m-01'), $hariIni]);
    $top = $top->fetchAll();
?>

<!-- BARIS 1: Keuangan Harian & Bulanan -->
<div class="row g-3 mb-3">
  <div class="col-12 col-md-6 col-xl-3"><?= kpi('Laba Hari Ini', rp($hari['untung']), 'trending-up', 'hijau', (int)$hari['trx'] . ' transaksi', 'text-untung') ?></div>
  <div class="col-12 col-md-6 col-xl-3"><?= kpi('Omzet Hari Ini', rp($hari['omzet']), 'dollar-sign', 'merah', 'Uang Kotor') ?></div>
  <div class="col-12 col-md-6 col-xl-3"><?= kpi('Laba Bulan Ini', rp($bulan['untung']), 'bar-chart-2', 'hijau', (int)$bulan['trx'] . ' transaksi', 'text-untung') ?></div>
  <div class="col-12 col-md-6 col-xl-3"><?= kpi('Omzet Bulan Ini', rp($bulan['omzet']), 'layers', 'biru', 'Uang Kotor') ?></div>
</div>

<!-- BARIS 2: Statistik Sistem KKWU -->
<div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kartu p-3 d-flex align-items-center stat-cyan">
            <div class="me-3 stat-ico"><?= ic('briefcase') ?></div>
            <div>
                <div class="text-muted small fw-bold text-uppercase">Total Kas KKWU</div>
                <div class="fs-4 fw-bold text-dark"><?= rp($kas_all) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kartu p-3 d-flex align-items-center stat-red">
            <div class="me-3 stat-ico"><?= ic('users') ?></div>
            <div>
                <div class="text-muted small fw-bold text-uppercase">Dana Suplier (Bulan Ini)</div>
                <div class="fs-4 fw-bold text-dark"><?= rp($bulan['modal']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kartu p-3 d-flex align-items-center stat-purple">
            <div class="me-3 stat-ico"><?= ic('package') ?></div>
            <div>
                <div class="text-muted small fw-bold text-uppercase">Menu Produk</div>
                <div class="fs-4 fw-bold text-dark"><?= number_format($infoStok['jml_produk'], 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Varian</span></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="kartu p-3 d-flex align-items-center stat-amber">
            <div class="me-3 stat-ico"><?= ic('box') ?></div>
            <div>
                <div class="text-muted small fw-bold text-uppercase">Fisik Barang Titipan</div>
                <div class="fs-4 fw-bold text-dark"><?= number_format($infoStok['jml_fisik'], 0, ',', '.') ?> <span class="fs-6 fw-normal text-muted">Item</span></div>
            </div>
        </div>
    </div>
</div>

<!-- BARIS 3: Grafik & Top Produk -->
<div class="row g-3 mb-4">
  <div class="col-12 col-xl-7">
    <div class="kartu h-100 shadow-sm">
        <div class="kartu-head border-bottom">Statistik Laba 7 Hari Terakhir <a class="ms-auto small badge bg-light text-dark text-decoration-none" href="laporan.php">Lihat Lengkap</a></div>
        <div class="kartu-body p-3"><div class="grafik-wrap"><canvas id="grafik" role="img" aria-label="Grafik untung 7 hari terakhir"></canvas></div></div>
    </div>
  </div>
  
  <div class="col-12 col-xl-5">
    <div class="kartu h-100 shadow-sm">
        <div class="kartu-head border-bottom">Top 5 Produk (Bulan Ini)</div>
        <div class="kartu-body p-0">
            <?php if (!$top): ?><div class="kosong py-4">Belum ada penjualan bulan ini.</div><?php endif; ?>
            <?php foreach ($top as $i => $r): ?>
            <div class="d-flex align-items-center gap-3 px-4 py-3 border-bottom">
                <span class="badge rank-badge <?= $i === 0 ? 'rank-1' : ($i === 1 ? 'rank-2' : ($i === 2 ? 'rank-3' : 'rank-n')) ?>"><?= $i + 1 ?></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-truncate text-dark"><?= e(preg_replace('/^(.*?)\s*\(([^)]+)\)$/', '$1', $r['nama'])) ?></div>
                    <div class="small text-muted">Terjual <?= (int)$r['qty'] ?> pcs</div>
                </div>
                <div class="text-success fw-bold"><?= rp($r['untung']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
  </div>
</div>

<!-- BARIS 4: Transaksi Terakhir & Peringatan Stok -->
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="kartu h-100 shadow-sm">
            <div class="kartu-head border-bottom"><?= ic('clock') ?> 5 Transaksi Terakhir <a class="ms-auto small badge bg-light text-dark text-decoration-none" href="riwayat.php">Riwayat Lengkap</a></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">No. Struk</th>
                            <th>Waktu</th>
                            <th>Metode</th>
                            <th class="text-end pe-4">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($trxTerbaru as $trx): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><a href="struk.php?id=<?= $trx['no_struk'] ?>" target="_blank" class="text-decoration-none text-primary">#<?= e($trx['no_struk']) ?></a></td>
                            <td class="text-muted small"><?= date('d M, H:i', strtotime($trx['tanggal'])) ?></td>
                            <td>
                                <?php if($trx['status'] === 'batal'): ?>
                                    <span class="badge bg-danger">Dibatalkan</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark border text-capitalize"><?= e($trx['metode']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-4 fw-bold"><?= rp($trx['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($trxTerbaru)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">Belum ada transaksi.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="kartu h-100 shadow-sm">
            <div class="kartu-head border-bottom bg-danger text-white head-stok"><?= ic('alert-triangle') ?> Peringatan Stok Menipis</div>
            <div class="kartu-body p-0">
                <?php if (!$menipis): ?><div class="kosong py-4 text-success"><?= ic('check-circle') ?> Stok barang titipan aman!</div><?php endif; ?>
                <?php foreach ($menipis as $m): ?>
                <div class="d-flex px-4 py-3 border-bottom align-items-center">
                    <span class="flex-grow-1 fw-semibold text-dark"><?= e($m['nama']) ?></span>
                    <span class="badge <?= $m['stok'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?> px-3 py-2 rounded-pill">
                        <?= $m['stok'] <= 0 ? 'Habis (0)' : 'Sisa ' . (int)$m['stok'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php
    $extraLibs = ['assets/vendor/chart.umd.js', 'assets/js/index.js'];
    $inlineJs = 'window.POS = ' . json_encode(['labels' => $lbl, 'val' => $val]) . ';';
else:
    $st = $pdo->prepare("SELECT COUNT(*) trx, COALESCE(SUM(total),0) omzet FROM transaksi WHERE status='selesai' AND user_id = ? AND DATE(tanggal) = ?");
    $st->execute([user()['id'], $hariIni]);
    $saya = $st->fetch();
?>
<!-- TAMPILAN DASHBOARD UNTUK ROLE KASIR / SISWA -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="kartu kartu-body p-4 d-flex flex-wrap align-items-center gap-4 hero-kasir">
            <div class="avatar avatar-hero">
                <?= strtoupper(substr(user()['nama'], 0, 1)) ?>
            </div>
            <div>
                <div class="h4 fw-bold mb-1">Selamat Bertugas, <?= e(user()['nama']) ?>!</div>
                <div class="opacity-75">Penjualan shift kamu hari ini: <strong><?= (int)$saya['trx'] ?> Transaksi</strong> dengan omzet <strong><?= rp($saya['omzet']) ?></strong></div>
            </div>
            <a href="kasir.php" class="btn bg-white text-dark fw-bold btn-lg ms-auto rounded-pill px-4 shadow-sm btn-masuk-kasir"><?= ic('shopping-cart') ?> Masuk ke Layar Kasir</a>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-md-6">
        <div class="kartu h-100 shadow-sm">
            <div class="kartu-head border-bottom bg-danger text-white head-stok"><?= ic('alert-triangle') ?> Peringatan Stok Menipis</div>
            <div class="kartu-body p-0">
                <?php if (!$menipis): ?><div class="kosong py-4 text-success"><?= ic('check-circle') ?> Stok barang titipan aman!</div><?php endif; ?>
                <?php foreach ($menipis as $m): ?>
                <div class="d-flex px-4 py-3 border-bottom align-items-center">
                    <span class="flex-grow-1 fw-semibold text-dark"><?= e($m['nama']) ?></span>
                    <span class="badge <?= $m['stok'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?> px-3 py-2 rounded-pill">
                        <?= $m['stok'] <= 0 ? 'Habis (0)' : 'Sisa ' . (int)$m['stok'] ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-6">
        <div class="kartu h-100 shadow-sm">
            <div class="kartu-head border-bottom"><?= ic('clock') ?> 5 Transaksi Terakhir</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">No. Struk</th>
                            <th>Metode</th>
                            <th class="text-end pe-4">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($trxTerbaru as $trx): ?>
                        <tr>
                            <td class="ps-4 fw-semibold"><a href="struk.php?id=<?= $trx['no_struk'] ?>" target="_blank" class="text-decoration-none text-primary">#<?= e($trx['no_struk']) ?></a></td>
                            <td><span class="badge bg-light text-dark border text-capitalize"><?= e($trx['metode']) ?></span></td>
                            <td class="text-end pe-4 fw-bold"><?= rp($trx['total']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($trxTerbaru)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Belum ada transaksi.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>