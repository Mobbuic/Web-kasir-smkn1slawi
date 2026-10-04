document.getElementById('cari').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  document.querySelectorAll('.tersimpan-row').forEach(r => r.style.display = !q || r.dataset.teks.includes(q) ? '' : 'none');
  document.querySelectorAll('[data-grup]').forEach(g => {
    let n = g.nextElementSibling, ada = false;
    while (n && !n.hasAttribute('data-grup')) { if (n.style.display !== 'none') ada = true; n = n.nextElementSibling; }
    g.style.display = ada ? '' : 'none';
  });
});
