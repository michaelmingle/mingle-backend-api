<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Compact event payload for embedding inside connections and requests. */
/** @mixin Event */
class EventSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'category' => $this->category,
            'location_text' => $this->location_text,
            'starts_at' => $this->starts_at?->toIso8601String(),
        ];
    }
}
