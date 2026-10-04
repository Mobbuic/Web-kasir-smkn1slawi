<?php
require_once __DIR__ . '/includes/init.php';
require_admin();
$pdo = db();

/* ---------- Periode ---------- */
$p = $_GET['p'] ?? '7h';
$hariIni = date('Y-m-d');
switch ($p) {
    case 'hari':  $dari = $sampai = $hariIni; $label = 'Hari ini'; break;
    case 'bulan': $dari = date('Y-m-01'); $sampai = $hariIni; $label = 'Bulan ini'; break;
    case 'lalu':  $dari = date('Y-m-01', strtotime('first day of last month')); $sampai = date('Y-m-t', strtotime($dari)); $label = 'Bulan lalu'; break;
    case 'kustom':
        $dari   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['dari'] ?? '') ? $_GET['dari'] : $hariIni;
        $sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['sampai'] ?? '') ? $_GET['sampai'] : $hariIni;
        if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
        $label = 'Kustom'; break;
    default: $p = '7h'; $dari = date('Y-m-d', strtotime('-6 days')); $sampai = $hariIni; $label = '7 hari terakhir';
}

/* ---------- Data Penjualan per Produk ---------- */
$ring = ringkasan($dari, $sampai);
$margin = $ring['omzet'] > 0 ? round($ring['untung'] / $ring['omzet'] * 100, 1) : 0;

