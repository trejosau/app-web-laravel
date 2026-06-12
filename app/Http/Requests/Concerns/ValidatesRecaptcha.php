<?php

namespace App\Http\Requests\Concerns;

use App\Services\RecaptchaService;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\Validation\Validator;

trait ValidatesRecaptcha
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $recaptcha = app(RecaptchaService::class);

            if ($recaptcha->verify($this, $this->input('g-recaptcha-response'))) {
                return;
            }

            app(SecurityAuditService::class)->log($this, 'captcha.failed', 'warning', 422, $this->user()?->id);
            $validator->errors()->add('captcha', config('security_errors.validation.captcha_failed.code').': '.config('security_errors.validation.captcha_failed.userInfo'));
        });
    }
}
