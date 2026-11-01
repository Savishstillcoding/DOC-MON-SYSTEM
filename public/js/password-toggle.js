// Show / hide password (AUD-056): an eye button inside every password box, so people
// can double-check what they typed. Used on the log-in, sign-up and profile pages.
//
// - The password starts hidden; it is only shown while the person asks for it.
// - On submit it is hidden again, so it is never left on screen after the page changes
//   (and browsers still offer to save it as a password).
// - Without JavaScript the boxes simply work as normal password boxes.
(function () {
  var SVG = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
  var EYE = SVG + '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_OFF = SVG + '<path d="M10.7 5.1A10.4 10.4 0 0 1 12 5c7 0 10 7 10 7a13.2 13.2 0 0 1-1.7 2.7"/>'
    + '<path d="M6.6 6.6A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.4-1.6"/>'
    + '<path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/></svg>';

  document.querySelectorAll('input[type="password"]').forEach(function (input) {
    var wrap = document.createElement('span');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    input.classList.add('pw-input');

    var button = document.createElement('button');
    button.type = 'button'; // never submits the form
    button.className = 'pw-toggle';
    wrap.appendChild(button);

    function show(visible) {
      input.type = visible ? 'text' : 'password';
      button.innerHTML = visible ? EYE_OFF : EYE;
      button.setAttribute('aria-pressed', visible ? 'true' : 'false');
      button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      button.title = visible ? 'Hide password' : 'Show password';
    }

    button.addEventListener('click', function () {
      show(input.type === 'password');
      input.focus(); // keep typing where you were
    });

    if (input.form) {
      input.form.addEventListener('submit', function () { show(false); });
    }

    show(false);
  });
})();
