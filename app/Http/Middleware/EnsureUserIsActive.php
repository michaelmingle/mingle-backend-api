<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended and banned accounts keep valid tokens but lose API access, so the
 * check lives here rather than at login only.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== UserStatus::Active) {
            return ApiResponse::error(
                $user->status === UserStatus::Banned
                    ? 'This account has been banned.'
                    : 'This account is suspended.',
                403
            );
        }

        return $next($request);
    }
}
