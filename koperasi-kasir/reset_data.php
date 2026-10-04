<?php
require_once __DIR__ . '/includes/init.php';
require_admin(); // Hanya admin yang boleh mereset data

$pdo = db();

try {
    // Matikan pengecekan foreign key sebentar agar bisa dihapus
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Hapus data transaksi dan itemnya
    $pdo->exec("DELETE FROM transaksi_item");
    $pdo->exec("DELETE FROM transaksi");

    // Nyalakan kembali pengecekan foreign key
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Berhasil dan arahkan kembali ke beranda
    echo "<script>alert('Berhasil! Semua data transaksi dummy telah dibersihkan. Sistem siap digunakan.'); window.location.href='index.php';</script>";

} catch (Exception $e) {
    echo "<script>alert('Gagal mereset data: " . addslashes($e->getMessage()) . "'); window.location.href='index.php';</script>";
}