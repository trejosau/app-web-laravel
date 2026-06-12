@if (config('recaptcha.enabled') && filled(config('recaptcha.site_key')))
    <div class="g-recaptcha" data-sitekey="{{ config('recaptcha.site_key') }}"></div>
    @error('captcha')
        <p class="error">{{ $message }}</p>
    @enderror
    @once
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endonce
@endif
