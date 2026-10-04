// --- FITUR 1: JAM REAL-TIME & SAPAAN DINAMIS (TANPA EMOJI) ---
function updateJam() {
    const sekarang = new Date();
    const jam = sekarang.getHours();
    const menit = sekarang.getMinutes().toString().padStart(2, '0');
    const detik = sekarang.getSeconds().toString().padStart(2, '0');
    
    let teks = 'Selamat Malam';
    let idIcon = 'icon-malam';
    
    if (jam >= 4 && jam < 11) { teks = 'Semangat Pagi'; idIcon = 'icon-pagi'; }
    else if (jam >= 11 && jam < 15) { teks = 'Selamat Siang'; idIcon = 'icon-siang'; }
    else if (jam >= 15 && jam < 18) { teks = 'Selamat Sore'; idIcon = 'icon-sore'; }
    
    const elSapaan = document.getElementById('sapaan-waktu');
    const elJam = document.getElementById('jam-realtime');
    
    if (elSapaan) {
        // Mengambil SVG Feather Icon murni dari elemen template
        const svgIcon = document.getElementById(idIcon).innerHTML;
        elSapaan.innerHTML = svgIcon + teks;
    }
    
    if (elJam) {
        const tglOri = elJam.getAttribute('data-tgl');
        elJam.innerText = tglOri + ' • ' + jam.toString().padStart(2, '0') + ':' + menit + ':' + detik;
    }
}
setInterval(updateJam, 1000);
updateJam(); // Jalankan langsung detik pertama

// --- FITUR 2: NOTIFIKASI SWEETALERT & SUARA KASIR ---
document.addEventListener("DOMContentLoaded", () => {
    const flash = document.getElementById('flash-data');
    if (flash) {
        const msg = flash.dataset.pesan;
        const ft = flash.dataset.tipe; // success, danger, info, warning
        
        let type = 'info';
        if (ft === 'success') type = 'success';
        if (ft === 'danger') type = 'error';
        if (ft === 'warning') type = 'warning';

        // Deteksi jika pesan berisi transaksi berhasil (Mainkan Suara Kasir)
        if (msg.toLowerCase().includes('transaksi') && (msg.toLowerCase().includes('berhasil') || msg.toLowerCase().includes('selesai'))) {
            const sound = document.getElementById('suara-kasir');
            if(sound) {
                sound.volume = 0.6;
                sound.play().catch(e => console.log('Suara kasir diblokir izin browser.'));
            }
        }

        // Tampilkan Toast Modern (Notifikasi Melayang)
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer)
                toast.addEventListener('mouseleave', Swal.resumeTimer)
            }
        });

        Toast.fire({
            icon: type,
            title: msg
        });
    }
});

// --- FITUR 3: KONFIRMASI SUBMIT FORM (pengganti onsubmit="return confirm(...)") ---
document.addEventListener('submit', (e) => {
    const pesan = e.target.dataset ? e.target.dataset.confirm : null;
    if (pesan && !window.confirm(pesan)) e.preventDefault();
});
