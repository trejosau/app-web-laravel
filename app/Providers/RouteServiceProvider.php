<?php

namespace App\Providers;

use App\Services\SecurityAuditService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        foreach (array_keys(config('rate_limits')) as $limiter) {
            RateLimiter::for($limiter, fn (Request $request) => $this->configuredLimit($limiter, $request));
        }
    }

    /**
     * Create the named Laravel rate limit from config/rate_limits.php.
     */
    private function configuredLimit(string $limiter, Request $request): Limit
    {
        $config = config("rate_limits.{$limiter}");
        $attempts = max(1, (int) ($config['attempts'] ?? 1));
        $decayMinutes = max(1, (int) ($config['decay_minutes'] ?? 1));
        $limit = $decayMinutes === 1
            ? Limit::perMinute($attempts)
            : Limit::perMinutes($decayMinutes, $attempts);

        return $limit
            ->by($this->rateLimitKey($request, (string) ($config['key'] ?? 'pending_user_ip')))
            ->response(fn (Request $request, array $headers) => $this->rateLimitResponse($request, $headers, $limiter));
    }

    /**
     * Build a non-reversible limiter key from the configured strategy.
     */
    private function rateLimitKey(Request $request, string $strategy): string
    {
        return match ($strategy) {
            'user_or_client' => $this->sha512Key((string) ($request->user()?->id ?: $this->clientFingerprint($request))),
            'registration_identity_ip' => $this->sha512Key(
                mb_strtolower(trim((string) $request->input('username'))).'|'.
                mb_strtolower(trim((string) $request->input('email'))).'|'.
                $request->ip()
            ),
            'username_ip' => $this->sha512Key(mb_strtolower(trim((string) $request->input('username'))).'|'.$request->ip()),
            'email_ip' => $this->sha512Key(mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip()),
            default => $this->userIpKey($request),
        };
    }

    /**
     * Build a client fingerprint for guest/API requests.
     */
    private function clientFingerprint(Request $request): string
    {
        return implode('|', [
            $request->session()->getId(),
            (string) $request->userAgent(),
            (string) $request->ip(),
        ]);
    }

    /**
     * Build a limiter key from authenticated or pending user plus IP.
     */
    private function userIpKey(Request $request): string
    {
        $userId = $request->user()?->id ?: $request->session()->get('auth_pending_user_id', 'guest');

        return $this->sha512Key($userId.'|'.$request->ip());
    }

    /**
     * Hash limiter keys before storing them in cache.
     */
    private function sha512Key(string $value): string
    {
        return hash('sha512', $value);
    }

    /**
     * Write audit trace and return a Spanish 429 response.
     *
     * @param  array<string, string>  $headers
     */
    private function rateLimitResponse(Request $request, array $headers, string $limiter)
    {
        $userId = $request->user()?->id ?: $request->session()->get('auth_pending_user_id');

        app(SecurityAuditService::class)->rateLimited($request, $limiter, $userId ?: null);

        return response(config('security_errors.rate_limit.blocked.code').': '.config('security_errors.rate_limit.blocked.userInfo'), 429, $headers);
    }
}
