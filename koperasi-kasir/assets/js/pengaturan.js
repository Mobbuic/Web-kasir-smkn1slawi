const mpw = new bootstrap.Modal('#modalPw');
document.querySelectorAll('.btn-pw').forEach((b) => b.addEventListener('click', () => {
  document.getElementById('pw-id').value = b.dataset.id;
  document.getElementById('pw-nama').textContent = b.dataset.nama;
  document.getElementById('pw-baru').value = '';
  mpw.show();
}));
