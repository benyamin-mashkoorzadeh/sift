<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'question' => $this->question,
            'resolution' => $this->resolution,
            'created_at' => $this->created_at?->toISOString(),
            'last_asked_at' => $this->last_asked_at?->toISOString(),
            'resolved_at' => $this->resolved_at?->toISOString(),
        ];
    }
}
