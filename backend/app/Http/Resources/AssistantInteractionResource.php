<?php

namespace App\Http\Resources;

use App\Models\AssistantInteractionCitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssistantInteractionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'origin' => $this->origin->value,
            'status' => $this->status->value,
            'question' => $this->question,
            'answer' => $this->answer,
            'citations' => $this->citations->map(
                static fn (AssistantInteractionCitation $citation): array => [
                    'document_id' => $citation->document_id,
                    'chunk_id' => $citation->document_chunk_id,
                    'original_filename' => $citation->original_filename,
                    'page_number' => $citation->page_number,
                    'chunk_index' => $citation->chunk_index,
                    'source_available' => $citation->document_id !== null,
                ],
            )->all(),
            'review' => $this->reviewItem === null ? null : [
                'id' => $this->reviewItem->id,
                'status' => $this->reviewItem->status->value,
                'resolution' => $this->reviewItem->resolution,
                'resolved_at' => $this->reviewItem->resolved_at?->toISOString(),
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
