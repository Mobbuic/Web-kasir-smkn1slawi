document.querySelectorAll('[data-action="print"]').forEach((el) => {
  el.addEventListener('click', () => window.print());
});
document.querySelectorAll('[data-action="close"]').forEach((el) => {
  el.addEventListener('click', () => window.close());
});
if (document.body.dataset.autoprint) {
  window.addEventListener('load', () => window.print());
}
