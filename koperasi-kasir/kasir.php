<?php
$judul = 'Transaksi';
$aktif = 'kasir';
$pos   = true;
$extraCss = ['assets/css/kasir.css'];
require __DIR__ . '/includes/header.php';

$pdo = db();
// Ambil data produk aktif dari database
$produk = $pdo->query("SELECT p.id, p.nama, p.harga_jual, p.stok, p.stok_minimum, p.favorit, p.kategori_id, k.nama AS kategori
                       FROM produk p LEFT JOIN kategori k ON k.id = p.kategori_id
                       WHERE p.aktif = 1 ORDER BY p.nama")->fetchAll();

$kategori = $pdo->query('SELECT id, nama FROM kategori ORDER BY nama')->fetchAll();

$savedId = 0; $cart = []; $catatan = '';
if (isset($_GET['lanjut'])) {
    $st = $pdo->prepare("SELECT id, catatan FROM transaksi WHERE id = ? AND status = 'tersimpan'");
    $st->execute([(int)$_GET['lanjut']]);
    if ($row = $st->fetch()) {
        $savedId = (int)$row['id']; $catatan = (string)$row['catatan'];
        $it = $pdo->prepare('SELECT produk_id AS id, qty FROM transaksi_item WHERE transaksi_id = ? AND produk_id IS NOT NULL');
        $it->execute([$savedId]);
        $cart = $it->fetchAll();
    }
}
$cfg = [
    'produk' => $produk, 'cart' => $cart, 'savedId' => $savedId, 'catatan' => $catatan,
    'csrf' => csrf(), 'isAdmin' => is_admin(), 'koperasi' => setting('nama_koperasi', 'Koperasi Sekolah'),
];
?>
<div class="pos-grid">
  <section class="pos-left" aria-label="Daftar produk">
    <div class="pos-tools">
      <div class="input-group">
        <span class="input-group-text bg-white"><?= ic('search') ?></span>
        <input id="cari" class="form-control" placeholder="Cari produk" autocomplete="off">
      </div>
      <div class="d-flex gap-2">
        <div class="pos-tabs flex-grow-1">
          <button type="button" class="pos-tab active" data-tab="semua">Produk</button>
          <button type="button" class="pos-tab" data-tab="favorit">Favorit</button>
        </div>
        <select id="kat" class="form-select w-auto" aria-label="Kategori">
          <option value="">Semua kategori</option>
          <?php foreach ($kategori as $k): ?><option value="<?= (int)$k['id'] ?>"><?= e($k['nama']) ?></option><?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="produk-list" id="produk-list"></div>
  </section>

  <section class="pos-right" aria-label="Keranjang">
    <div class="cart-head">
      <?= ic('shopping-cart') ?><span>Keranjang <span class="text-muted fw-normal" id="cart-info"></span></span>
      <button type="button" id="btn-hapus" class="btn btn-sm btn-garis ms-auto"><?= ic('trash-2') ?>Hapus</button>
    </div>
    <div class="cart-body" id="cart-body"></div>
    <div class="cart-foot">
      <div class="cart-total"><span class="text-muted">Total</span><span class="angka" id="cart-total">Rp0</span></div>
      <div class="d-flex gap-2">
        <button type="button" id="btn-simpan" class="btn btn-garis btn-lg-touch flex-fill"><?= ic('bookmark') ?>Simpan</button>
        <button type="button" id="btn-bayar" class="btn btn-merah btn-lg-touch flex-fill"><?= ic('credit-card') ?>Bayar</button>
      </div>
    </div>
  </section>
</div>

<!-- Modal bayar -->
<div class="modal fade" id="modalBayar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5 fw-bold">Pembayaran</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-baseline mb-3">
          <span class="text-muted">Total tagihan</span><span class="fs-3 fw-bold" id="bayar-total">Rp0</span>
        </div>
        <div class="d-flex gap-2 mb-3" id="metode-wrap">
          <button type="button" class="metode-btn active" data-metode="tunai"><?= ic('dollar-sign') ?>Tunai</button>
          <button type="button" class="metode-btn" data-metode="qris"><?= ic('smartphone') ?>QRIS</button>
          <button type="button" class="metode-btn" data-metode="transfer"><?= ic('credit-card') ?>Transfer</button>
        </div>
        <div id="tunai-wrap">
          <label class="form-label fw-semibold" for="diterima">Uang diterima</label>
          <input id="diterima" class="form-control input-uang mb-2" inputmode="numeric" autocomplete="off" placeholder="0">
          <div class="nominal-cepat mb-3" id="nominal-cepat"></div>
          <div class="kembalian-box"><span id="kembalian-label">Kembalian</span><span id="kembalian-nilai">Rp0</span></div>
        </div>
        <div id="nontunai-wrap" class="text-muted d-none">Pastikan pembayaran sudah masuk sebelum menekan Proses.</div>
        <div class="alert alert-danger py-2 mt-3 mb-0 d-none" id="bayar-error"></div>
      </div>
      <div class="modal-footer">
        <button type="button" id="btn-proses" class="btn btn-hijau btn-lg-touch w-100"><?= ic('check') ?>Proses Pembayaran</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal simpan -->
<div class="modal fade" id="modalSimpan" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5 fw-bold">Simpan transaksi</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body">
        <label class="form-label fw-semibold" for="catatan">Catatan (opsional)</label>
        <input id="catatan" class="form-control" maxlength="120" placeholder="Contoh: Pesanan kelas 9B">
        <div class="alert alert-danger py-2 mt-3 mb-0 d-none" id="simpan-error"></div>
      </div>
      <div class="modal-footer"><button type="button" id="btn-simpan-ok" class="btn btn-merah btn-lg-touch w-100"><?= ic('bookmark') ?>Simpan</button></div>
    </div>
  </div>
</div>

<!-- Modal Sukses Transaksi -->
<div class="sukses" id="sukses" role="dialog" aria-modal="true" aria-labelledby="sukses-judul">
  <div class="sukses-inner">
    <div class="sukses-left">
        <div class="ikon-sukses-modern">
          <?= ic('check') ?>
        </div>
        <h2 class="h3 fw-bold mb-1 sukses-judul" id="sukses-judul">Transaksi Berhasil!</h2>
        <div class="text-muted small mb-3" id="sukses-waktu"></div>
        <div class="sukses-untung d-none" id="s-untung-wrap"><?= ic('trending-up') ?> Untung transaksi: <span id="s-untung"></span></div>
    </div>
    <div class="sukses-right">
        <div class="struk-card">
            <div class="struk-item">
                <span class="struk-label">Metode Bayar</span>
                <span class="struk-value" id="s-metode"></span>
            </div>
            <div class="struk-item">
                <span class="struk-label">Total Tagihan</span>
                <span class="struk-value" id="s-total"></span>
            </div>
            <div class="struk-item">
                <span class="struk-label">Uang Diterima</span>
                <span class="struk-value" id="s-bayar"></span>
            </div>
            <div class="struk-item">
                <span class="struk-label">Kembalian</span>
                <span class="struk-value merah" id="s-kembali"></span>
            </div>
        </div>
        <div class="btn-grup-aksi mb-3">
            <button type="button" class="btn-aksi-mini" id="s-cetak"><?= ic('printer') ?><span>Cetak</span></button>
            <a class="btn-aksi-mini" id="s-kirim" target="_blank" rel="noopener"><?= ic('send') ?><span>WhatsApp</span></a>
            <a class="btn-aksi-mini" id="s-lihat" target="_blank" rel="noopener"><?= ic('file-text') ?><span>Struk</span></a>
        </div>
        <button type="button" class="btn-transaksi-baru" id="s-baru"><?= ic('plus') ?> Transaksi Baru</button>
    </div>
  </div>
</div>

<div class="pos-toast" id="toast" role="status" aria-live="polite"></div>
<?php
$inlineJs = 'window.POS = ' . json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';';
$extraLibs = ['assets/js/kasir.js'];
require __DIR__ . '/includes/footer.php';