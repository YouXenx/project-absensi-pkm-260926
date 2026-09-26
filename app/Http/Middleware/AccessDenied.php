<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AccessDenied
{
    public const MESSAGE = 'Anda tidak punya akses';

    /**
     * Page requests go back to the user's own dashboard (or login) with a SweetAlert
     * dialog rendered by partials/flash.blade.php; Ajax/JSON requests get a plain 403.
     * A policy can pass a more specific reason (e.g. "hanya bisa mengubah absensi hari ini").
     */
    public static function respond(Request $request, ?string $message = null): Response
    {
        $message = filled($message) ? $message : self::MESSAGE;

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        $user = $request->user();

        $destination = $user
            ? redirect()->route($user->role->dashboardRoute())
            : redirect()->route('login');

        return $destination->with('alert', [
            'icon' => 'error',
            'title' => 'Akses ditolak',
            'text' => $message,
            'toast' => false,
        ]);
    }
}
