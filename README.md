[README.md](https://github.com/user-attachments/files/33080120/README.md)
# Kasir Koperasi Sekolah

## Login awal (GANTI di Pengaturan)
| Peran | Username |     Password     |
|---|---|----------|------------------|
| Admin | admin    | admin123 |
| Kasir | kasir1   | kasir123 |

## Struktur
```
database.sql        skema + data awal (produk contoh)
migrations/         perubahan skema setelah database.sql (idempotent, import berurutan)
seed_demo.sql       data demo transaksi (opsional)
config/db.php       koneksi
includes/           init (auth, csrf, helper), header/footer, trx (logika transaksi)
api/checkout.php    bayar (harga & stok divalidasi server)
api/simpan.php      simpan transaksi (draft)
kasir.php           layar transaksi
reset_data.php      hapus semua transaksi uji (admin, wajib ketik RESET)
transaksi_tersimpan.php · riwayat.php · struk.php
index.php (Beranda) · laporan.php (untung) · produk.php · pengaturan.php
```
## Cara untung dihitung
`harga_beli` dan `harga_jual` disalin ke `transaksi_item` saat transaksi terjadi.
Untung = (jual − beli) × qty. Kalau harga modal berubah nanti, laporan lama tetap benar.

## Aturan
- Stok berkurang saat **dibayar** (bukan saat disimpan). Transaksi dibatalkan → stok kembali.
- Kasir tidak melihat harga modal maupun untung; hanya admin.
