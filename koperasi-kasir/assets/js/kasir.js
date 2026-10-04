(function () {
  const P = window.POS;
  const $ = (s) => document.querySelector(s);
  const rp = (n) => 'Rp' + Math.round(Number(n) || 0).toLocaleString('id-ID');
  const BLN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

  const produk = P.produk.map((p) => ({ ...p, id: +p.id, harga_jual: +p.harga_jual, stok: +p.stok, stok_minimum: +p.stok_minimum }));
  const byId = new Map(produk.map((p) => [p.id, p]));
  const cart = new Map(); // id -> qty
  let savedId = +P.savedId || 0;
  let tab = 'semua';
  let metode = 'tunai';
  let diterima = 0;
  let sedangProses = false;

  // Isi keranjang dari transaksi tersimpan (jika dilanjutkan)
  (P.cart || []).forEach((c) => {
    const p = byId.get(+c.id);
    if (p && p.stok > 0) cart.set(p.id, Math.min(+c.qty, p.stok));
  });
  if (P.catatan) $('#catatan').value = P.catatan;

  /* ---------- Util ---------- */
  function toast(msg) {
    const t = $('#toast');
    t.textContent = msg;
    t.classList.add('tampil');
    clearTimeout(toast._t);
    toast._t = setTimeout(() => t.classList.remove('tampil'), 2200);
  }
  const ic = (n, c = '') => `<svg class="ic ${c}" aria-hidden="true" focusable="false"><use href="assets/icons/feather-sprite.svg#${n}"/></svg>`;
  const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const inisial = (nama) => {
    const w = nama.trim().split(/\s+/);
    return (w.length > 1 ? w[0][0] + w[1][0] : nama.slice(0, 2)).toUpperCase();
  };
  const total = () => { let t = 0; cart.forEach((q, id) => (t += q * byId.get(id).harga_jual)); return t; };
  const jumlahItem = () => { let n = 0; cart.forEach((q) => (n += q)); return n; };

  async function kirim(url, data) {
    const r = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF': P.csrf },
      body: JSON.stringify(data),
    });
    let j;
    try { j = await r.json(); } catch (e) { j = { ok: false, error: 'Respon server tidak valid.' }; }
    return j;
  }
  const payload = () => ({ items: [...cart].map(([id, qty]) => ({ id, qty })), saved_id: savedId });
  function resetKeranjang() {
    cart.clear();
    savedId = 0;
    $('#catatan').value = '';
    if (location.search) history.replaceState(null, '', 'kasir.php');
    render();
  }

  /* ---------- Daftar produk ---------- */
  function renderProduk() {
    const q = $('#cari').value.trim().toLowerCase();
    const kat = $('#kat').value;
    const rows = produk.filter((p) =>
      (!q || p.nama.toLowerCase().includes(q)) &&
      (tab !== 'favorit' || +p.favorit === 1) &&
      (!kat || String(p.kategori_id) === kat));
    if (!rows.length) {
      $('#produk-list').innerHTML = `<div class="kosong">${ic('search')}Produk tidak ditemukan.</div>`;
      return;
    }
    $('#produk-list').innerHTML = rows.map((p) => {
      const di = cart.get(p.id) || 0;
      const habis = p.stok <= 0;
      const tipis = !habis && p.stok <= p.stok_minimum;
      const meta = [p.kategori ? esc(p.kategori) : 'Tanpa kategori',
        habis ? '<span class="stok-tipis">Habis</span>' : `<span class="${tipis ? 'stok-tipis' : ''}">Stok ${p.stok}</span>`,
        di ? `<strong>Di keranjang ${di}</strong>` : ''].filter(Boolean).join(' · ');
      return `<button type="button" class="produk-row" data-id="${p.id}" ${habis ? 'disabled' : ''}>
        <span class="badge-inisial tone-${(+p.kategori_id || 0) % 6}">${esc(inisial(p.nama))}</span>
        <span class="min-w-0"><span class="produk-nama d-block">${esc(p.nama)}</span><span class="produk-meta">${meta}</span></span>
        <span class="produk-harga">${rp(p.harga_jual)}</span></button>`;
    }).join('');
  }

  /* ---------- Keranjang ---------- */
  function renderCart() {
    const body = $('#cart-body');
    if (!cart.size) {
      body.innerHTML = `<div class="cart-kosong"><div>${ic('shopping-cart')}<div class="fw-bold mt-2">Keranjang masih kosong</div><div class="small">Ketuk produk untuk menambahkannya.</div></div></div>`;
    } else {
      body.innerHTML = [...cart].map(([id, qty]) => {
        const p = byId.get(id);
        return `<div class="cart-item" data-id="${id}">
          <div class="min-w-0"><div class="nama">${esc(p.nama)}</div><div class="sub">${rp(p.harga_jual)} × ${qty} = <strong>${rp(p.harga_jual * qty)}</strong></div></div>
          <div class="qty"><button type="button" data-act="kurang" aria-label="Kurangi">−</button><span>${qty}</span><button type="button" data-act="tambah" aria-label="Tambah">+</button></div>
        </div>`;
      }).join('');
    }
    $('#cart-total').textContent = rp(total());
    $('#cart-info').textContent = cart.size ? `(${jumlahItem()} item)` : '';
    const kosong = !cart.size;
    $('#btn-bayar').disabled = kosong;
    $('#btn-simpan').disabled = kosong;
    $('#btn-hapus').disabled = kosong;
  }
  function render() { renderProduk(); renderCart(); }

  function tambah(id) {
    const p = byId.get(id);
    const q = (cart.get(id) || 0) + 1;
    if (q > p.stok) { toast(`Stok ${p.nama} hanya ${p.stok}`); return; }
    cart.set(id, q);
    render();
  }
  function kurang(id) {
    const q = (cart.get(id) || 0) - 1;
    if (q <= 0) cart.delete(id); else cart.set(id, q);
    render();
  }

  $('#produk-list').addEventListener('click', (e) => {
    const b = e.target.closest('.produk-row');
    if (b && !b.disabled) tambah(+b.dataset.id);
  });
  $('#cart-body').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-act]');
    if (!b) return;
    const id = +b.closest('.cart-item').dataset.id;
    b.dataset.act === 'tambah' ? tambah(id) : kurang(id);
  });
  $('#cari').addEventListener('input', renderProduk);
  $('#kat').addEventListener('change', renderProduk);
  document.querySelectorAll('.pos-tab').forEach((t) => t.addEventListener('click', () => {
    tab = t.dataset.tab;
    document.querySelectorAll('.pos-tab').forEach((x) => x.classList.toggle('active', x === t));
    renderProduk();
  }));
  $('#btn-hapus').addEventListener('click', () => {
    if (cart.size && confirm('Kosongkan keranjang?')) resetKeranjang();
  });

  /* ---------- Bayar ---------- */
  const modalBayar = new bootstrap.Modal('#modalBayar');
  const modalSimpan = new bootstrap.Modal('#modalSimpan');

  function saran(t) {
    const s = new Set([t]);
    [5000, 10000, 20000, 50000, 100000].forEach((u) => s.add(Math.ceil(t / u) * u));
    return [...s].sort((a, b) => a - b).slice(0, 5);
  }
  function updateBayar() {
    const t = total();
    const tunai = metode === 'tunai';
    $('#tunai-wrap').classList.toggle('d-none', !tunai);
    $('#nontunai-wrap').classList.toggle('d-none', tunai);
    $('#diterima').value = diterima ? diterima.toLocaleString('id-ID') : '';
    const selisih = diterima - t;
    $('#kembalian-label').textContent = selisih < 0 ? 'Kurang' : 'Kembalian';
    $('#kembalian-nilai').textContent = rp(Math.abs(selisih));
    $('#kembalian-nilai').className = selisih < 0 ? 'text-rugi' : '';
    $('#btn-proses').disabled = sedangProses || (tunai && diterima < t);
  }
  $('#btn-bayar').addEventListener('click', () => {
    if (!cart.size) return;
    metode = 'tunai';
    diterima = 0;
    document.querySelectorAll('.metode-btn').forEach((b) => b.classList.toggle('active', b.dataset.metode === 'tunai'));
    $('#bayar-total').textContent = rp(total());
    $('#bayar-error').classList.add('d-none');
    $('#nominal-cepat').innerHTML = saran(total()).map((v) =>
      `<button type="button" data-v="${v}">${v === total() ? 'Uang Pas' : rp(v)}</button>`).join('');
    updateBayar();
    modalBayar.show();
  });
  $('#modalBayar').addEventListener('shown.bs.modal', () => { if (metode === 'tunai') $('#diterima').focus(); });
  $('#metode-wrap').addEventListener('click', (e) => {
    const b = e.target.closest('.metode-btn');
    if (!b) return;
    metode = b.dataset.metode;
    document.querySelectorAll('.metode-btn').forEach((x) => x.classList.toggle('active', x === b));
    updateBayar();
  });
  $('#nominal-cepat').addEventListener('click', (e) => {
    const b = e.target.closest('button[data-v]');
    if (b) { diterima = +b.dataset.v; updateBayar(); }
  });
  $('#diterima').addEventListener('input', (e) => {
    diterima = parseInt(e.target.value.replace(/\D/g, ''), 10) || 0;
    updateBayar();
  });
  $('#diterima').addEventListener('keydown', (e) => { if (e.key === 'Enter' && !$('#btn-proses').disabled) proses(); });
  $('#btn-proses').addEventListener('click', proses);

  async function proses() {
    if (sedangProses || !cart.size) return;
    sedangProses = true;
    updateBayar();
    const snapshot = [...cart].map(([id, qty]) => ({ nama: byId.get(id).nama, qty, harga: byId.get(id).harga_jual }));
    const r = await kirim('api/checkout.php', { ...payload(), metode, bayar: diterima });
    sedangProses = false;
    if (!r.ok) {
      const el = $('#bayar-error');
      el.textContent = r.error || 'Pembayaran gagal.';
      el.classList.remove('d-none');
      updateBayar();
      return;
    }
    // Sinkronkan stok lokal
    cart.forEach((q, id) => { byId.get(id).stok -= q; });
    modalBayar.hide();
    tampilSukses(r, snapshot);
    resetKeranjang();
  }

  function tampilSukses(r, snapshot) {
    const d = r.tanggal.replace(' ', 'T');
    const t = new Date(d);
    const p2 = (n) => String(n).padStart(2, '0');
    $('#sukses-waktu').textContent = `${p2(t.getDate())}-${BLN[t.getMonth()]}-${t.getFullYear()}, ${p2(t.getHours())}:${p2(t.getMinutes())}`;
    $('#s-metode').textContent = { tunai: 'Tunai', qris: 'QRIS', transfer: 'Transfer' }[r.metode];
    $('#s-total').textContent = rp(r.total);
    $('#s-bayar').textContent = rp(r.bayar);
    $('#s-kembali').textContent = rp(r.kembalian);
    const adaUntung = typeof r.untung === 'number';
    $('#s-untung-wrap').classList.toggle('d-none', !adaUntung);
    if (adaUntung) $('#s-untung').textContent = rp(r.untung);

    const struk = `struk.php?id=${r.id}`;
    $('#s-lihat').href = struk;
    $('#s-cetak').onclick = () => window.open(struk + '&print=1', '_blank');
    const teks = [`*${P.koperasi}*`, `Struk ${r.no_struk}`, '',
      ...snapshot.map((s) => `${s.nama} ${s.qty} x ${rp(s.harga)} = ${rp(s.qty * s.harga)}`),
      '', `Total: ${rp(r.total)}`, 'Terima kasih!'].join('\n');
    $('#s-kirim').href = 'https://wa.me/?text=' + encodeURIComponent(teks);
    $('#sukses').classList.add('tampil');
    $('#s-baru').focus();
  }
  $('#s-baru').addEventListener('click', () => { $('#sukses').classList.remove('tampil'); renderProduk(); });

  /* ---------- Simpan draft ---------- */
  $('#btn-simpan').addEventListener('click', () => {
    if (!cart.size) return;
    $('#simpan-error').classList.add('d-none');
    modalSimpan.show();
  });
  $('#modalSimpan').addEventListener('shown.bs.modal', () => $('#catatan').focus());
  $('#catatan').addEventListener('keydown', (e) => { if (e.key === 'Enter') $('#btn-simpan-ok').click(); });
  $('#btn-simpan-ok').addEventListener('click', async () => {
    const btn = $('#btn-simpan-ok');
    btn.disabled = true;
    const r = await kirim('api/simpan.php', { ...payload(), catatan: $('#catatan').value });
    btn.disabled = false;
    if (!r.ok) {
      const el = $('#simpan-error');
      el.textContent = r.error || 'Gagal menyimpan.';
      el.classList.remove('d-none');
      return;
    }
    modalSimpan.hide();
    resetKeranjang();
    toast('Transaksi disimpan');
  });

  render();
})();
