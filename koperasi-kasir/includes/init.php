<?php
date_default_timezone_set('Asia/Jakarta');
session_start();
ob_start();
require_once __DIR__ . '/../config/db.php';

/* ---------- Halaman error yang bisa dibaca (bantu diagnosa setup XAMPP) ---------- */
set_exception_handler(function (Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    error_log($e->getMessage());
    http_response_code(500);
    $m = $e->getMessage();
    $hint = 'Cek log error Apache (xampp/apache/logs/error.log).';
    if (stripos($m, 'Unknown database') !== false) $hint = 'Database belum dibuat. Import database.sql lewat phpMyAdmin (tab Import, tanpa memilih database dulu).';
    elseif (stripos($m, "doesn't exist") !== false) $hint = 'Tabel belum lengkap, kemungkinan import database.sql berhenti di tengah. Import ulang database.sql dan baca pesan error dari phpMyAdmin.';
    elseif (stripos($m, 'Access denied') !== false) $hint = 'Username/password MySQL salah. Sesuaikan DB_USER dan DB_PASS di config/db.php.';
    elseif (stripos($m, '2002') !== false || stripos($m, 'refused') !== false) $hint = 'MySQL belum jalan atau port berbeda. Start MySQL di XAMPP; kalau port-nya bukan 3306, ubah DB_HOST menjadi "127.0.0.1:PORT" di config/db.php.';
    elseif (stripos($m, 'could not find driver') !== false) $hint = 'Aktifkan extension pdo_mysql di php.ini (hapus tanda ; di depan extension=pdo_mysql), lalu restart Apache.';
    elseif (stripos($m, 'mb_') !== false) $hint = 'Aktifkan extension mbstring di php.ini (extension=mbstring), lalu restart Apache.';
    if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Kesalahan server: ' . $m]);
        return;
    }
    echo '<!doctype html><meta charset="utf-8"><title>Kesalahan</title><body style="font-family:system-ui,sans-serif;max-width:680px;margin:3rem auto;padding:0 1rem">'
       . '<h1 style="color:#E8412B;font-size:1.4rem">Aplikasi belum bisa jalan</h1>'
       . '<p><strong>' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</strong></p>'
       . '<pre style="background:#f4f2f9;padding:1rem;white-space:pre-wrap;border-radius:6px;font-size:.85rem">' . htmlspecialchars($m, ENT_QUOTES, 'UTF-8') . '</pre></body>';
});

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
const BULAN = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function rp($n): string { return 'Rp' . number_format((int)$n, 0, ',', '.'); }
/* ---------- Ikon Feather (sprite lokal: assets/icons/feather-sprite.svg) ---------- */
function ic(string $name, string $class = ''): string {
    return '<svg class="ic' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false"><use href="assets/icons/feather-sprite.svg#' . e($name) . '"/></svg>';
}

/* Kartu ringkasan angka (KPI). $tone: hijau | merah | abu | biru */
function kpi(string $label, string $nilai, string $ikon, string $tone = 'abu', string $sub = '', string $nilaiClass = ''): string {
    return '<div class="kartu kpi"><div class="kpi-top"><span class="label">' . e($label) . '</span><span class="kpi-ic ' . e($tone) . '">' . ic($ikon) . '</span></div>'
         . '<div class="nilai ' . e($nilaiClass) . '">' . e($nilai) . '</div>'
         . ($sub !== '' ? '<div class="sub">' . e($sub) . '</div>' : '') . '</div>';
}

function tgl_id(string $dt): string {
    $t = strtotime($dt);
    return HARI[(int)date('w', $t)] . ', ' . date('j', $t) . ' ' . BULAN[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function tgl_pendek(string $dt): string {
    $t = strtotime($dt);
    return date('j', $t) . ' ' . BULAN[(int)date('n', $t)] . ' ' . date('Y', $t);
}

/* ---------- Auth ---------- */
function user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (user()['role'] ?? '') === 'admin'; }
function require_login(): void {
    if (!user()) { header('Location: login.php'); exit; }
}
function require_admin(): void {
    require_login();
    if (!is_admin()) { http_response_code(403); exit('Halaman ini khusus admin.'); }
}

/* ---------- CSRF ---------- */
function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
function csrf_verify(): void {
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $t)) { http_response_code(419); exit('Sesi tidak valid. Muat ulang halaman.'); }
}

/* ---------- Flash & redirect ---------- */
function flash(string $msg, string $type = 'success'): void { $_SESSION['flash'] = [$msg, $type]; }
function redirect(string $to): void { header('Location: ' . $to); exit; }

/* ---------- JSON API ---------- */
function api_start(): array {
    header('Content-Type: application/json; charset=utf-8');
    if (!user()) { http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Sesi habis, silakan login lagi.']); exit; }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
    csrf_verify();
    return json_decode(file_get_contents('php://input'), true) ?: [];
}
function api_out(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

/* ---------- Pengaturan ---------- */
function setting(string $k, string $default = ''): string {
    static $cache = null;
    if ($cache === null) $cache = db()->query('SELECT kunci, nilai FROM pengaturan')->fetchAll(PDO::FETCH_KEY_PAIR);
    return $cache[$k] ?? $default;
}

/* ---------- Ringkasan untung ---------- */
function ringkasan(string $dari, string $sampai): array {
    $st = db()->prepare("SELECT COUNT(*) trx, COALESCE(SUM(total),0) omzet, COALESCE(SUM(total_modal),0) modal, COALESCE(SUM(untung),0) untung
        FROM transaksi WHERE status='selesai' AND DATE(tanggal) BETWEEN ? AND ?");
    $st->execute([$dari, $sampai]);
    return $st->fetch();
}
