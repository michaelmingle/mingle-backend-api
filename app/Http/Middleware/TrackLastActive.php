<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps users.last_active_at fresh for the "active users" analytics and for
 * ordering nearby results, throttled to at most one write every 5 minutes.
 */
class TrackLastActive
{
    private const THROTTLE_MINUTES = 5;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user && (
            $user->last_active_at === null
            || $user->last_active_at->lt(now()->subMinutes(self::THROTTLE_MINUTES))
        )) {
            $user->touchLastActive();
        }

        return $response;
    }
}
