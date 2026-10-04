const { labels: LBL, val: VAL } = window.POS;
Chart.defaults.font.family = "'Plus Jakarta Sans', system-ui, sans-serif"; Chart.defaults.color = '#64748b';
new Chart(document.getElementById('grafik'), {
  type: 'line',
  data: { 
      labels: LBL, 
      datasets: [{ 
          label: 'Laba Bersih', 
          data: VAL, 
          borderColor: '#00bcd4', 
          backgroundColor: 'rgba(0, 188, 212, 0.1)',
          borderWidth: 3,
          pointBackgroundColor: '#fff',
          pointBorderColor: '#00bcd4',
          pointBorderWidth: 2,
          pointRadius: 4,
          fill: true,
          tension: 0.4
      }] 
  },
  options: { 
      maintainAspectRatio: false, 
      plugins: { 
          legend: { display: false },
          tooltip: { 
              backgroundColor: '#1e293b',
              titleFont: { size: 13 },
              bodyFont: { size: 14, weight: 'bold' },
              padding: 12,
              callbacks: { label: (c) => ' Laba: Rp ' + Number(c.parsed.y).toLocaleString('id-ID') } 
          } 
      },
      scales: { 
          x: { grid: { display: false } }, 
          y: { 
              beginAtZero: true, 
              border: { dash: [4, 4] },
              grid: { color: '#f1f5f9' },
              ticks: { callback: (v) => v >= 1000 ? (v / 1000) + 'rb' : v, font: { weight: '500' } } 
          } 
      } 
  }
});
