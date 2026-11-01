// Live updates (AUD-050): availability dots, statuses and counts change without a reload.
//
// How it works:
// 1. The page carries a fingerprint of its data (<meta name="docmon-live" data-stamp>).
// 2. Every 20 s, while the tab is visible, we ask the server for the current fingerprint
//    (a tiny JSON answer, not the whole page).
// 3. Only when it changed do we download this page again and swap the inside of each
//    box marked data-live="...". Boxes you are typing in are left alone, and fold-outs
//    you opened or closed stay that way.
// Without JavaScript the pages still work: they just need a reload, like before.
(function () {
  var meta = document.querySelector('meta[name="docmon-live"]');
  if (!meta || !window.fetch || !window.DOMParser) return;

  var EVERY = 20000;           // ask every 20 s
  var IDLE_LIMIT = 30 * 60000; // stop asking after 30 min without mouse/keyboard/touch, so a
                               // forgotten tab doesn't keep the login session alive forever
  var url = meta.getAttribute('content');
  var stamp = meta.getAttribute('data-stamp');
  var lastActive = Date.now();
  var lastAsked = Date.now();
  var busy = false;
  var stopped = false;

  ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart'].forEach(function (type) {
    window.addEventListener(type, function () {
      var wasIdle = Date.now() - lastActive > IDLE_LIMIT;
      lastActive = Date.now();
      if (wasIdle) check(); // back after a break: catch up right away
    }, { passive: true });
  });

  // Coming back to the tab: catch up right away instead of waiting for the next round
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && Date.now() - lastAsked >= EVERY) check();
  });

  setInterval(function () {
    if (!document.hidden && Date.now() - lastActive < IDLE_LIMIT) check();
  }, EVERY);

  function check() {
    if (busy || stopped) return;
    busy = true;
    lastAsked = Date.now();

    fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) {
        // Logged out (or the account was deactivated): stop asking; the next click shows the log-in page
        if (response.redirected || [401, 403, 419].indexOf(response.status) !== -1) {
          stopped = true;
          return null;
        }
        return response.ok ? response.json() : null;
      })
      .then(function (data) {
        if (data && data.stamp && data.stamp !== stamp) return refresh(data.stamp);
      })
      .catch(function () { /* offline, or the server is waking up: try again next round */ })
      .then(function () { busy = false; });
  }

  function refresh(newStamp) {
    return fetch(location.href, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) { return response.ok && !response.redirected ? response.text() : null; })
      .then(function (html) {
        if (!html) return;
        var fresh = new DOMParser().parseFromString(html, 'text/html');
        var changed = false;
        var skipped = false;

        document.querySelectorAll('[data-live]').forEach(function (box) {
          var next = fresh.querySelector('[data-live="' + box.getAttribute('data-live') + '"]');
          if (!next) return;

          // Don't pull a field or button out from under the user's cursor; retry next round
          var focused = document.activeElement;
          if (focused && focused !== document.body && box.contains(focused)) {
            skipped = true;
            return;
          }

          keepFoldOuts(box, next);
          if (box.innerHTML === next.innerHTML) return;

          box.innerHTML = next.innerHTML;
          box.classList.add('is-live-updated'); // no "rise in" animation for swapped cards
          changed = true;
        });

        // Remember the new fingerprint only when every box is up to date
        if (!skipped) stamp = newStamp;
        if (changed) toast();
      });
  }

  // A fold-out (<details data-live-id="...">) the user opened or closed stays that way
  function keepFoldOuts(box, next) {
    box.querySelectorAll('details[data-live-id]').forEach(function (old) {
      var twin = next.querySelector('details[data-live-id="' + old.getAttribute('data-live-id') + '"]');
      if (twin) twin.open = old.open;
    });
  }

  var toastTimer;
  function toast() {
    var note = document.querySelector('.dm-live-toast');
    if (!note) {
      note = document.createElement('p');
      note.className = 'dm-live-toast';
      note.setAttribute('role', 'status'); // screen readers announce it too
      document.body.appendChild(note);
    }
    note.textContent = 'Updated just now';
    note.classList.add('is-shown');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { note.classList.remove('is-shown'); }, 2500);
  }
})();
