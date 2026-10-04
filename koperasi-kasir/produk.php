<?php
require_once __DIR__ . '/includes/init.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';
    $now  = date('Y-m-d H:i:s');
    try {
        if ($aksi === 'simpan') {
            $id    = (int)($_POST['id'] ?? 0);
            $nama  = trim($_POST['nama'] ?? '');
            $kode  = trim($_POST['kode'] ?? '') ?: null;
            $kat   = (int)($_POST['kategori_id'] ?? 0) ?: null;
            $sup   = (int)($_POST['suplier_id'] ?? 0) ?: null; // Menangkap ID Suplier
            $beli  = max(0, (int)($_POST['harga_beli'] ?? 0));
            $jual  = max(0, (int)($_POST['harga_jual'] ?? 0));
            $stok  = (int)($_POST['stok'] ?? 0);
            $min   = max(0, (int)($_POST['stok_minimum'] ?? 0));
            $fav   = isset($_POST['favorit']) ? 1 : 0;
            $aktif = isset($_POST['aktif']) ? 1 : 0;
            
            if ($nama === '') throw new RuntimeException('Nama produk wajib diisi.');
            if ($jual <= 0) throw new RuntimeException('Harga jual harus lebih dari 0.');

            $pdo->beginTransaction();
            if ($id) {
                $lama = $pdo->prepare('SELECT stok FROM produk WHERE id = ? FOR UPDATE');
                $lama->execute([$id]);
                $stokLama = $lama->fetchColumn();
                if ($stokLama === false) throw new RuntimeException('Produk tidak ditemukan.');
                
                // Update tabel produk dengan suplier_id
                $pdo->prepare('UPDATE produk SET kode=?, nama=?, kategori_id=?, suplier_id=?, harga_beli=?, harga_jual=?, stok=?, stok_minimum=?, favorit=?, aktif=? WHERE id=?')
                    ->execute([$kode, $nama, $kat, $sup, $beli, $jual, $stok, $min, $fav, $aktif, $id]);
                
                if ((int)$stokLama !== $stok) {
                    $pdo->prepare("INSERT INTO stok_log (produk_id, jumlah, tipe, keterangan, user_id, created_at) VALUES (?, ?, 'koreksi', 'Koreksi manual', ?, ?)")
                        ->execute([$id, $stok - (int)$stokLama, user()['id'], $now]);
                }
            } else {
                // Insert tabel produk dengan suplier_id
                $pdo->prepare('INSERT INTO produk (kode, nama, kategori_id, suplier_id, harga_beli, harga_jual, stok, stok_minimum, favorit, aktif) VALUES (?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$kode, $nama, $kat, $sup, $beli, $jual, $stok, $min, $fav, $aktif]);
                
                if ($stok > 0) {
                    $pdo->prepare("INSERT INTO stok_log (produk_id, jumlah, tipe, keterangan, user_id, created_at) VALUES (?, ?, 'masuk', 'Stok awal', ?, ?)")
                        ->execute([(int)$pdo->lastInsertId(), $stok, user()['id'], $now]);
                }
            }
            $pdo->commit();
            flash('Produk disimpan.');
            
        } elseif ($aksi === 'hapus') {
            $pdo->prepare('DELETE FROM produk WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Produk dihapus. Riwayat transaksi lama tetap tersimpan.');
            
        } elseif ($aksi === 'stok') {
            $id = (int)$_POST['id']; $qty = (int)($_POST['qty'] ?? 0);
            if ($qty <= 0) throw new RuntimeException('Jumlah stok masuk harus lebih dari 0.');
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE produk SET stok = stok + ? WHERE id = ?')->execute([$qty, $id]);
            $pdo->prepare("INSERT INTO stok_log (produk_id, jumlah, tipe, keterangan, user_id, created_at) VALUES (?, ?, 'masuk', ?, ?, ?)")
                ->execute([$id, $qty, mb_substr(trim($_POST['ket'] ?? '') ?: 'Stok masuk', 0, 120), user()['id'], $now]);
            $pdo->commit();
            flash("Stok bertambah $qty.");
            
        } elseif ($aksi === 'kat_tambah') {
            $n = trim($_POST['nama'] ?? '');
            if ($n === '') throw new RuntimeException('Nama kategori wajib diisi.');
            $pdo->prepare('INSERT INTO kategori (nama) VALUES (?)')->execute([$n]);
            flash('Kategori ditambahkan.');
            
        } elseif ($aksi === 'kat_hapus') {
            $pdo->prepare('DELETE FROM kategori WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Kategori dihapus.');
            
        } elseif ($aksi === 'sup_tambah') {
            // Aksi Baru: Tambah Suplier
            $n = trim($_POST['nama_lengkap'] ?? '');
            if ($n === '') throw new RuntimeException('Nama lengkap suplier wajib diisi.');
            $pdo->prepare('INSERT INTO suplier (nama_lengkap) VALUES (?)')->execute([$n]);
            flash('Suplier/Penitip baru ditambahkan.');
            
        } elseif ($aksi === 'sup_hapus') {
            // Aksi Baru: Hapus Suplier
            $pdo->prepare('DELETE FROM suplier WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Suplier dihapus. Produk milik suplier ini menjadi "Umum / Mandiri".');
        }
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $dup = $ex instanceof PDOException && $ex->getCode() === '23000';
        flash($dup ? 'Kode produk/nama kategori sudah dipakai.' : ($ex instanceof RuntimeException ? $ex->getMessage() : 'Gagal menyimpan. Coba lagi.'), 'danger');
        if (!($ex instanceof RuntimeException) && !$dup) error_log('produk: ' . $ex->getMessage());
    }
    redirect('produk.php');
}

