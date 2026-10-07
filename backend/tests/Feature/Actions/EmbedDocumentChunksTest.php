<?php

namespace Tests\Feature\Actions;

use App\Actions\Documents\EmbedDocumentChunks;
use App\Contracts\AI\EmbeddingProvider;
use App\Data\AI\EmbeddingOutput;
use App\Data\AI\EmbeddingRequest;
use App\Data\AI\EmbeddingResult;
use App\Enums\DocumentStatus;
use App\Enums\EmbeddingInputType;
use App\Exceptions\AI\EmbeddingProviderException;
use App\Exceptions\Documents\DocumentEmbeddingException;
use App\Exceptions\Documents\StaleDocumentChunks;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmbedDocumentChunksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_embeds_in_configured_batches_and_persists_the_complete_set(): void
    {
        config()->set('ai.embeddings.batch_size', 2);
        config()->set('ai.embeddings.dimensions', 1024);
        $document = $this->createDocument(chunkCount: 5);
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'processing_error' => 'Previous failure.',
        ])->save();
        $batchSizes = [];
        $inputTypes = [];
        $provider = Mockery::mock(EmbeddingProvider::class);
        $provider->shouldReceive('embed')->times(3)->andReturnUsing(
            function (EmbeddingRequest $request) use (&$batchSizes, &$inputTypes): EmbeddingResult {
                $batchSizes[] = count($request->inputs);
                $inputTypes[] = $request->inputType;

                $outputs = array_map(
                    static fn ($input): EmbeddingOutput => new EmbeddingOutput(
                        key: $input->key,
                        vector: array_fill(0, 1024, ((int) $input->key) / 1000),
                    ),
                    array_reverse($request->inputs),
                );

                return new EmbeddingResult('cohere', 'embed-v4.0', 1024, $outputs);
            },
        );
        $this->app->instance(EmbeddingProvider::class, $provider);

        $chunks = $this->app->make(EmbedDocumentChunks::class)->handle($document);
        $document->refresh();

        $this->assertSame([2, 2, 1], $batchSizes);
        $this->assertSame([
            EmbeddingInputType::Document,
            EmbeddingInputType::Document,
            EmbeddingInputType::Document,
        ], $inputTypes);
        $this->assertCount(5, $chunks);
        $this->assertSame(DocumentStatus::Processing, $document->status);
        $this->assertNull($document->processing_error);
        $this->assertNull($document->processed_at);
        $this->assertCount(1, $chunks->pluck('embedded_at')->map->toISOString()->unique());

        foreach ($chunks as $chunk) {
            $this->assertCount(1024, $chunk->embedding);
            $this->assertEqualsWithDelta($chunk->id / 1000, $chunk->embedding[0], 0.000001);
            $this->assertSame('cohere', $chunk->embedding_provider);
            $this->assertSame('embed-v4.0', $chunk->embedding_model);
            $this->assertNotNull($chunk->embedded_at);
        }
    }

    public function test_a_later_batch_failure_preserves_the_previous_complete_embedding_set(): void
    {
        config()->set('ai.embeddings.batch_size', 2);
        $document = $this->createDocument(chunkCount: 3, withExistingEmbeddings: true);
        $oldTimestamps = $document->chunks()->pluck('embedded_at', 'id');
        $provider = Mockery::mock(EmbeddingProvider::class);
        $provider->shouldReceive('embed')->once()->andReturnUsing(
            fn (EmbeddingRequest $request): EmbeddingResult => $this->resultFor($request, 0.75),
        );
        $provider->shouldReceive('embed')->once()->andThrow(
            new EmbeddingProviderException(EmbeddingProviderException::USER_MESSAGE),
        );
        $this->app->instance(EmbeddingProvider::class, $provider);

        try {
            $this->app->make(EmbedDocumentChunks::class)->handle($document);
            $this->fail('A failed later batch should stop embedding.');
        } catch (DocumentEmbeddingException $exception) {
            $this->assertSame(DocumentEmbeddingException::USER_MESSAGE, $exception->getMessage());
        }

        $document->refresh();
        $chunks = $document->chunks()->orderBy('chunk_index')->get();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(DocumentEmbeddingException::USER_MESSAGE, $document->processing_error);

        foreach ($chunks as $chunk) {
            $this->assertSame('previous-provider', $chunk->embedding_provider);
            $this->assertSame('previous-model', $chunk->embedding_model);
            $this->assertSame(0.25, $chunk->embedding[0]);
            $this->assertTrue($chunk->embedded_at->equalTo($oldTimestamps[$chunk->id]));
        }
    }

    public function test_a_database_failure_rolls_back_every_embedding_update(): void
    {
        $document = $this->createDocument(chunkCount: 3, withExistingEmbeddings: true);
        $provider = Mockery::mock(EmbeddingProvider::class);
        $provider->shouldReceive('embed')->once()->andReturnUsing(
            fn (EmbeddingRequest $request): EmbeddingResult => $this->resultFor($request, 0.75),
        );
        $this->app->instance(EmbeddingProvider::class, $provider);
        $updateCount = 0;

        DocumentChunk::updating(function () use (&$updateCount): void {
            $updateCount++;

            if ($updateCount === 2) {
                throw new RuntimeException('Simulated database write failure.');
            }
        });

        try {
            $this->app->make(EmbedDocumentChunks::class)->handle($document);
            $this->fail('A database write failure should roll back embedding persistence.');
        } catch (DocumentEmbeddingException $exception) {
            $this->assertSame(DocumentEmbeddingException::USER_MESSAGE, $exception->getMessage());
        }

        DocumentChunk::flushEventListeners();

        $document->refresh();

        foreach ($document->chunks()->get() as $chunk) {
            $this->assertSame('previous-provider', $chunk->embedding_provider);
            $this->assertSame('previous-model', $chunk->embedding_model);
            $this->assertSame(0.25, $chunk->embedding[0]);
        }

        $this->assertSame(DocumentStatus::Failed, $document->status);
    }

    public function test_stale_rechunked_content_is_not_updated_or_marked_failed(): void
    {
        $document = $this->createDocument(chunkCount: 2);
        $provider = Mockery::mock(EmbeddingProvider::class);
        $provider->shouldReceive('embed')->once()->andReturnUsing(
            function (EmbeddingRequest $request) use ($document): EmbeddingResult {
                $result = $this->resultFor($request, 0.5);

                $document->chunks()->delete();
                $this->createChunk($document, 0, 'Newly re-chunked content.');

                return $result;
            },
        );
        $this->app->instance(EmbeddingProvider::class, $provider);

        $this->expectException(StaleDocumentChunks::class);

        try {
            $this->app->make(EmbedDocumentChunks::class)->handle($document);
        } finally {
            $document->refresh();
            $chunk = $document->chunks()->sole();

            $this->assertSame(DocumentStatus::Processing, $document->status);
            $this->assertNull($document->processing_error);
            $this->assertSame('Newly re-chunked content.', $chunk->content);
            $this->assertNull($chunk->embedding);
        }
    }

    private function createDocument(int $chunkCount, bool $withExistingEmbeddings = false): Document
    {
        $workspace = Workspace::query()->create([
            'name' => 'Embedding Test',
            'slug' => 'embedding-test-'.str()->random(8),
        ]);
        $document = $workspace->documents()->create([
            'original_filename' => 'knowledge.pdf',
            'storage_disk' => 'local',
            'storage_path' => "documents/{$workspace->id}/knowledge.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Processing,
        ]);

        for ($index = 0; $index < $chunkCount; $index++) {
            $this->createChunk(
                document: $document,
                chunkIndex: $index,
                content: "Knowledge chunk {$index}.",
                withExistingEmbedding: $withExistingEmbeddings,
            );
        }

        return $document;
    }

    private function createChunk(
        Document $document,
        int $chunkIndex,
        string $content,
        bool $withExistingEmbedding = false,
    ): DocumentChunk {
        return $document->chunks()->create([
            'workspace_id' => $document->workspace_id,
            'content' => $content,
            'page_number' => $chunkIndex + 1,
            'chunk_index' => $chunkIndex,
            'embedding' => $withExistingEmbedding ? array_fill(0, 1024, 0.25) : null,
            'embedding_provider' => $withExistingEmbedding ? 'previous-provider' : null,
            'embedding_model' => $withExistingEmbedding ? 'previous-model' : null,
            'embedded_at' => $withExistingEmbedding ? now()->subDay() : null,
        ]);
    }

    private function resultFor(EmbeddingRequest $request, float $value): EmbeddingResult
    {
        return new EmbeddingResult(
            provider: 'cohere',
            model: 'embed-v4.0',
            dimensions: 1024,
            outputs: array_map(
                static fn ($input): EmbeddingOutput => new EmbeddingOutput(
                    $input->key,
                    array_fill(0, 1024, $value),
                ),
                $request->inputs,
            ),
        );
    }
}
