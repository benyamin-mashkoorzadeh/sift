<?php

namespace App\Actions\Documents;

use App\Contracts\Documents\TextChunker;
use App\Data\Documents\ExtractedDocumentText;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentChunkingException;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ChunkDocumentText
{
    public function __construct(private readonly TextChunker $chunker) {}

    /**
     * @return Collection<int, DocumentChunk>
     */
    public function handle(Document $document, ExtractedDocumentText $documentText): Collection
    {
        try {
            $chunks = $this->chunker->chunk(
                documentText: $documentText,
                chunkSize: (int) config('documents.chunking.size'),
                chunkOverlap: (int) config('documents.chunking.overlap'),
            );

            if ($chunks === []) {
                throw new DocumentChunkingException(DocumentChunkingException::USER_MESSAGE);
            }

            DB::transaction(function () use ($document, $chunks): void {
                $lockedDocument = Document::query()
                    ->lockForUpdate()
                    ->findOrFail($document->getKey());

                $lockedDocument->chunks()->delete();

                $timestamp = now();
                $rows = [];

                foreach ($chunks as $chunkIndex => $chunk) {
                    $rows[] = [
                        'workspace_id' => $lockedDocument->workspace_id,
                        'document_id' => $lockedDocument->getKey(),
                        'content' => $chunk->content,
                        'page_number' => $chunk->pageNumber,
                        'chunk_index' => $chunkIndex,
                        'embedding' => null,
                        'embedding_provider' => null,
                        'embedding_model' => null,
                        'embedded_at' => null,
                        'metadata' => json_encode([
                            'page_chunk_index' => $chunk->pageChunkIndex,
                            'chunking_strategy' => 'page-aware-v1',
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }

                DocumentChunk::query()->insert($rows);

                $lockedDocument->forceFill([
                    'status' => DocumentStatus::Processing,
                    'processing_error' => null,
                    'processed_at' => null,
                ])->save();
            });

            $document->refresh();

            return $document->chunks()->orderBy('chunk_index')->get();
        } catch (Throwable $exception) {
            $failure = $exception instanceof DocumentChunkingException
                ? $exception
                : new DocumentChunkingException(
                    DocumentChunkingException::USER_MESSAGE,
                    previous: $exception,
                );

            $this->markFailed($document);
            report($exception);

            throw $failure;
        }
    }

    private function markFailed(Document $document): void
    {
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'processing_error' => DocumentChunkingException::USER_MESSAGE,
            'processed_at' => null,
        ])->save();
    }
}
