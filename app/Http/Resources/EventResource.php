<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Event */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'banner_url' => $this->banner_url,
            'location_text' => $this->location_text,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'category' => $this->category,
            'status' => $this->status?->value,
            'attendee_count' => $this->attendee_count,
            'is_organizer' => $viewer !== null && $viewer->id === $this->organizer_id,
            'organizer' => $this->whenLoaded('organizer', fn () => [
                'id' => $this->organizer?->id,
                'name' => $this->organizer?->name,
                'is_verified' => (bool) $this->organizer?->is_verified,
            ]),
            'attendance' => $this->when(
                $this->relationLoaded('eventAttendees'),
                fn () => $this->viewerAttendance($viewer?->id)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return array{is_attending: bool, is_networking_enabled: bool|null}|null */
    private function viewerAttendance(?int $viewerId): ?array
    {
        if ($viewerId === null) {
            return null;
        }

        $attendance = $this->eventAttendees->firstWhere('user_id', $viewerId);

        return [
            'is_attending' => $attendance !== null,
            'is_networking_enabled' => $attendance?->is_networking_enabled,
        ];
    }
}
