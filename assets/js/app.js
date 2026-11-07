// Konfirmasi sebelum submit: <form data-confirm="Yakin?">
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) e.preventDefault();
});
// Filter otomatis: <select data-autosubmit>
document.addEventListener('change', function (e) {
  if (e.target.matches && e.target.matches('[data-autosubmit]') && e.target.form) e.target.form.submit();
});

// Pratinjau foto di kotak unggah: <input type="file" data-preview> di dalam .drop
document.addEventListener('change', function (e) {
  var t = e.target;
  if (!(t.matches && t.matches('input[type=file][data-preview]'))) return;
  var box = t.closest('.drop'), img = box.querySelector('.drop-prev'), nm = box.querySelector('.drop-name');
  var f = t.files && t.files[0];
  var reset = function () { img.hidden = true; img.removeAttribute('src'); nm.textContent = ''; box.classList.remove('has-file'); };
  if (!f) { reset(); return; }
  if (f.size > 5 * 1024 * 1024) { alert('Ukuran foto maksimal 5 MB.'); t.value = ''; reset(); return; }
  if (!/^image\/(png|jpeg)$/.test(f.type)) { alert('Foto harus berformat PNG atau JPG.'); t.value = ''; reset(); return; }
  nm.textContent = f.name; img.src = URL.createObjectURL(f); img.hidden = false; box.classList.add('has-file');
});
// Isi otomatis kolom lain dari pilihan: <select data-fill="#id"> dengan <option data-val="...">
function simsarFill(sel) {
  var o = sel.options[sel.selectedIndex], t = document.querySelector(sel.getAttribute('data-fill'));
  if (t) t.value = (o && o.getAttribute('data-val')) || '';
}
document.addEventListener('change', function (e) {
  if (e.target.matches && e.target.matches('select[data-fill]')) simsarFill(e.target);
});
document.querySelectorAll('select[data-fill]').forEach(simsarFill);

// Dialog konfirmasi (mis. Keluar): tombol [data-open-dialog="id"] dan [data-close-dialog]
document.addEventListener('click', function (e) {
  var o = e.target.closest && e.target.closest('[data-open-dialog]');
  if (o) { var d = document.getElementById(o.getAttribute('data-open-dialog')); if (d && d.showModal) { e.preventDefault(); d.showModal(); } return; }
  var c = e.target.closest && e.target.closest('[data-close-dialog]');
  if (c) { c.closest('dialog').close(); return; }
  if (e.target.tagName === 'DIALOG') e.target.close();          // klik di luar kotak
});

// Sidebar di HP: tombol burger membuka/menutup laci menu
(function () {
  var app = document.querySelector('.app');
  if (!app) return;
  var burger = document.querySelector('[data-toggle-sidebar]');
  var sidebar = document.getElementById('sidebar');
  function setOpen(open) {
    app.classList.toggle('sb-open', open);
    document.body.classList.toggle('sb-lock', open);
    if (burger) {
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Tutup menu' : 'Buka menu');
    }
    if (open) {
      var first = sidebar && sidebar.querySelector('nav a');
      if (first) first.focus();
    } else if (burger && sidebar && sidebar.contains(document.activeElement)) {
      burger.focus();
    }
  }
  document.addEventListener('click', function (e) {
    if (!e.target.closest) return;
    if (e.target.closest('[data-toggle-sidebar]')) { setOpen(!app.classList.contains('sb-open')); return; }
    if (e.target.closest('[data-close-sidebar]')) setOpen(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && app.classList.contains('sb-open')) setOpen(false);
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth > 860 && app.classList.contains('sb-open')) setOpen(false);
  });
})();
