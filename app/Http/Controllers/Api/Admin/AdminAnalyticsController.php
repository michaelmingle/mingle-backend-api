<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ConnectionRequestStatus;
use App\Enums\EventStatus;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\ConnectionRequest;
use App\Models\Event;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminAnalyticsController extends Controller
{
    /** Dashboard summary for the (future) admin web console. */
    public function summary(): JsonResponse
    {
        $handledRequests = ConnectionRequest::query()
            ->whereIn('status', [
                ConnectionRequestStatus::Accepted->value,
                ConnectionRequestStatus::Declined->value,
            ])
            ->count();

        $acceptedRequests = ConnectionRequest::query()
            ->where('status', ConnectionRequestStatus::Accepted->value)
            ->count();

        return $this->ok([
            'total_users' => User::query()->count(),
            'active_users_7d' => User::query()->where('last_active_at', '>=', now()->subDays(7))->count(),
            'new_users_7d' => User::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'suspended_users' => User::query()->where('status', UserStatus::Suspended->value)->count(),
            'banned_users' => User::query()->where('status', UserStatus::Banned->value)->count(),
            'verified_users' => User::query()->where('is_verified', true)->count(),
            'premium_users' => User::query()->where('is_premium', true)->count(),
            'total_connections' => Connection::query()->count(),
            'connections_7d' => Connection::query()->where('connected_at', '>=', now()->subDays(7))->count(),
            'total_events' => Event::query()->count(),
            'published_events' => Event::query()->where('status', EventStatus::Published->value)->count(),
            'upcoming_events' => Event::query()->published()->upcoming()->count(),
            'pending_connection_requests' => ConnectionRequest::query()
                ->where('status', ConnectionRequestStatus::Pending->value)
                ->count(),
            'connection_acceptance_rate' => $handledRequests > 0
                ? round($acceptedRequests / $handledRequests, 4)
                : null,
            'pending_reports' => Report::query()->where('status', 'pending')->count(),
        ]);
    }
}
