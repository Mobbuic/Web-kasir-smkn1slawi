<?php
// 1. Memanggil init.php yang sudah memuat session_start() & koneksi DB
require_once __DIR__ . '/includes/init.php';

// 2. Jika sudah login, langsung arahkan ke Dashboard
if (user()) {
    redirect('index.php');
}

$error = '';

// 3. Proses Login dengan standar keamanan sistem
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi Token CSRF untuk keamanan form
    csrf_verify();
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $st = db()->prepare('SELECT * FROM users WHERE username = ? AND aktif = 1');
    $st->execute([$username]);
    $row = $st->fetch();
    
    if ($row && password_verify($password, $row['password'])) {
        // Regenerasi ID Session untuk cegah pembajakan akun
        session_regenerate_id(true);
        
        // Daftarkan data user ke dalam array $_SESSION['user'] sesuai standar init.php
        $_SESSION['user'] = [
            'id'       => (int)$row['id'], 
            'nama'     => $row['nama'], 
            'username' => $row['username'], 
            'role'     => $row['role']
        ];
        
        // Arahkan admin ke index, sedangkan kasir langsung ke layar transaksi
        redirect($row['role'] === 'admin' ? 'index.php' : 'kasir.php');
    }
    
    $error = 'Username atau password salah!';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Kasir KKWU SMKN 1 Slawi</title>
    <!-- Memanggil CSS Bootstrap bawaan -->
    <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <div class="login-wrapper">
        <div class="row g-0">
            
            <!-- SISI KIRI -->
            <div class="col-lg-5 col-md-6 d-none d-md-flex panel-kiri">
                <img src="assets/img/logo.png" alt="Logo SMKN 1 Slawi" class="logo-besar">
                <h2 class="judul-kiri">KKWU SMEA</h2>
                <p class="sub-kiri">Sistem Kasir Kewirausahaan<br>SMK Negeri 1 Slawi</p>
            </div>

            <!-- SISI KANAN -->
            <div class="col-lg-7 col-md-6 panel-kanan">
                
                <div class="d-md-none text-center mb-4">
                    <img src="assets/img/logo.png" alt="Logo" class="logo-mobile">
                </div>

                <h3 class="teks-sambut">Selamat Datang Kembali</h3>
                <p class="teks-panduan">Masuk untuk melanjutkan ke dashboard kasir Anda.</p>

                <!-- Notifikasi Error -->
                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 d-flex align-items-center" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" autocomplete="off">
                    
                    <!-- Token CSRF Wajib Ada! -->
                    <?= csrf_field() ?>
                    
                    <div class="mb-4">
                        <label class="form-label" for="username">Username</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                            </span>
                            <input type="text" class="form-control border-start-0" id="username" name="username" placeholder="Masukkan username" required autofocus autocapitalize="none">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                            </span>
                            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Masukkan password" required>
                            
                            <span class="input-group-text border-start-0 btn-lihat" id="lihat" title="Lihat Password">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="eyeIcon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-login w-100">Login ke Aplikasi</button>
                </form>
                
            </div>
        </div>
    </div>

    <script src="assets/js/login.js"></script>
</body>
</html>