$judul = 'Kelola Produk & Suplier';
$aktif = 'produk';
$extraCss = ['assets/css/produk.css'];
require __DIR__ . '/includes/header.php';

// Memuat data kategori & suplier dari database
$kategori = $pdo->query('SELECT id, nama FROM kategori ORDER BY nama')->fetchAll();
$suplier = $pdo->query('SELECT id, nama_lengkap FROM suplier ORDER BY nama_lengkap')->fetchAll();

// Memuat data produk beserta relasinya
$rows = $pdo->query('SELECT p.*, k.nama AS kategori, s.nama_lengkap AS suplier_nama 
                     FROM produk p 
                     LEFT JOIN kategori k ON k.id = p.kategori_id 
                     LEFT JOIN suplier s ON s.id = p.suplier_id 
                     ORDER BY p.nama')->fetchAll();
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <div class="input-group cari-produk">
    <span class="input-group-text bg-white"><?= ic('search') ?></span>
    <input id="cari" class="form-control" placeholder="Cari produk / kode..." autocomplete="off">
  </div>
  
  <select id="fkat" class="form-select w-auto">
      <option value="">Semua kategori</option>
      <?php foreach ($kategori as $k): ?><option><?= e($k['nama']) ?></option><?php endforeach; ?>
  </select>

  <!-- Filter Baru: Suplier -->
  <select id="fsup" class="form-select w-auto">
      <option value="">Semua suplier</option>
      <?php foreach ($suplier as $s): ?><option><?= e($s['nama_lengkap']) ?></option><?php endforeach; ?>
  </select>

  <div class="ms-auto d-flex gap-2">
    <button class="btn btn-garis" data-bs-toggle="modal" data-bs-target="#modalKat"><?= ic('tag') ?> Kategori</button>
    <!-- Tombol Modal Suplier -->
    <button class="btn btn-garis text-primary border-primary" data-bs-toggle="modal" data-bs-target="#modalSup"><?= ic('users') ?> Suplier</button>
    <button class="btn btn-merah" id="btn-baru"><?= ic('plus') ?> Produk baru</button>
  </div>
</div>

<div class="kartu"><div class="tabel-scroll">
<table class="tabel" id="tabel">
  <thead>
      <tr>
          <th>Produk</th>
          <th>Kategori</th>
          <th>Suplier/Penitip</th>
          <th class="num">Modal</th>
          <th class="num">Harga jual</th>
          <th class="num">Stok</th>
          <th>Status</th>
          <th></th>
      </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $p):
    $u = $p['harga_jual'] - $p['harga_beli'];
    $tipis = $p['stok'] <= $p['stok_minimum']; ?>
    <!-- Atribut pencarian diperbarui -->
    <tr data-teks="<?= e(mb_strtolower($p['nama'] . ' ' . $p['kode'])) ?>" data-kat="<?= e($p['kategori'] ?? '') ?>" data-sup="<?= e($p['suplier_nama'] ?? '') ?>">
      <td>
          <div class="fw-semibold"><?= $p['favorit'] ? ic('star', 'ic-fill text-warning') . ' ' : '' ?><?= e($p['nama']) ?></div>
          <div class="small text-muted"><?= e($p['kode'] ?? '—') ?></div>
      </td>
      <td><?= e($p['kategori'] ?? '—') ?></td>
      <td>
          <?php if($p['suplier_nama']): ?>
              <span class="badge bg-light text-primary border border-primary"><?= e($p['suplier_nama']) ?></span>
          <?php else: ?>
              <span class="text-muted small">Mandiri</span>
          <?php endif; ?>
      </td>
      <td class="num"><?= rp($p['harga_beli']) ?></td>
      <td class="num"><?= rp($p['harga_jual']) ?></td>
      <td class="num <?= $tipis ? 'text-rugi fw-bold' : '' ?>"><?= (int)$p['stok'] ?></td>
      <td><?= $p['aktif'] ? '<span class="chip hijau">Aktif</span>' : '<span class="chip">Nonaktif</span>' ?></td>
      <td class="text-nowrap text-end">
        <button class="btn btn-sm btn-garis btn-stok" data-id="<?= (int)$p['id'] ?>" data-nama="<?= e($p['nama']) ?>" title="Tambah stok"><?= ic('plus-circle') ?></button>
        <button class="btn btn-sm btn-garis btn-edit" data-p='<?= e(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>' title="Ubah"><?= ic('edit-2') ?></button>
        <form method="post" class="d-inline" data-confirm="Hapus produk <?= e($p['nama']) ?>?">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <button class="btn btn-sm btn-garis text-danger" title="Hapus"><?= ic('trash-2') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php if (!$rows): ?><div class="kosong"><?= ic('package') ?>Belum ada produk. Tambahkan produk pertama kamu.</div><?php endif; ?>
