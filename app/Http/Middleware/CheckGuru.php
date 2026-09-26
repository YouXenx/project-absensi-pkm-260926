<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckGuru
{
    /**
     * Only let users with the "guru" role through.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isGuru()) {
            return $next($request);
        }

        return AccessDenied::respond($request);
    }
}
