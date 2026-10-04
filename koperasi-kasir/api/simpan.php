<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/trx.php';

$in  = api_start();
$pdo = db();

try {
    $savedId = (int)($in['saved_id'] ?? 0);
    $catatan = mb_substr(trim((string)($in['catatan'] ?? '')), 0, 120);
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    [$baris, $total, $modal] = bangun_baris($pdo, $in['items'] ?? [], false);

    $ada = false;
    if ($savedId) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM transaksi WHERE id = ? AND status = 'tersimpan'");
        $c->execute([$savedId]);
        $ada = (int)$c->fetchColumn() > 0;
    }
    if ($ada) {
        $pdo->prepare('UPDATE transaksi SET tanggal=?, total=?, total_modal=?, untung=?, catatan=? WHERE id=?')
            ->execute([$now, $total, $modal, $total - $modal, $catatan ?: null, $savedId]);
        $pdo->prepare('DELETE FROM transaksi_item WHERE transaksi_id = ?')->execute([$savedId]);
        $id = $savedId;
    } else {
        $pdo->prepare("INSERT INTO transaksi (no_struk, user_id, tanggal, total, total_modal, untung, status, catatan) VALUES (NULL,?,?,?,?,?, 'tersimpan', ?)")
            ->execute([user()['id'], $now, $total, $modal, $total - $modal, $catatan ?: null]);
        $id = (int)$pdo->lastInsertId();
    }
    simpan_item($pdo, $id, $baris);
    $pdo->commit();
    api_out(['ok' => true, 'id' => $id]);
} catch (AppError $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    api_out(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('simpan: ' . $e->getMessage());
    api_out(['ok' => false, 'error' => 'Terjadi kesalahan server. Coba lagi.'], 500);
}
