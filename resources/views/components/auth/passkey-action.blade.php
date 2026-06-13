@props([
    'title',
    'description',
    'buttonId',
    'buttonLabel',
    'mode',
    'optionsUrl',
    'submitUrl',
    'autoStart' => false,
])

@php
    $errorId = $buttonId.'-error';
    $warningId = $buttonId.'-warning';
    $rpId = (string) config('webauthn.rp_id');
    $showRpWarning = $rpId !== '' && request()->getHost() !== $rpId;
    $viteAvailable = file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json'));
@endphp

<section class="panel">
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
    <p
        class="warning"
        id="{{ $warningId }}"
        role="status"
        aria-live="polite"
        @unless ($showRpWarning) hidden @endunless
    >
        @if ($showRpWarning)
            El dominio actual no coincide con el RP ID configurado. Abre https://{{ $rpId }} directamente.
        @endif
    </p>
    <p class="error" id="{{ $errorId }}" role="alert" aria-live="polite"></p>
    <button
        type="button"
        id="{{ $buttonId }}"
        data-webauthn-button
        data-mode="{{ $mode }}"
        data-options-url="{{ $optionsUrl }}"
        data-submit-url="{{ $submitUrl }}"
        data-error-id="{{ $errorId }}"
        data-warning-id="{{ $warningId }}"
        @if ($autoStart) data-auto-start="true" @endif
        data-message-https-required="{{ config('security_errors.passkey.https_required.code').': '.config('security_errors.passkey.https_required.userInfo') }}"
        data-message-browser-unsupported="{{ config('security_errors.passkey.browser_unsupported.code').': '.config('security_errors.passkey.browser_unsupported.userInfo') }}"
        data-message-options-failed="{{ config('security_errors.passkey.options_failed.code').': '.config('security_errors.passkey.options_failed.userInfo') }}"
        data-message-completion-failed="{{ config('security_errors.passkey.validation_failed.code').': '.config('security_errors.passkey.validation_failed.userInfo') }}"
        data-message-cancelled-or-expired="{{ config('security_errors.passkey.cancelled_or_expired.code').': '.config('security_errors.passkey.cancelled_or_expired.userInfo') }}"
        data-message-csrf-missing="{{ config('security_errors.csrf.failed.code').': '.config('security_errors.csrf.failed.userInfo') }}"
        aria-describedby="{{ $warningId }} {{ $errorId }}"
    >
        {{ $buttonLabel }}
    </button>
</section>

@once
    @if ($viteAvailable)
        @vite('resources/js/app.js')
    @else
        <script type="module" src="{{ asset('js/webauthn/passkey-client.js') }}"></script>
    @endif
@endonce
