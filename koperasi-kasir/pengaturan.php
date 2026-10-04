<?php
require_once __DIR__ . '/includes/init.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $aksi = $_POST['aksi'] ?? '';
    try {
        if ($aksi === 'koperasi') {
            $up = $pdo->prepare('INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
            foreach (['nama_koperasi' => 100, 'alamat' => 200, 'footer_struk' => 200] as $k => $max) {
                $up->execute([$k, mb_substr(trim($_POST[$k] ?? ''), 0, $max)]);
            }
            flash('Pengaturan disimpan.');
        } elseif ($aksi === 'user_baru') {
            $nama = trim($_POST['nama'] ?? ''); $un = strtolower(trim($_POST['username'] ?? '')); $pw = $_POST['password'] ?? '';
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'kasir';
            if ($nama === '' || !preg_match('/^[a-z0-9_.]{3,40}$/', $un)) throw new RuntimeException('Nama wajib diisi. Username 3–40 karakter (huruf kecil, angka, _ atau .).');
            if (strlen($pw) < 6) throw new RuntimeException('Password minimal 6 karakter.');
            $pdo->prepare('INSERT INTO users (nama, username, password, role) VALUES (?,?,?,?)')->execute([$nama, $un, password_hash($pw, PASSWORD_DEFAULT), $role]);
            flash('Pengguna ditambahkan.');
        } elseif ($aksi === 'user_pw') {
            $pw = $_POST['password'] ?? '';
            if (strlen($pw) < 6) throw new RuntimeException('Password minimal 6 karakter.');
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([password_hash($pw, PASSWORD_DEFAULT), (int)$_POST['id']]);
            flash('Password diganti.');
        } elseif ($aksi === 'user_toggle') {
            $id = (int)$_POST['id'];
            if ($id === user()['id']) throw new RuntimeException('Kamu tidak bisa menonaktifkan akun sendiri.');
            $pdo->prepare('UPDATE users SET aktif = 1 - aktif WHERE id = ?')->execute([$id]);
            flash('Status pengguna diubah.');
        }
    } catch (Throwable $ex) {
        $dup = $ex instanceof PDOException && $ex->getCode() === '23000';
        flash($dup ? 'Username sudah dipakai.' : ($ex instanceof RuntimeException ? $ex->getMessage() : 'Gagal menyimpan. Coba lagi.'), 'danger');
    }
    redirect('pengaturan.php');
}

$judul = 'Pengaturan';
$aktif = 'pengaturan';
require __DIR__ . '/includes/header.php';
$users = $pdo->query('SELECT id, nama, username, role, aktif FROM users ORDER BY role, nama')->fetchAll();
?>
<div class="row g-3">
  <div class="col-12 col-xl-5">
    <form method="post" class="kartu">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="koperasi">
      <div class="kartu-head">Info koperasi (tampil di struk)</div>
      <div class="kartu-body">
        <label class="form-label fw-semibold" for="nk">Nama koperasi</label><input class="form-control mb-3" id="nk" name="nama_koperasi" value="<?= e(setting('nama_koperasi')) ?>" required>
        <label class="form-label fw-semibold" for="al">Alamat</label><input class="form-control mb-3" id="al" name="alamat" value="<?= e(setting('alamat')) ?>">
        <label class="form-label fw-semibold" for="ft">Pesan di bawah struk</label><input class="form-control mb-3" id="ft" name="footer_struk" value="<?= e(setting('footer_struk')) ?>">
        <button class="btn btn-merah">Simpan</button>
      </div>
    </form>
  </div>
  <div class="col-12 col-xl-7">
    <div class="kartu">
      <div class="kartu-head">Pengguna <button class="btn btn-sm btn-merah ms-auto" data-bs-toggle="modal" data-bs-target="#modalUser"><?= ic('plus') ?> Tambah</button></div>
      <div class="tabel-scroll"><table class="tabel">
        <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td class="fw-semibold"><?= e($u['nama']) ?></td><td><?= e($u['username']) ?></td>
            <td><?= $u['role'] === 'admin' ? 'Admin / Pembina' : 'Kasir' ?></td>
            <td><?= $u['aktif'] ? '<span class="chip hijau">Aktif</span>' : '<span class="chip">Nonaktif</span>' ?></td>
            <td class="text-nowrap text-end">
              <button class="btn btn-sm btn-garis btn-pw" data-id="<?= (int)$u['id'] ?>" data-nama="<?= e($u['nama']) ?>" title="Ganti password"><?= ic('key') ?></button>
              <?php if ((int)$u['id'] !== user()['id']): ?>
              <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="user_toggle"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-sm btn-garis" title="<?= $u['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>"><?= ic($u['aktif'] ? 'user-x' : 'user-check') ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalUser" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="user_baru">
  <div class="modal-header"><h2 class="modal-title fs-5 fw-bold">Pengguna baru</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
  <div class="modal-body row g-3">
    <div class="col-12"><label class="form-label fw-semibold" for="un-nama">Nama</label><input class="form-control" id="un-nama" name="nama" required maxlength="80"></div>
    <div class="col-6"><label class="form-label fw-semibold" for="un-user">Username</label><input class="form-control" id="un-user" name="username" required maxlength="40" autocapitalize="none"></div>
    <div class="col-6"><label class="form-label fw-semibold" for="un-role">Peran</label><select class="form-select" id="un-role" name="role"><option value="kasir">Kasir</option><option value="admin">Admin / Pembina</option></select></div>
    <div class="col-12"><label class="form-label fw-semibold" for="un-pw">Password</label><input class="form-control" id="un-pw" name="password" type="password" minlength="6" required autocomplete="new-password"></div>
  </div>
  <div class="modal-footer"><button class="btn btn-merah btn-lg-touch w-100">Tambah pengguna</button></div>
</form></div></div>

<div class="modal fade" id="modalPw" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="user_pw"><input type="hidden" name="id" id="pw-id">
  <div class="modal-header"><h2 class="modal-title fs-5 fw-bold">Ganti password: <span id="pw-nama"></span></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button></div>
  <div class="modal-body"><label class="form-label fw-semibold" for="pw-baru">Password baru</label><input class="form-control" id="pw-baru" name="password" type="password" minlength="6" required autocomplete="new-password"></div>
  <div class="modal-footer"><button class="btn btn-merah btn-lg-touch w-100">Simpan password</button></div>
</form></div></div>
<?php
$extraLibs = ['assets/js/pengaturan.js'];
require __DIR__ . '/includes/footer.php';
