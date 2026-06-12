<?php

namespace App\Exceptions;

use App\Services\SecurityAuditService;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<Throwable>, LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e): bool {
            $context = $this->logExceptionContext($e);

            Log::error($this->exceptionMessage($e), $context);
            Log::channel('development')->error($this->exceptionMessage($e), $context);

            return false;
        });
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof TokenMismatchException) {
            return $this->handleTokenMismatch($request);
        }

        return parent::render($request, $e);
    }

    private function handleTokenMismatch(Request $request): JsonResponse|RedirectResponse
    {
        $userId = $request->user()?->id ?: $request->session()->get('auth_pending_user_id');

        try {
            app(SecurityAuditService::class)->log($request, 'csrf.failed', 'warning', 419, $userId ?: null);
        } catch (Throwable) {
            //
        }

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        Cookie::queue(Cookie::forget((string) config('session.cookie')));

        if ($request->expectsJson()) {
            return response()->json([
                'code' => config('security_errors.csrf.failed.code'),
                'userInfo' => config('security_errors.csrf.failed.userInfo'),
            ], 419);
        }

        return redirect()->route('login')->withErrors([
            'username' => config('security_errors.csrf.failed.code').': '.config('security_errors.csrf.failed.userInfo'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function logExceptionContext(Throwable $e): array
    {
        $request = app()->bound('request') ? request() : null;

        return array_filter([
            'exception' => $e::class,
            'code' => $e->getCode(),
            'file' => str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()),
            'line' => $e->getLine(),
            'method' => $request instanceof Request ? $request->method() : null,
            'route' => $request instanceof Request ? $request->path() : null,
            'user_id' => $request instanceof Request ? $request->user()?->id : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function exceptionMessage(Throwable $e): string
    {
        return $e->getMessage() !== '' ? $e->getMessage() : class_basename($e);
    }
}
