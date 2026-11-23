// Sign-up forms: open the Privacy Notice in a pop-up instead of a new tab,
// so the officer doesn't lose what they typed. Close it with the X, the
// Close button, Esc, or a click on the dark area around it.
(function () {
  var dialog = document.getElementById('privacy-dialog');
  var link = document.querySelector('[data-privacy-open]');
  if (!dialog || !link || typeof dialog.showModal !== 'function') return; // old browser: the link opens /privacy in a new tab

  link.addEventListener('click', function (e) {
    e.preventDefault();   // don't open the new tab, and don't tick the checkbox the link sits in
    dialog.showModal();
    dialog.querySelector('.pv-dialog-body').scrollTop = 0;
  });

  dialog.querySelectorAll('[data-privacy-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { dialog.close(); });
  });

  // A click on the dialog element itself (not its contents) is a click on the dark area
  dialog.addEventListener('click', function (e) {
    if (e.target === dialog) dialog.close();
  });
})();
