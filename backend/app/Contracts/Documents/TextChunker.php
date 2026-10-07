<?php

namespace App\Contracts\Documents;

use App\Data\Documents\DocumentTextChunk;
use App\Data\Documents\ExtractedDocumentText;

interface TextChunker
{
    /**
     * @return list<DocumentTextChunk>
     */
    public function chunk(
        ExtractedDocumentText $documentText,
        int $chunkSize,
        int $chunkOverlap,
    ): array;
}
