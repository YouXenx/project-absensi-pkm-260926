<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public const MESSAGE = 'Akun Anda dinonaktifkan. Hubungi administrator sekolah.';

    /**
     * Sign out an account that was deactivated while it still had a live session.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => self::MESSAGE], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->with('alert', [
            'icon' => 'warning',
            'title' => 'Akun nonaktif',
            'text' => self::MESSAGE,
            'toast' => false,
        ]);
    }
}
