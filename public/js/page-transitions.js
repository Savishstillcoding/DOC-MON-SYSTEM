// Signup and login show the same role buttons. When moving between those
// two pages, mark the page with "keep-choices" so transitions.css keeps the
// buttons still instead of dropping them out and rising them back in.
// Runs in <head> so the class is set before the page transition starts.
(function () {
  var rolePages = ['/signup', '/login'];
  var from;
  try {
    from = new URL(document.referrer);
  } catch (e) {
    return; // no previous page (typed URL, bookmark, refresh from nowhere)
  }

  var cameFromOurSite = from.origin === location.origin;
  var fromRolePage = rolePages.indexOf(from.pathname) !== -1;
  var onRolePage = rolePages.indexOf(location.pathname) !== -1;

  if (cameFromOurSite && fromRolePage && onRolePage && from.pathname !== location.pathname) {
    document.documentElement.classList.add('keep-choices');
  }
})();
