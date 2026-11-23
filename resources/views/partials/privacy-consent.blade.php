{{-- Consent checkbox on both sign-up forms.
     Clicking "Privacy Notice" opens it in a pop-up over the form (js/privacy-dialog.js),
     so nothing typed is lost. Without JavaScript the link still opens /privacy in a new tab. --}}
<div class="field">
  <label class="privacy-check">
    <input type="checkbox" name="privacy_accepted" value="1" @checked(old('privacy_accepted')) required>
    <span>I have read the <a href="{{ url('/privacy') }}" target="_blank" rel="noopener" data-privacy-open>Privacy Notice</a> and agree to DOC-MON collecting and using my information as it describes.</span>
  </label>
  @error('privacy_accepted')<p class="form-error" role="alert">{{ $message }}</p>@enderror
</div>

{{-- The pop-up. <dialog> gives Esc-to-close and keeps keyboard focus inside it for free.
     The buttons are type="button" so they never submit the sign-up form. --}}
<dialog class="pv-dialog" id="privacy-dialog" aria-labelledby="privacy-dialog-title">
  <div class="pv-dialog-head">
    <h2 id="privacy-dialog-title">Privacy Notice</h2>
    <button type="button" class="pv-dialog-x" data-privacy-close aria-label="Close">&times;</button>
  </div>
  <div class="pv-dialog-body">
    @include('partials.privacy-notice-text', [
        'years' => App\Models\User::RETENTION_YEARS,
        'contactEmail' => config('services.privacy.email'),
    ])
  </div>
  <div class="pv-dialog-foot">
    <button type="button" class="pv-dialog-close" data-privacy-close>Close</button>
  </div>
</dialog>
