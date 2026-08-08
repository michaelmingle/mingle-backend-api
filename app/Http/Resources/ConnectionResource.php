<?php

namespace App\Http\Resources;

use App\Models\Connection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A connection is always rendered from the current viewer's perspective: the
 * `user` key is the *other* participant, and `note` is the viewer's own private
 * note (never the counterpart's).
 *
 * @mixin \App\Models\Connection
 */
class ConnectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Connection $connection */
        $connection = $this->resource;
        $viewer = $request->user();
        $viewerId = $viewer?->id ?? 0;

        $other = $connection->otherUser($viewerId);

        $note = $connection->relationLoaded('notes')
            ? $connection->notes->firstWhere('owner_id', $viewerId)
            : $connection->noteFor($viewerId);

        $isFavorite = $connection->relationLoaded('favorites')
            ? $connection->favorites->contains('user_id', $viewerId)
            : $connection->isFavoritedBy($viewerId);

        return [
            'id' => $connection->id,
            'user' => $other
                ? (new PublicUserResource($other))->connected(true)->toArray($request)
                : null,
            'is_favorite' => $isFavorite,
            'note' => $note?->note,
            'event' => $connection->relationLoaded('event') && $connection->event
                ? new EventSummaryResource($connection->event)
                : null,
            'met_at_event' => $connection->event_id !== null,
            'connected_at' => $connection->connected_at?->toIso8601String(),
            'created_at' => $connection->created_at?->toIso8601String(),
        ];
    }
}
