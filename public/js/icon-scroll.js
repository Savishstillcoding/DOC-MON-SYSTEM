// Keeps the falling file icons in one continuous motion across pages.
// Loaded in <head>, so it runs before the icons are drawn for the first time.

// 1) Start the animation at the right point in time.
// Every page load would normally restart the animation from the top, so we
// jump ahead to where it "should" be based on the clock. All pages share
// the same clock, so switching pages doesn't reset it. This is set as a CSS
// variable on <html> (the icons don't exist yet), and each page's CSS uses
// it: .icon-track { animation-delay: var(--icons-delay); }
// Must match the animation length in the page CSS (20s).
var ICON_LOOP_MS = 20000;
document.documentElement.style.setProperty(
  '--icons-delay',
  -(Date.now() % ICON_LOOP_MS) + 'ms'
);

// Same idea for the wire pulses behind the dark panel (css/wires.css).
// Their loops have different lengths, so instead of one loop we use "how
// long ago the current hour started"; every pulse then lands where it would
// be if it had been running all along. (It only resets once an hour.)
document.documentElement.style.setProperty(
  '--wires-offset',
  -(Date.now() % 3600000) + 'ms'
);

// 2) Hide the old page's icons as soon as this page's icons can be drawn.
// During a page switch the old page's icons are shown on top as a still
// picture, so the empty white panel never flashes (see transitions.css).
// That picture must go away as early as possible, or the icons look frozen
// and then jump. .icons-ready removes it.
document.addEventListener('DOMContentLoaded', function () {
  var root = document.documentElement;
  var images = Array.prototype.slice.call(document.querySelectorAll('.icon-track img'));

  // Usual case when switching pages: the image is cached and already loaded,
  // so remove the still picture right away instead of waiting.
  var allLoaded = images.every(function (img) {
    return img.complete && img.naturalWidth > 0;
  });
  if (allLoaded) {
    root.classList.add('icons-ready');
    return;
  }

  // First visit: wait until the images are downloaded and ready to draw.
  Promise.all(images.map(function (img) {
    return img.decode().catch(function () {});
  })).then(function () {
    root.classList.add('icons-ready');
  });
});