$st = $pdo->prepare("SELECT ti.nama_produk AS nama, SUM(ti.qty) AS qty, SUM(ti.subtotal) AS omzet,
        SUM(ti.qty * ti.harga_beli) AS modal, SUM(ti.untung) AS untung
    FROM transaksi_item ti JOIN transaksi t ON t.id = ti.transaksi_id
    WHERE t.status = 'selesai' AND DATE(t.tanggal) BETWEEN ? AND ?
    GROUP BY ti.nama_produk ORDER BY ti.nama_produk ASC");
$st->execute([$dari, $sampai]);
$perProduk = $st->fetchAll();

/* ---------- EXPORT FORMAT TANDA TERIMA (EXCEL .xls) ---------- */
if (($_GET['export'] ?? '') === 'ttd') {
    $filename = "TANDA_TERIMA_KKWU_" . $dari . "_SD_" . $sampai . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8"><style>';
    echo 'table { border-collapse: collapse; width: 100%; font-family: "Arial", sans-serif; font-size: 11pt; }';
    echo 'th { background-color: #92d050; color: #000000; font-weight: bold; border: 1px solid #000000; padding: 6px; text-align: left; vertical-align: middle; }';
    echo 'td { border: 1px solid #000000; padding: 6px; vertical-align: top; }';
    echo '.center { text-align: center; }';
    echo '.num { text-align: right; }';
    echo '.title { font-size: 14pt; font-weight: bold; text-align: center; color: #000000; }';
    echo '.subtitle { font-size: 12pt; font-weight: bold; text-align: center; color: #000000; }';
    echo '.ttd-kiri { text-align: left; }';
    echo '.ttd-tengah { text-align: center; }';
    echo '</style></head><body>';
    
    echo '<div class="title">TANDA TERIMA HASIL PENJUALAN KELAS KEWIRAUSAHAAN</div>';
    echo '<div class="title">SMK NEGERI 1 SLAWI</div>';
    echo '<div class="title">TAHUN 2026/2027</div>';
    echo '<div class="subtitle" style="margin-bottom: 15px;">Periode: ' . tgl_pendek($dari) . ' s/d ' . tgl_pendek($sampai) . '</div>';
    
    echo '<table>';
    echo '<thead><tr>';
    echo '<th style="width: 40px;">No.</th>';
    echo '<th style="width: 250px;">Nama Suplier</th>';
    echo '<th style="width: 250px;">Nama Produk</th>';
    echo '<th style="width: 150px; background-color: #f4b084; text-align: center;">TOTAL TERIMA</th>';
    echo '<th style="width: 150px; background-color: #ffffff; text-align: center;">TANDA TANGAN</th>';
    echo '</tr></thead><tbody>';
    
    // KAMUS PINTAR: Mencocokkan nama produk dengan format nama lengkap Excel secara otomatis
    $kamusProduk = [
        'tahu bakso' => 'Aan Saptuning Astutik,S.Pd',
        'nasi cokot' => 'Ahmad Ghozali',
        'es teh' => 'Bobi Gunawan',
        'es capcin, es pink lava,es kopi' => 'Bobi Gunawan',
        'donat gula' => 'Doni Satpam',
        'molen' => 'Fysta Sahitha,S.Pd',
        'lontong' => 'Fysta Sahitha,S.Pd',
        'gorengan' => 'Fysta Sahitha,S.Pd',
        'nanas bumbu kering' => 'Laela Fauziyah',
        'es rujak' => 'Laela Fauziyah',
        'pop mie kecil' => 'Ika Indah,S.Pd',
        'pom mie besar' => 'Ika Indah,S.Pd',
        'susu kedelai' => 'Ivan Mahendra',
        'pop mie ayam gledek' => 'M.Fahrusozi,S.Pd',
        'mie sedap cup' => 'M.Fahrusozi,S.Pd',
        'es kucir' => 'Nur Hidayati, S.Pd',
        'dimsum' => 'Wiwit Isaroh, S.Pd',
        'sosis solo' => 'Wiwit Isaroh, S.Pd',
        'roti bolen' => 'Aisya syila',
        'pie buah' => 'Aisya syila',
        'dimsum mentai' => 'Atika Agustin _XII-AK 4',
        'dimsum isi 2' => 'Atika Agustin _XII-AK 4',
        'dimsum keju goreng mentai' => 'Atika Agustin _XII-AK 4',
        'dimsum chili oil,saos' => 'Atika Agustin _XII-AK 4',
        'wonton' => 'Atika Rahmawati-XI AK 3',
        'milo' => 'Atika Rahmawati-XI AK 3',
        'makaroni pedas' => 'Bella  Auinun Nur hikmah_XI-MP 4',
        'makaroni balado' => 'Bella  Auinun Nur hikmah_XI-MP 4',
        'cireng ayam citul' => 'Cinta Putri Aulia_XI TKJ 4',
        'kebab goreng' => 'Cinta Putri Aulia_XI TKJ 4',
        'tempura' => 'Eka Gina Salsabila-XI PSPT',
        'basreng' => 'Eka Gina Salsabila-XI PSPT',
        'karipuf' => 'Eka Gina Salsabila-XI PSPT',
        'seblak' => 'Ersa YS-XI-MP 3',
        'sandwich' => 'Ersa YS-XI-MP 3',
        'risol coklat' => 'Intan Maharani _XII-TKJ 2',
        'chicken mini' => 'Intan Maharani _XII-TKJ 2',
        'pangsit' => 'Nayla Maulida P-XI AK 1',
        'siomay' => 'Nayla Maulida P-XI AK 1',
        'risol mayo' => 'Nisrina Salsabila-XI MP 4',
        'risol ayam' => 'Nisrina Salsabila-XI MP 4',
        'piscok' => 'Azkiatul Aeni XII TKJ 4',
        'ayam keju mix' => 'Usi-XI MP3',
        'cilok sambal kacang' => 'Shifa Namaira_XI AK 3',
        'papeda' => 'Sifa Nur Septiani_ XII-MP 2',
        'sosis mie gulung' => 'Utiyah Nadi Bunda _ XII-AK 3',
        'olos isi sayur' => 'Utiyah Nadi Bunda _ XII-AK 3',
        'lumpia mie' => 'Utiyah Nadi Bunda _ XII-AK 3',
        'pizza mini' => 'Vita Veni Ananta_XI BR 1',
        'cilok' => 'zazkia Nafis_X MPLB 2',
        'ketan susu keju' => 'Ukhti Ssmaroh',
        'cookies' => 'Azizah',
        'crumble crispy' => 'Marisa Ahmad',
        'martabak telor' => 'Zahra Rana',
        'kwetiau' => 'Amelia S',
        'mi lidi' => 'Sela bunga XI MP 2',
        'manisan mangga' => 'Reni Dwi A XI AK 3',
        'cireng cili oil' => 'Gaida XI AKL 4',
        'onigiri' => 'Laudia Saputri',
        'kripca' => 'Laudia Saputri',
        'cilok ayam suwir pedas' => 'Zahra Nabila Salim XI AK 3',
        'es lumut' => 'Rizky',
    ];
    
    $masterSuplier = array_unique(array_values($kamusProduk));

    $groupedData = [];
    foreach ($perProduk as $r) {
        $namaFull = $r['nama'];
        $suplier = 'Umum / Mandiri';
        $produk = $namaFull;
        
        // 1. Cek Regex jika pengguna mengetik pakai kurung misal "Tahu bakso (Aan)"
        if (preg_match('/^(.*?)\s*\(([^)]+)\)$/', $namaFull, $matches)) {
            $produk = trim($matches[1]);
            $suplierRaw = trim($matches[2]); 
            
            // Cocokkan nama suplier di dalam kurung dengan nama lengkap di kamus
            $bestMatch = $suplierRaw;
            foreach ($masterSuplier as $ms) {
                if (stripos($ms, $suplierRaw) !== false || stripos($suplierRaw, $ms) !== false) {
                    $bestMatch = $ms;
                    break;
                }
            }
            $suplier = $bestMatch;
        } else {
            // 2. Jika tidak pakai kurung, otomatis tebak dari nama produknya
            $p_lower = strtolower(trim($produk));
            if (isset($kamusProduk[$p_lower])) {
                $suplier = $kamusProduk[$p_lower];
            }
        }
        
        $groupedData[$suplier][] = [
            'produk' => $produk,
            'omzet' => $r['omzet'],
            'qty' => $r['qty']
        ];
    }
    
    ksort($groupedData);

    $index = 1;
    foreach ($groupedData as $suplName => $items) {
        $first = true;
        
        // Hitung total uang yang harus diterima suplier ini
        $totalTerima = 0;
        foreach ($items as $item) {
            $totalTerima += $item['omzet'];
        }
        
        foreach ($items as $item) {
            echo '<tr style="background-color: #e2efda;">';
            
            // Kolom No. & Nama Suplier diisi di baris pertama saja
            if ($first) {
                echo '<td class="center">' . $index . '</td>';
                echo '<td>' . htmlspecialchars($suplName) . '</td>';
            } else {
                echo '<td></td><td></td>';
            }
            
            // Kolom Nama Produk
            echo '<td>' . htmlspecialchars($item['produk']) . '</td>';
            
            // Kolom Total Terima & Tanda Tangan
            if ($first) {
                echo '<td class="num">' . $totalTerima . '</td>';
                
                // Format zig-zag tanda tangan
                if ($index % 2 == 1) {
                    echo '<td class="ttd-kiri">' . $index . '</td>';
                } else {
                    echo '<td class="ttd-tengah">' . $index . '</td>';
                }
            } else {
                echo '<td></td><td></td>';
            }
            
            echo '</tr>';
            $first = false;
        }
        $index++;
    }
    
    echo '</tbody></table>';
    echo '</body></html>';
    exit;
}

// Grafik harian
$st = $pdo->prepare("SELECT DATE(tanggal) AS d, SUM(total) AS omzet, SUM(untung) AS untung
    FROM transaksi WHERE status = 'selesai' AND DATE(tanggal) BETWEEN ? AND ? GROUP BY DATE(tanggal)");
$st->execute([$dari, $sampai]);
$harian = $st->fetchAll(PDO::FETCH_UNIQUE);
$labels = []; $dOmzet = []; $dUntung = [];
$n = 0;
for ($t = strtotime($dari); $t <= strtotime($sampai) && $n < 93; $t = strtotime('+1 day', $t), $n++) {
    $k = date('Y-m-d', $t);
    $labels[] = date('j', $t) . ' ' . BULAN[(int)date('n', $t)];
    $dOmzet[]  = (int)($harian[$k]['omzet'] ?? 0);
    $dUntung[] = (int)($harian[$k]['untung'] ?? 0);
}

$judul = 'Laporan Keuntungan';
$aktif = 'laporan';
$extraCss = ['assets/css/laporan.css'];
require __DIR__ . '/includes/header.php';
$presets = ['hari' => 'Hari ini', '7h' => '7 hari', 'bulan' => 'Bulan ini', 'lalu' => 'Bulan lalu', 'kustom' => 'Kustom'];
?>
<div class="d-flex flex-wrap gap-2 align-items-center mb-3 no-print">
  <div class="btn-group" role="group" aria-label="Periode">
    <?php foreach ($presets as $k => $v): ?><a class="btn <?= $p === $k ? 'btn-merah' : 'btn-garis' ?>" href="laporan.php?p=<?= $k ?>"><?= $v ?></a><?php endforeach; ?>
  </div>
  <?php if ($p === 'kustom'): ?>
  <form class="d-flex gap-2 align-items-center" method="get"><input type="hidden" name="p" value="kustom">
    <input type="date" name="dari" class="form-control" value="<?= e($dari) ?>"><span>s/d</span><input type="date" name="sampai" class="form-control" value="<?= e($sampai) ?>">
    <button class="btn btn-merah">Terapkan</button></form>
  <?php endif; ?>
  <div class="ms-auto d-flex gap-2">
    <a class="btn text-white fw-bold btn-export-ttd" href="laporan.php?<?= e(http_build_query(['p' => $p, 'dari' => $dari, 'sampai' => $sampai, 'export' => 'ttd'])) ?>"><?= ic('file-text') ?> Format Tanda Terima (Excel)</a>
    <button class="btn btn-garis" data-action="print"><?= ic('printer') ?> Cetak</button>
  </div>
</div>
<p class="text-muted mb-3"><?= e($label) ?>: <?= e(tgl_pendek($dari)) ?><?= $dari !== $sampai ? ' – ' . e(tgl_pendek($sampai)) : '' ?> · <?= (int)$ring['trx'] ?> transaksi</p>

<div class="row g-3 mb-3">
  <div class="col-12 col-md-6 col-xl-3"><?= kpi('Untung bersih', rp($ring['untung']), 'trending-up', 'hijau', 'Margin ' . $margin . '%', 'text-untung') ?></div>
  <div class="col-6 col-xl-3"><?= kpi('Omzet (penjualan)', rp($ring['omzet']), 'dollar-sign', 'merah') ?></div>
  <div class="col-6 col-xl-3"><?= kpi('Modal barang terjual', rp($ring['modal']), 'package', 'abu') ?></div>
  <div class="col-12 col-md-6 col-xl-3"><?= kpi('Rata-rata untung per transaksi', rp($ring['trx'] ? $ring['untung'] / $ring['trx'] : 0), 'bar-chart-2', 'biru') ?></div>
</div>

<div class="kartu mb-3">
  <div class="kartu-head">Omzet dan untung per hari</div>
  <div class="kartu-body"><div class="grafik-wrap"><canvas id="grafik" aria-label="Grafik omzet dan untung per hari" role="img"></canvas></div></div>
</div>

<div class="kartu"><div class="kartu-head">Untung per produk <span class="text-muted fw-normal small">(urut dari yang paling menguntungkan)</span></div>
<div class="tabel-scroll"><table class="tabel">
  <thead><tr><th>#</th><th>Produk</th><th class="num">Terjual</th><th class="num">Omzet</th><th class="num">Modal</th><th class="num">Untung</th><th class="num">Margin</th></tr></thead>
  <tbody>
  <?php foreach ($perProduk as $i => $r): ?>
    <tr><td class="text-muted"><?= $i + 1 ?></td><td class="fw-semibold"><?= e($r['nama']) ?></td><td class="num"><?= (int)$r['qty'] ?></td>
      <td class="num"><?= rp($r['omzet']) ?></td><td class="num"><?= rp($r['modal']) ?></td>
      <td class="num text-untung fw-semibold"><?= rp($r['untung']) ?></td><td class="num"><?= $r['omzet'] > 0 ? round($r['untung'] / $r['omzet'] * 100, 1) : 0 ?>%</td></tr>
  <?php endforeach; ?>
  </tbody>
  <?php if ($perProduk): ?><tfoot><tr class="fw-bold"><td></td><td>Total</td><td class="num"><?= array_sum(array_column($perProduk, 'qty')) ?></td><td class="num"><?= rp($ring['omzet']) ?></td><td class="num"><?= rp($ring['modal']) ?></td><td class="num text-untung"><?= rp($ring['untung']) ?></td><td class="num"><?= $margin ?>%</td></tr></tfoot><?php endif; ?>
</table>
<?php if (!$perProduk): ?><div class="kosong"><?= ic('bar-chart-2') ?>Belum ada penjualan pada periode ini.</div><?php endif; ?>
</div></div>
<?php
$extraLibs = ['assets/vendor/chart.umd.js', 'assets/js/laporan.js'];
$inlineJs = 'window.POS = ' . json_encode(['labels' => $labels, 'omzet' => $dOmzet, 'untung' => $dUntung]) . ';';
require __DIR__ . '/includes/footer.php';