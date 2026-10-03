// Shows the chosen file's name in the School ID upload box, so the user
// can see their upload worked (instead of "Click to upload" staying there).
(function () {
  var input = document.getElementById('school_id');
  var text = document.querySelector('[data-upload-text]');
  if (!input || !text) return;

  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    text.textContent = file ? file.name : 'Click to upload';
    text.classList.toggle('has-file', Boolean(file));
  });
})();
