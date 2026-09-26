<?php

use App\Http\Middleware\AccessDenied;
use App\Http\Middleware\CheckAdmin;
use App\Http\Middleware\CheckGuru;
use App\Http\Middleware\EnsureActiveAcademicYear;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => CheckAdmin::class,
            'guru' => CheckGuru::class,
            'active-year' => EnsureActiveAcademicYear::class,
        ]);

        // Deactivated accounts are signed out on their next request.
        $middleware->web(append: [EnsureUserIsActive::class]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => route($request->user()->role->dashboardRoute()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Gate/policy denials (403) on pages: back to the user's dashboard with a SweetAlert.
        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request) {
            if (! $request->user()) {
                return null;
            }

            // Framework default text is replaced by our generic message; policy reasons are kept.
            $message = $exception->getMessage() === 'This action is unauthorized.' ? null : $exception->getMessage();

            return AccessDenied::respond($request, $message);
        });
    })->create();
