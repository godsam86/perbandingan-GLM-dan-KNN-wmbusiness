// ============================================================
// Helper umum untuk halaman WMBusiness99
// ============================================================

if (typeof Chart !== 'undefined') {
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.font.size = 11;
  Chart.defaults.color = '#64748B';
  Chart.defaults.plugins.legend.labels.boxWidth = 10;
  Chart.defaults.plugins.legend.labels.boxHeight = 10;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
}

function fmtRupiah(n) {
  return new Intl.NumberFormat('id-ID').format(Math.round(n));
}
function fmtPersen(n, d = 2) {
  return (n * 100).toFixed(d).replace('.', ',') + '%';
}

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
      new bootstrap.Tooltip(el);
    }
  });
});