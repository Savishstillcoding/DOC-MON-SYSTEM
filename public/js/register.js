// School/Employee ID upload box:
// - shows the chosen file's name, so the user can see their upload worked
// - enforces the 2 MB limit (AUD-040): PHP silently drops bigger uploads, and
//   phone photos are often 3-5 MB, so big photos are shrunk here first.
//   Big PDFs can't be shrunk, so they get a clear message instead.
// This is only a convenience: the server must still check the size (Milestone 2).
(function () {
  var MAX_BYTES = 2 * 1024 * 1024;   // must match the server limit
  var MAX_SIDE = 1600;               // px; still sharp enough to read an ID card

  var input = document.getElementById('school_id');
  var text = document.querySelector('[data-upload-text]');
  var error = document.getElementById('school_id_error');
  if (!input || !text) return;

  function mb(bytes) {
    return (bytes / 1024 / 1024).toFixed(1) + ' MB';
  }

  function show(label, hasFile) {
    text.textContent = label;
    text.classList.toggle('has-file', hasFile);
  }

  function fail(message) {
    input.value = '';                // empty again, so "required" blocks submitting
    show('Click to upload', false);
    if (error) {
      error.textContent = message;
      error.hidden = false;
    }
  }

  // Draws the photo smaller and saves it as a JPEG; resolves with the new file
  function shrink(file) {
    return new Promise(function (resolve, reject) {
      var img = new Image();
      var url = URL.createObjectURL(file);

      img.onload = function () {
        URL.revokeObjectURL(url);
        var scale = Math.min(1, MAX_SIDE / Math.max(img.naturalWidth, img.naturalHeight));
        var canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);

        var tryQuality = function (quality) {
          canvas.toBlob(function (blob) {
            if (!blob) return reject();
            if (blob.size > MAX_BYTES && quality > 0.5) return tryQuality(quality - 0.15);
            var name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
            resolve(new File([blob], name, { type: 'image/jpeg' }));
          }, 'image/jpeg', quality);
        };
        tryQuality(0.85);
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
        reject();                    // e.g. a photo format this browser can't open
      };
      img.src = url;
    });
  }

  input.addEventListener('change', function () {
    var file = input.files && input.files[0];
    if (error) error.hidden = true;

    if (!file) return show('Click to upload', false);
    if (file.size <= MAX_BYTES) return show(file.name, true);

    if (!file.type.startsWith('image/')) {
      return fail('This file is ' + mb(file.size) + '. The limit is 2 MB. Please upload a smaller PDF, or a photo of your ID instead.');
    }

    show('Shrinking photo…', false);
    shrink(file).then(function (small) {
      if (small.size > MAX_BYTES) throw new Error('still too big');

      // Swap the big photo for the small one inside the file input
      var swap = new DataTransfer();
      swap.items.add(small);
      input.files = swap.files;
      show(small.name + ' (shrunk from ' + mb(file.size) + ' to ' + mb(small.size) + ')', true);
    }).catch(function () {
      fail('This photo is ' + mb(file.size) + ' and could not be shrunk below 2 MB. Please take a smaller photo or crop it.');
    });
  });
})();
