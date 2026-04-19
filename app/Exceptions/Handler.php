<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
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
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render exception ke HTTP response.
     *
     * TKT-08 — perbaikan:
     * Sebelumnya override ini membungkus **semua** exception menjadi
     * status 500 dengan body `{success:false, message}`. Efek samping:
     *   - Validator gagal (semestinya 422) muncul di chat FE sebagai
     *     `500 - The given data was invalid.` — tidak bisa dibedakan dari
     *     error server sungguhan, dan body standar Laravel
     *     `{message, errors}` hilang.
     *   - Auth gagal (semestinya 401) jadi 500 → interceptor FE tidak
     *     bisa membedakan "tidak login" dari "server error".
     *   - Resource tidak ditemukan (semestinya 404) juga jadi 500.
     *
     * Sekarang: hanya exception generik (tidak membawa semantik HTTP)
     * yang dibungkus 500. Exception yang sudah punya makna HTTP jelas
     * didelegasikan ke implementasi parent Laravel yang mengembalikan
     * status + body standar:
     *   - ValidationException         → 422 {message, errors}
     *   - AuthenticationException     → 401 {message}
     *   - AuthorizationException      → 403
     *   - ModelNotFoundException /
     *     NotFoundHttpException       → 404
     *   - MethodNotAllowedHttpException → 405
     *   - ThrottleRequestsException   → 429
     *   - HttpExceptionInterface apa pun → status dari exception
     *
     * Untuk exception generik, log-kan supaya backend dev tidak "buta"
     * saat mendiagnosis (sebelumnya exception hanya muncul di body HTTP
     * response dan tidak pernah masuk ke laravel.log).
     */
    public function render($request, Throwable $e)
    {
        if ($this->shouldDelegateToParent($e)) {
            return parent::render($request, $e);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            $message = $e->getMessage() ?: class_basename($e);

            Log::error('Unhandled exception → JSON 500', [
                'exception' => get_class($e),
                'message'   => $message,
                'path'      => $request->path(),
                'method'    => $request->method(),
                'user_id'   => optional($request->user())->id,
                // Trace dibatasi 5 frame supaya log tidak membengkak;
                // cukup untuk identifikasi file+line sumber bug.
                'trace'     => collect($e->getTrace())
                    ->take(5)
                    ->map(fn ($t) => ($t['file'] ?? '?') . ':' . ($t['line'] ?? '?'))
                    ->values()
                    ->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 500);
        }

        return parent::render($request, $e);
    }

    /**
     * Exception yang dibiarkan dirender Laravel default karena status HTTP
     * dan body-nya sudah sesuai konvensi (tidak perlu dibungkus 500).
     *
     * Catatan: `ModelNotFoundException` dikenali secara eksplisit di sini
     * meskipun Laravel otomatis meng-konversinya ke `NotFoundHttpException`
     * di `prepareException()` — redundant tapi defensif, kalau alur konversi
     * berubah di versi framework berikutnya.
     */
    private function shouldDelegateToParent(Throwable $e): bool
    {
        return $e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof ModelNotFoundException
            || $e instanceof NotFoundHttpException
            || $e instanceof MethodNotAllowedHttpException
            || $e instanceof ThrottleRequestsException
            || $e instanceof HttpExceptionInterface;
    }
}
