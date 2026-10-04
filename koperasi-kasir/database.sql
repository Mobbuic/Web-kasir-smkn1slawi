-- Koperasi Sekolah - Aplikasi Kasir
-- Import lewat phpMyAdmin (XAMPP) atau: mysql -uroot < database.sql

CREATE DATABASE IF NOT EXISTS koperasi_kasir CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE koperasi_kasir;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS stok_log, transaksi_item, transaksi, produk, kategori, users, pengaturan;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(80) NOT NULL,
  username VARCHAR(40) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE kategori (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE produk (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(30) NULL UNIQUE,
  nama VARCHAR(100) NOT NULL,
  kategori_id INT UNSIGNED NULL,
  harga_beli INT UNSIGNED NOT NULL DEFAULT 0,
  harga_jual INT UNSIGNED NOT NULL DEFAULT 0,
  stok INT NOT NULL DEFAULT 0,
  stok_minimum INT NOT NULL DEFAULT 5,
  favorit TINYINT(1) NOT NULL DEFAULT 0,
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (nama),
  CONSTRAINT fk_produk_kategori FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- status: selesai = transaksi jadi | tersimpan = draft (belum dibayar) | batal = dibatalkan
CREATE TABLE transaksi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  no_struk VARCHAR(30) NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  tanggal DATETIME NOT NULL,
  total INT UNSIGNED NOT NULL DEFAULT 0,
  total_modal INT UNSIGNED NOT NULL DEFAULT 0,
  untung INT NOT NULL DEFAULT 0,
  metode ENUM('tunai','qris','transfer') NOT NULL DEFAULT 'tunai',
  bayar INT UNSIGNED NOT NULL DEFAULT 0,
  kembalian INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('selesai','tersimpan','batal') NOT NULL DEFAULT 'selesai',
  catatan VARCHAR(120) NULL,
  INDEX idx_tgl_status (tanggal, status),
  CONSTRAINT fk_trx_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- harga_beli & harga_jual disimpan saat transaksi (snapshot) supaya laporan untung tetap akurat
CREATE TABLE transaksi_item (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT UNSIGNED NOT NULL,
  produk_id INT UNSIGNED NULL,
  nama_produk VARCHAR(100) NOT NULL,
  qty INT UNSIGNED NOT NULL,
  harga_beli INT UNSIGNED NOT NULL,
  harga_jual INT UNSIGNED NOT NULL,
  subtotal INT UNSIGNED NOT NULL,
  untung INT NOT NULL,
  INDEX (transaksi_id),
  CONSTRAINT fk_item_trx FOREIGN KEY (transaksi_id) REFERENCES transaksi(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_produk FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE stok_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  produk_id INT UNSIGNED NOT NULL,
  jumlah INT NOT NULL,
  tipe ENUM('masuk','jual','batal','koreksi') NOT NULL,
  keterangan VARCHAR(120) NULL,
  user_id INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  INDEX (produk_id),
  CONSTRAINT fk_log_produk FOREIGN KEY (produk_id) REFERENCES produk(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE pengaturan (
  kunci VARCHAR(40) PRIMARY KEY,
  nilai VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

-- ---------- Seed ----------
-- Login awal: admin / admin123  |  kasir1 / kasir123  (GANTI setelah login pertama)
INSERT INTO users (nama, username, password, role) VALUES
('Pembina Koperasi', 'admin', '$2y$10$63O2WVOAXheIvCkMy9TQgO/oBHpCgOrhmJuFB89hI25F.5V5empe.', 'admin'),
('Kasir 1', 'kasir1', '$2y$10$SR.WKdRNCCKngdUbvG/WR.dhpoQhmjSQZNU2Vjc7EUNM6FqEfyk8m', 'kasir');

INSERT INTO pengaturan (kunci, nilai) VALUES
('nama_koperasi', 'Koperasi Sekolah'),
('alamat', 'Jl. Pendidikan No. 1'),
('footer_struk', 'Terima kasih sudah berbelanja!');

INSERT INTO kategori (nama) VALUES ('Makanan Ringan'), ('Minuman'), ('Alat Tulis'), ('Seragam & Atribut');

INSERT INTO produk (kode, nama, kategori_id, harga_beli, harga_jual, stok, stok_minimum, favorit) VALUES
('MK001', 'Chitato', 1, 8000, 10000, 40, 10, 1),
('MK002', 'Roti Sobek', 1, 5500, 7000, 30, 8, 1),
('MK003', 'Biskuit Roma', 1, 2500, 3000, 60, 15, 0),
('MK004', 'Wafer Tango', 1, 1500, 2000, 80, 20, 0),
('MK005', 'Risol Mayo', 1, 2500, 4000, 25, 6, 1),
('MN001', 'Air Mineral 600ml', 2, 2500, 4000, 100, 24, 1),
('MN002', 'Teh Botol', 2, 4000, 5000, 48, 12, 1),
('MN003', 'Susu Kotak', 2, 4500, 6000, 36, 10, 0),
('MN004', 'Es Batu Kopi', 2, 5000, 8000, 20, 5, 0),
('AT001', 'Pulpen Standard', 3, 1500, 2500, 70, 20, 1),
('AT002', 'Buku Tulis 38 Lembar', 3, 3000, 4500, 50, 15, 1),
('AT003', 'Pensil 2B', 3, 1200, 2000, 45, 15, 0),
('AT004', 'Penghapus', 3, 1000, 2000, 40, 10, 0),
('SG001', 'Dasi Sekolah', 4, 12000, 17000, 15, 5, 0),
('SG002', 'Topi Upacara', 4, 15000, 22000, 12, 4, 0);
