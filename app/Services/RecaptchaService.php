<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class RecaptchaService
{
    public function enabled(): bool
    {
        return (bool) config('recaptcha.enabled');
    }

    public function verify(Request $request, ?string $token): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        if (! is_string($token) || $token === '') {
            return false;
        }

        $secret = config('recaptcha.secret_key');
        $siteKey = config('recaptcha.site_key');

        if (! is_string($secret) || $secret === '' || ! is_string($siteKey) || $siteKey === '') {
            return false;
        }

        $response = Http::timeout(10)->asForm()->post((string) config('recaptcha.verify_url'), [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (! $response->ok()) {
            return false;
        }

        return (bool) data_get($response->json(), 'success');
    }
}