</div></div>

<!-- Modal Produk Utama -->
<div class="modal fade" id="modalProduk" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" id="f-id" value="0">
      <div class="modal-header"><h2 class="modal-title fs-5 fw-bold" id="f-judul">Produk baru</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body row g-3">
        <div class="col-12"><label class="form-label fw-semibold" for="f-nama">Nama produk</label><input class="form-control" id="f-nama" name="nama" required maxlength="100"></div>
        
        <div class="col-6">
            <label class="form-label fw-semibold" for="f-sup">Suplier / Penitip</label>
            <select class="form-select border-primary" id="f-sup" name="suplier_id">
                <option value="">Umum / Mandiri</option>
                <?php foreach ($suplier as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['nama_lengkap']) ?></option><?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-6"><label class="form-label fw-semibold" for="f-kat">Kategori</label>
          <select class="form-select" id="f-kat" name="kategori_id"><option value="">—</option><?php foreach ($kategori as $k): ?><option value="<?= (int)$k['id'] ?>"><?= e($k['nama']) ?></option><?php endforeach; ?></select></div>
        
        <div class="col-6"><label class="form-label fw-semibold" for="f-kode">Kode Barcode (opsional)</label><input class="form-control" id="f-kode" name="kode" maxlength="30"></div>
        <div class="col-6"><label class="form-label fw-semibold" for="f-stok">Sisa Stok Fisik</label><input class="form-control" id="f-stok" name="stok" type="number" value="0" required></div>
        
        <div class="col-6"><label class="form-label fw-semibold text-danger" for="f-beli">Harga Modal (Utk Suplier)</label><input class="form-control" id="f-beli" name="harga_beli" type="number" min="0" step="100" value="0" required></div>
        <div class="col-6"><label class="form-label fw-semibold text-success" for="f-jual">Harga Jual (Ke Siswa)</label><input class="form-control" id="f-jual" name="harga_jual" type="number" min="1" step="100" required></div>
        
        <div class="col-12"><div id="f-untung" class="kembalian-box"><span>Laba / Kas KKWU</span><span>Rp0</span></div></div>
        
        <div class="col-12"><label class="form-label fw-semibold" for="f-min">Peringatan stok menipis pada angka:</label><input class="form-control" id="f-min" name="stok_minimum" type="number" min="0" value="5"></div>
        
        <div class="col-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-fav" name="favorit"><label class="form-check-label" for="f-fav">Tampil di Layar Kasir</label></div></div>
        <div class="col-6"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="f-aktif" name="aktif" checked><label class="form-check-label" for="f-aktif">Status Dijual</label></div></div>
      </div>
      <div class="modal-footer"><button class="btn btn-merah btn-lg-touch w-100">Simpan Produk</button></div>
    </form>
  </div>
