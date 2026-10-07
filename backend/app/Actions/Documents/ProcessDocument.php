<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentChunkingException;
use App\Exceptions\Documents\DocumentEmbeddingException;
use App\Exceptions\Documents\DocumentExtractionException;
use App\Exceptions\Documents\DocumentProcessingException;
use App\Exceptions\Documents\InsufficientExtractableText;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessDocument
{
    public function __construct(
        private readonly ExtractDocumentText $extractDocumentText,
        private readonly ChunkDocumentText $chunkDocumentText,
        private readonly EmbedDocumentChunks $embedDocumentChunks,
    ) {}

    public function handle(Document $document): Document
    {
        if ($this->beginProcessing($document) === false) {
            return $document->refresh();
        }

        try {
            $documentText = $this->extractDocumentText->handle($document);
            $this->chunkDocumentText->handle($document, $documentText);
            $embeddedChunks = $this->embedDocumentChunks->handle($document);

            $this->markReady($document, $embeddedChunks);

            return $document->refresh();
        } catch (Throwable $exception) {
            $this->markFailed($document, $this->safeMessage($exception));

            if (! $this->isKnownProcessingFailure($exception)) {
                report($exception);
            }

            throw $exception;
        }
    }

    private function beginProcessing(Document $document): bool
    {
        return DB::transaction(function () use ($document): bool {
            $lockedDocument = Document::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            if ($lockedDocument->status === DocumentStatus::Ready) {
                return false;
            }

            $lockedDocument->forceFill([
                'status' => DocumentStatus::Processing,
                'processing_error' => null,
                'processed_at' => null,
            ])->save();

            return true;
        });
    }

    /**
     * @param  Collection<int, DocumentChunk>  $embeddedChunks
     */
    private function markReady(Document $document, Collection $embeddedChunks): void
    {
        $expectedState = $this->chunkState($embeddedChunks);

        DB::transaction(function () use ($document, $expectedState): void {
            $lockedDocument = Document::query()
                ->lockForUpdate()
                ->findOrFail($document->getKey());

            $currentChunks = $lockedDocument->chunks()
                ->lockForUpdate()
                ->orderBy('chunk_index')
                ->orderBy('id')
                ->get();

            if ($expectedState === []
                || $expectedState !== $this->chunkState($currentChunks)
                || $currentChunks->contains(fn (DocumentChunk $chunk): bool => ! $this->isFullyEmbedded($chunk))) {
                throw new DocumentProcessingException(DocumentProcessingException::USER_MESSAGE);
            }

            $lockedDocument->forceFill([
                'status' => DocumentStatus::Ready,
                'processing_error' => null,
                'processed_at' => now(),
            ])->save();
        });
    }

    /**
     * @param  Collection<int, DocumentChunk>  $chunks
     * @return list<array{id: int, chunk_index: int, content_hash: string, updated_at: ?string}>
     */
    private function chunkState(Collection $chunks): array
    {
        return $chunks->map(static fn (DocumentChunk $chunk): array => [
            'id' => (int) $chunk->getKey(),
            'chunk_index' => $chunk->chunk_index,
            'content_hash' => hash('sha256', $chunk->content),
            'updated_at' => $chunk->updated_at?->format('Y-m-d H:i:s.uP'),
        ])->values()->all();
    }

    private function isFullyEmbedded(DocumentChunk $chunk): bool
    {
        return is_array($chunk->embedding)
            && $chunk->embedding !== []
            && is_string($chunk->embedding_provider)
            && $chunk->embedding_provider !== ''
            && is_string($chunk->embedding_model)
            && $chunk->embedding_model !== ''
            && $chunk->embedded_at !== null;
    }

    private function markFailed(Document $document, string $safeError): void
    {
        $document->newQuery()
            ->whereKey($document->getKey())
            ->where('status', '!=', DocumentStatus::Ready->value)
            ->update([
                'status' => DocumentStatus::Failed->value,
                'processing_error' => $safeError,
                'processed_at' => null,
                'updated_at' => now(),
            ]);
    }

    private function safeMessage(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof InsufficientExtractableText => InsufficientExtractableText::USER_MESSAGE,
            $exception instanceof DocumentExtractionException => DocumentExtractionException::USER_MESSAGE,
            $exception instanceof DocumentChunkingException => DocumentChunkingException::USER_MESSAGE,
            $exception instanceof DocumentEmbeddingException => DocumentEmbeddingException::USER_MESSAGE,
            default => DocumentProcessingException::USER_MESSAGE,
        };
    }

    private function isKnownProcessingFailure(Throwable $exception): bool
    {
        return $exception instanceof DocumentExtractionException
            || $exception instanceof DocumentChunkingException
            || $exception instanceof DocumentEmbeddingException
            || $exception instanceof DocumentProcessingException;
    }
}
