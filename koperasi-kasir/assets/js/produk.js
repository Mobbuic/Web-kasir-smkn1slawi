const $ = (s) => document.querySelector(s);
const rp = (n) => (n < 0 ? '-' : '') + 'Rp' + Math.abs(Math.round(n)).toLocaleString('id-ID');
const mp = new bootstrap.Modal('#modalProduk'), ms = new bootstrap.Modal('#modalStok');

function hitungUntung() {
  const u = (+$('#f-jual').value || 0) - (+$('#f-beli').value || 0);
  const j = +$('#f-jual').value || 0;
  const box = $('#f-untung');
  box.lastElementChild.textContent = rp(u) + (j ? ' (' + Math.round(u / j * 100) + '%)' : '');
  box.lastElementChild.className = u < 0 ? 'text-rugi' : 'text-untung';
}
['#f-jual', '#f-beli'].forEach((s) => $(s).addEventListener('input', hitungUntung));

function isiForm(p) {
  $('#f-judul').textContent = p ? 'Ubah Informasi Produk' : 'Tambah Produk Baru';
  $('#f-id').value = p ? p.id : 0;
  $('#f-nama').value = p ? p.nama : '';
  $('#f-kode').value = p && p.kode ? p.kode : '';
  $('#f-kat').value = p && p.kategori_id ? p.kategori_id : '';
  $('#f-sup').value = p && p.suplier_id ? p.suplier_id : '';
  $('#f-beli').value = p ? p.harga_beli : 0;
  $('#f-jual').value = p ? p.harga_jual : '';
  $('#f-stok').value = p ? p.stok : 0;
  $('#f-min').value = p ? p.stok_minimum : 5;
  $('#f-fav').checked = p ? +p.favorit === 1 : false;
  $('#f-aktif').checked = p ? +p.aktif === 1 : true;
  hitungUntung();
  mp.show();
}
$('#btn-baru').addEventListener('click', () => isiForm(null));
document.querySelectorAll('.btn-edit').forEach((b) => b.addEventListener('click', () => isiForm(JSON.parse(b.dataset.p))));
document.querySelectorAll('.btn-stok').forEach((b) => b.addEventListener('click', () => {
  $('#s-id').value = b.dataset.id; $('#s-nama').textContent = b.dataset.nama; $('#s-qty').value = '';
  ms.show(); setTimeout(() => $('#s-qty').focus(), 300);
}));

function saring() {
  const q = $('#cari').value.trim().toLowerCase(), k = $('#fkat').value, s = $('#fsup').value;
  document.querySelectorAll('#tabel tbody tr').forEach((r) => {
    // Tampilkan jika sesuai dengan kata kunci, DAN sesuai kategori, DAN sesuai suplier
    r.style.display = (!q || r.dataset.teks.includes(q)) && 
                      (!k || r.dataset.kat === k) &&
                      (!s || r.dataset.sup === s) ? '' : 'none';
  });
}
$('#cari').addEventListener('input', saring);
$('#fkat').addEventListener('change', saring);
$('#fsup').addEventListener('change', saring);
