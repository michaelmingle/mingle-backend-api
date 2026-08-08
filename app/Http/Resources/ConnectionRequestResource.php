<?php

namespace App\Http\Resources;

use App\Models\ConnectionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ConnectionRequest */
class ConnectionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'message' => $this->message,
            'direction' => $viewer && $viewer->id === $this->sender_id ? 'outgoing' : 'incoming',
            'sender' => $this->whenLoaded('sender', fn () => (new PublicUserResource($this->sender))->resolve($request)),
            'receiver' => $this->whenLoaded('receiver', fn () => (new PublicUserResource($this->receiver))->resolve($request)),
            'event' => $this->whenLoaded('event', fn () => new EventSummaryResource($this->event)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
