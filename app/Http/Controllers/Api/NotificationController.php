<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications();

        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }

        $results = $query->paginate($this->perPage());

        return $this->ok([
            'items' => NotificationResource::collection($results->getCollection())->resolve($request),
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
                'has_more' => $results->hasMorePages(),
            ],
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return $this->fail('Notification not found.', 404);
        }

        $notification->markAsRead();

        return $this->ok(new NotificationResource($notification->refresh()), 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->ok(['unread_count' => 0], 'All notifications marked as read.');
    }
}
