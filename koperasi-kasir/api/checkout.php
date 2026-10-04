<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/trx.php';

$in  = api_start();
$pdo = db();

try {
    $metode  = in_array($in['metode'] ?? '', ['tunai', 'qris', 'transfer'], true) ? $in['metode'] : 'tunai';
    $savedId = (int)($in['saved_id'] ?? 0);

    $pdo->beginTransaction();
    if ($savedId) {
        // Draft dilanjutkan -> dihapus, diganti transaksi final (stok baru berkurang saat dibayar)
        $pdo->prepare("DELETE FROM transaksi WHERE id = ? AND status = 'tersimpan'")->execute([$savedId]);
    }

    [$baris, $total, $modal] = bangun_baris($pdo, $in['items'] ?? [], true);
    $bayar = $metode === 'tunai' ? (int)($in['bayar'] ?? 0) : $total;
    if ($bayar < $total) throw new AppError('Uang diterima kurang dari total tagihan.');
    $kembali = $bayar - $total;
    $now = date('Y-m-d H:i:s');

    $pdo->prepare("INSERT INTO transaksi (no_struk, user_id, tanggal, total, total_modal, untung, metode, bayar, kembalian, status)
                   VALUES (?,?,?,?,?,?,?,?,?, 'selesai')")
        ->execute(['TMP' . bin2hex(random_bytes(6)), user()['id'], $now, $total, $modal, $total - $modal, $metode, $bayar, $kembali]);
    $id = (int)$pdo->lastInsertId();
    $noStruk = 'KS' . date('ymd') . '-' . str_pad((string)$id, 5, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE transaksi SET no_struk = ? WHERE id = ?')->execute([$noStruk, $id]);

    simpan_item($pdo, $id, $baris);
    $upd = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id = ?');
    $log = $pdo->prepare("INSERT INTO stok_log (produk_id, jumlah, tipe, keterangan, user_id, created_at) VALUES (?, ?, 'jual', ?, ?, ?)");
    foreach ($baris as $b) {
        $upd->execute([$b['qty'], $b['produk_id']]);
        $log->execute([$b['produk_id'], -$b['qty'], $noStruk, user()['id'], $now]);
    }
    $pdo->commit();

    $resp = ['ok' => true, 'id' => $id, 'no_struk' => $noStruk, 'total' => $total, 'bayar' => $bayar, 'kembalian' => $kembali, 'metode' => $metode, 'tanggal' => $now];
    if (is_admin()) $resp['untung'] = $total - $modal;
    api_out($resp);
} catch (AppError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_out(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('checkout: ' . $e->getMessage());
    api_out(['ok' => false, 'error' => 'Terjadi kesalahan server. Coba lagi.'], 500);
}
