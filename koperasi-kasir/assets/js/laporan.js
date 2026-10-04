const { labels: LBL, omzet: OMZET, untung: UNTUNG } = window.POS;
const fmt = (v) => 'Rp' + Number(v).toLocaleString('id-ID');
Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif"; Chart.defaults.color = '#6F6C7D';
new Chart(document.getElementById('grafik'), {
  type: 'bar',
  data: { labels: LBL, datasets: [
    { label: 'Omzet', data: OMZET, backgroundColor: '#F3B3AA', borderRadius: 3 },
    { label: 'Untung', data: UNTUNG, backgroundColor: '#2FA84F', borderRadius: 3 } ] },
  options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
    plugins: { tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + fmt(c.parsed.y) } } },
    scales: { x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } }, y: { beginAtZero: true, ticks: { callback: (v) => v >= 1000 ? (v / 1000) + 'rb' : v } } } }
});

document.querySelectorAll('[data-action="print"]').forEach((el) => {
  el.addEventListener('click', () => window.print());
});
