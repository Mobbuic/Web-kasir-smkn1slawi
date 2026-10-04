<?php
class AppError extends RuntimeException {}

/**
 * Bangun baris transaksi dari input klien. Harga SELALU diambil dari database,
 * tidak pernah dari klien. Return [baris[], total, total_modal].
 */
function bangun_baris(PDO $pdo, array $items, bool $cekStok): array {
    $qtyById = [];
    foreach ($items as $it) {
        $id = (int)($it['id'] ?? 0);
        $q  = (int)($it['qty'] ?? 0);
        if ($id > 0 && $q > 0) $qtyById[$id] = ($qtyById[$id] ?? 0) + $q;
    }
    if (!$qtyById) throw new AppError('Keranjang masih kosong.');

    $ids = array_keys($qtyById);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $st  = $pdo->prepare("SELECT id, nama, harga_beli, harga_jual, stok FROM produk WHERE id IN ($ph) AND aktif = 1" . ($cekStok ? ' FOR UPDATE' : ''));
    $st->execute($ids);
    $prod = [];
    foreach ($st->fetchAll() as $r) $prod[(int)$r['id']] = $r;

    $baris = []; $total = 0; $modal = 0;
    foreach ($qtyById as $id => $q) {
        if (!isset($prod[$id])) throw new AppError('Ada produk yang sudah tidak tersedia. Muat ulang halaman kasir.');
        $p = $prod[$id];
        if ($cekStok && (int)$p['stok'] < $q) throw new AppError("Stok {$p['nama']} tidak cukup (sisa {$p['stok']}).");
        $sub = $q * (int)$p['harga_jual'];
        $mod = $q * (int)$p['harga_beli'];
        $baris[] = [
            'produk_id' => $id, 'nama' => $p['nama'], 'qty' => $q,
            'harga_beli' => (int)$p['harga_beli'], 'harga_jual' => (int)$p['harga_jual'],
            'subtotal' => $sub, 'untung' => $sub - $mod,
        ];
        $total += $sub; $modal += $mod;
    }
    return [$baris, $total, $modal];
}

function simpan_item(PDO $pdo, int $trxId, array $baris): void {
    $ins = $pdo->prepare('INSERT INTO transaksi_item (transaksi_id, produk_id, nama_produk, qty, harga_beli, harga_jual, subtotal, untung) VALUES (?,?,?,?,?,?,?,?)');
    foreach ($baris as $b) {
        $ins->execute([$trxId, $b['produk_id'], $b['nama'], $b['qty'], $b['harga_beli'], $b['harga_jual'], $b['subtotal'], $b['untung']]);
    }
}
