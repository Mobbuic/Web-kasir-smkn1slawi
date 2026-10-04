document.getElementById('lihat').addEventListener('click', function () {
    const i = document.getElementById('password');
    const tampil = i.type === 'password';

    i.type = tampil ? 'text' : 'password';

    // Warna aktif biru cyan saat password tampil, kembali ke currentColor saat disembunyikan
    document.getElementById('eyeIcon').classList.toggle('aktif', tampil);
});
