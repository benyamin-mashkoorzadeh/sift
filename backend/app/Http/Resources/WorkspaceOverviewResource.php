<?php

namespace App\Http\Resources;

use App\Models\AssistantInteraction;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceOverviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $overview */
        $overview = $this->resource;
        /** @var Workspace $workspace */
        $workspace = $overview['workspace'];

        return [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'documents' => $overview['documents'],
            'interactions' => $overview['interactions'],
            'reviews' => $overview['reviews'],
            'recent_documents' => $overview['recent_documents']->map(
                static fn (Document $document): array => [
                    'id' => $document->id,
                    'original_filename' => $document->original_filename,
                    'status' => $document->status->value,
                    'page_count' => $document->page_count,
                    'size_bytes' => $document->size_bytes,
                    'created_at' => $document->created_at?->toISOString(),
                ],
            )->all(),
            'recent_interactions' => $overview['recent_interactions']->map(
                static fn (AssistantInteraction $interaction): array => [
                    'id' => $interaction->id,
                    'question' => $interaction->question,
                    'status' => $interaction->status->value,
                    'created_at' => $interaction->created_at?->toISOString(),
                ],
            )->all(),
        ];
    }
}
