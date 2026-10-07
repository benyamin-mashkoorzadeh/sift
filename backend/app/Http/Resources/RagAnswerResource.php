<?php

namespace App\Http\Resources;

use App\Data\AI\RagCitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RagAnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'answer' => $this->answer,
            'citations' => array_map(
                static fn (RagCitation $citation): array => [
                    'document_id' => $citation->documentId,
                    'original_filename' => $citation->originalFilename,
                    'page_number' => $citation->pageNumber,
                    'chunk_id' => $citation->chunkId,
                    'chunk_index' => $citation->chunkIndex,
                ],
                $this->citations,
            ),
        ];
    }
}
