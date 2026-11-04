{{-- Consent checkbox on both sign-up forms (AUD-078). Opens the notice in a new tab, so the form isn't lost. --}}
<div class="field">
  <label class="privacy-check">
    <input type="checkbox" name="privacy_accepted" value="1" @checked(old('privacy_accepted')) required>
    <span>I have read the <a href="{{ url('/privacy') }}" target="_blank" rel="noopener">Privacy Notice</a> and agree to DOC-MON collecting and using my information as it describes.</span>
  </label>
  @error('privacy_accepted')<p class="form-error" role="alert">{{ $message }}</p>@enderror
</div>
