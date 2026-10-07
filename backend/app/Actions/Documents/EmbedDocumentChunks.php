<?php

namespace App\Actions\Documents;

use App\Contracts\AI\EmbeddingProvider;
use App\Data\AI\EmbeddingInput;
use App\Data\AI\EmbeddingRequest;
use App\Data\AI\EmbeddingResult;
use App\Enums\DocumentStatus;
use App\Enums\EmbeddingInputType;
use App\Exceptions\Documents\DocumentEmbeddingException;
use App\Exceptions\Documents\StaleDocumentChunks;
use App\Models\Document;
use App\Models\DocumentChunk;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class EmbedDocumentChunks
{
    public function __construct(private readonly EmbeddingProvider $provider) {}

    /**
     * @return Collection<int, DocumentChunk>
     */
    public function handle(Document $document): Collection
    {
        try {
            $chunks = $document->chunks()
                ->orderBy('chunk_index')
                ->orderBy('id')
                ->get();

            if ($chunks->isEmpty()) {
                throw new DocumentEmbeddingException(DocumentEmbeddingException::USER_MESSAGE);
            }

            $batchSize = (int) config('ai.embeddings.batch_size');
            $expectedDimensions = (int) config('ai.embeddings.dimensions');

            if ($batchSize < 1 || $expectedDimensions < 1) {
                throw new DocumentEmbeddingException(DocumentEmbeddingException::USER_MESSAGE);
            }

            $manifest = $this->manifest($chunks);
            $vectorsByChunkId = [];
            $providerName = null;
            $model = null;

            foreach ($chunks->chunk($batchSize) as $batch) {
                $inputs = $batch->map(
                    static fn (DocumentChunk $chunk): EmbeddingInput => new EmbeddingInput(
                        key: (string) $chunk->getKey(),
                        text: $chunk->content,
                    ),
                )->values()->all();

                $result = $this->provider->embed(new EmbeddingRequest(
                    inputs: $inputs,
                    inputType: EmbeddingInputType::Document,
                ));

                $this->validateResult(
                    result: $result,
                    inputs: $inputs,
                    expectedDimensions: $expectedDimensions,
                    expectedProvider: $providerName,
                    expectedModel: $model,
                );

                $providerName ??= $result->provider;
                $model ??= $result->model;

                foreach ($result->outputs as $output) {
                    if (array_key_exists($output->key, $vectorsByChunkId)) {
                        throw new DocumentEmbeddingException(DocumentEmbeddingException::USER_MESSAGE);
                    }

                    $vectorsByChunkId[$output->key] = $output->vector;
                }
            }

            if (count($vectorsByChunkId) !== $chunks->count() || $providerName === null || $model === null) {
                throw new DocumentEmbeddingException(DocumentEmbeddingException::USER_MESSAGE);
            }

            $embeddedAt = now();

            DB::transaction(function () use (
                $document,
                $manifest,
                $vectorsByChunkId,
                $providerName,
                $model,
                $embeddedAt,
            ): void {
                $lockedDocument = Document::query()
                    ->lockForUpdate()
                    ->findOrFail($document->getKey());

                $currentChunks = $lockedDocument->chunks()
                    ->lockForUpdate()
                    ->orderBy('chunk_index')
                    ->orderBy('id')
                    ->get();

                if (! hash_equals($manifest, $this->manifest($currentChunks))) {
                    throw new StaleDocumentChunks(StaleDocumentChunks::USER_MESSAGE);
                }

                foreach ($currentChunks as $chunk) {
                    $vector = $vectorsByChunkId[(string) $chunk->getKey()] ?? null;

                    if ($vector === null) {
                        throw new DocumentEmbeddingException(DocumentEmbeddingException::USER_MESSAGE);
                    }

                    $chunk->forceFill([
                        'embedding' => $vector,
                        'embedding_provider' => $providerName,
                        'embedding_model' => $model,
                        'embedded_at' => $embeddedAt,
                    ])->save();
                }

                $lockedDocument->forceFill([
                    'status' => DocumentStatus::Processing,
                    'processing_error' => null,
                    'processed_at' => null,
                ])->save();
            });

            $document->refresh();

            return $document->chunks()->orderBy('chunk_index')->get();
        } catch (StaleDocumentChunks $exception) {
            report($exception);

            throw $exception;
        } catch (Throwable $exception) {
            $failure = $exception instanceof DocumentEmbeddingException
                ? $exception
                : new DocumentEmbeddingException(
                    DocumentEmbeddingException::USER_MESSAGE,
                    previous: $exception,
                );

            $this->markFailed($document);
            report($exception);

            throw $failure;
        }
    }

    /**
     * @param  list<EmbeddingInput>  $inputs
     */
    private function validateResult(
        EmbeddingResult $result,
        array $inputs,
        int $expectedDimensions,
        ?string $expectedProvider,
        ?string $expectedModel,
    ): void {
        if ($result->dimensions !== $expectedDimensions
            || ($expectedProvider !== null && $result->provider !== $expectedProvider)
            || ($expectedModel !== null && $result->model !== $expectedModel)) {
            throw new DocumentEmbeddingException(DocumentEmbeddingException::USER_MESSAGE);
        }

        $expectedKeys = array_map(
            static fn (EmbeddingInput $input): string => $input->key,
            $inputs,
        );
        $actualKeys = array_map(
            static fn ($output): string => $output->key,
            $result->outputs,
        );

        sort($expectedKeys);
        sort($actualKeys);

        if ($expectedKeys !== $actualKeys) {
            throw new DocumentEmbeddingException(DocumentEmbeddingException::USER_MESSAGE);
        }
    }

    /**
     * @param  Collection<int, DocumentChunk>  $chunks
     */
    private function manifest(Collection $chunks): string
    {
        $content = $chunks->map(static function (DocumentChunk $chunk): array {
            return [
                'id' => $chunk->getKey(),
                'chunk_index' => $chunk->chunk_index,
                'content_hash' => hash('sha256', $chunk->content),
                'updated_at' => $chunk->updated_at?->format('Y-m-d H:i:s.uP'),
            ];
        })->values()->all();

        return hash('sha256', json_encode($content, JSON_THROW_ON_ERROR));
    }

    private function markFailed(Document $document): void
    {
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'processing_error' => DocumentEmbeddingException::USER_MESSAGE,
            'processed_at' => null,
        ])->save();
    }
}
