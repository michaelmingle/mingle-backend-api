<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Report */
class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'details' => $this->details,
            'status' => $this->status?->value,
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
            ]),
            'reported_user' => $this->whenLoaded('reportedUser', fn () => $this->reportedUser ? [
                'id' => $this->reportedUser->id,
                'name' => $this->reportedUser->name,
                'status' => $this->reportedUser->status?->value,
            ] : null),
            'reported_event' => $this->whenLoaded('reportedEvent', fn () => $this->reportedEvent
                ? new EventSummaryResource($this->reportedEvent)
                : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
