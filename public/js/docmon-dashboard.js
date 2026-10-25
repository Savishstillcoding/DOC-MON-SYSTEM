// Dashboard "live details": stat card numbers count up from 0 on page load.
// The real number is already in the HTML, so without JavaScript (or with
// "reduce motion" turned on) the page simply shows the final value.
(function () {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var DURATION = 700; // ms

  document.querySelectorAll('[data-count]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count'), 10);
    if (!target) return; // 0 or not a number: nothing to animate

    el.textContent = '0';
    var start = null;

    function step(now) {
      if (start === null) start = now;
      var progress = Math.min((now - start) / DURATION, 1);
      var eased = 1 - Math.pow(1 - progress, 3); // fast start, gentle stop
      el.textContent = Math.round(target * eased);
      if (progress < 1) requestAnimationFrame(step);
    }

    requestAnimationFrame(step);
  });
})();