</div>

<!-- Modal Kategori -->
<div class="modal fade" id="modalKat" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5 fw-bold">Kategori</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body">
        <form method="post" class="input-group mb-3">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="kat_tambah">
          <input class="form-control" name="nama" placeholder="Nama kategori baru" maxlength="60" required>
          <button class="btn btn-merah">Tambah</button>
        </form>
        <?php foreach ($kategori as $k): ?>
          <form method="post" class="d-flex align-items-center border-bottom py-2" data-confirm="Hapus kategori ini? Produknya jadi tanpa kategori.">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="kat_hapus"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
            <span class="flex-grow-1"><?= e($k['nama']) ?></span><button class="btn btn-sm btn-garis text-danger" aria-label="Hapus"><?= ic('trash-2') ?></button>
          </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal Suplier -->
<div class="modal fade" id="modalSup" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5 fw-bold text-primary"><?= ic('users') ?> Kelola Suplier</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body">
        <form method="post" class="input-group mb-4">
          <?= csrf_field() ?><input type="hidden" name="aksi" value="sup_tambah">
          <input class="form-control" name="nama_lengkap" placeholder="Nama Lengkap (Siswa/Guru)" maxlength="100" required>
          <button class="btn btn-primary">Daftarkan</button>
        </form>
        <div class="text-muted small fw-bold text-uppercase mb-2">Daftar Suplier Aktif:</div>
        <?php foreach ($suplier as $s): ?>
          <form method="post" class="d-flex align-items-center border-bottom py-2" data-confirm="Yakin hapus suplier <?= e($s['nama_lengkap']) ?>?">
            <?= csrf_field() ?><input type="hidden" name="aksi" value="sup_hapus"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <span class="flex-grow-1 fw-semibold"><?= e($s['nama_lengkap']) ?></span>
            <button class="btn btn-sm text-danger" aria-label="Hapus"><?= ic('trash-2') ?></button>
          </form>
        <?php endforeach; ?>
        <?php if (!$suplier): ?><div class="text-center py-3 text-muted">Belum ada suplier.</div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal Stok -->
<div class="modal fade" id="modalStok" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" class="modal-content">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="stok"><input type="hidden" name="id" id="s-id">
      <div class="modal-header"><h2 class="modal-title fs-5 fw-bold">Tambah stok: <span id="s-nama"></span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
      <div class="modal-body">
        <label class="form-label fw-semibold" for="s-qty">Jumlah Masuk</label><input class="form-control mb-3" id="s-qty" name="qty" type="number" min="1" required>
        <label class="form-label fw-semibold" for="s-ket">Keterangan Tambahan (Opsional)</label><input class="form-control" id="s-ket" name="ket" maxlength="120" placeholder="Contoh: Titipan jam ke-2">
      </div>
      <div class="modal-footer"><button class="btn btn-merah btn-lg-touch w-100">Simpan Stok Masuk</button></div>
    </form>
  </div>
</div>

<?php
$extraLibs = ['assets/js/produk.js'];
require __DIR__ . '/includes/footer.php';