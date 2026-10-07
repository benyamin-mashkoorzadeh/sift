<?php

namespace Tests\Feature\Actions;

use App\Actions\Documents\ChunkDocumentText;
use App\Contracts\Documents\TextChunker;
use App\Data\Documents\DocumentTextChunk;
use App\Data\Documents\ExtractedDocumentText;
use App\Data\Documents\ExtractedPageText;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentChunkingException;
use App\Models\Document;
use App\Models\Workspace;
use App\Services\Documents\PageAwareTextChunker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ChunkDocumentTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_chunker_contract_resolves_to_the_page_aware_implementation(): void
    {
        $this->assertInstanceOf(
            PageAwareTextChunker::class,
            $this->app->make(TextChunker::class),
        );
    }

    public function test_it_persists_page_aware_chunks_with_document_owned_workspace_and_null_embeddings(): void
    {
        config()->set('documents.chunking.size', 70);
        config()->set('documents.chunking.overlap', 12);
        $document = $this->createDocument();
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'processing_error' => 'Previous safe error.',
        ])->save();

        $chunks = $this->app->make(ChunkDocumentText::class)->handle(
            $document,
            new ExtractedDocumentText([
                new ExtractedPageText(1, str_repeat('Returns require a receipt. ', 8)),
                new ExtractedPageText(2, ''),
                new ExtractedPageText(3, 'Warranty exclusions apply.'),
            ]),
        );

        $document->refresh();

        $this->assertGreaterThan(1, $chunks->count());
        $this->assertSame(range(0, $chunks->count() - 1), $chunks->pluck('chunk_index')->all());
        $this->assertNotContains(2, $chunks->pluck('page_number')->all());
        $this->assertSame(3, $chunks->last()->page_number);
        $this->assertSame(DocumentStatus::Processing, $document->status);
        $this->assertNull($document->processing_error);
        $this->assertNull($document->processed_at);

        foreach ($chunks as $chunk) {
            $this->assertSame($document->workspace_id, $chunk->workspace_id);
            $this->assertSame($document->id, $chunk->document_id);
            $this->assertNull($chunk->embedding);
            $this->assertNull($chunk->embedding_provider);
            $this->assertNull($chunk->embedding_model);
            $this->assertNull($chunk->embedded_at);
            $this->assertSame('page-aware-v1', $chunk->metadata['chunking_strategy']);
        }
    }

    public function test_reprocessing_atomically_replaces_the_previous_chunk_set(): void
    {
        config()->set('documents.chunking.size', 1200);
        config()->set('documents.chunking.overlap', 200);
        $document = $this->createDocument();
        $oldChunk = $document->chunks()->create([
            'workspace_id' => $document->workspace_id,
            'content' => 'Old stale content.',
            'page_number' => 1,
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1024, 0.1),
            'embedding_provider' => 'old-provider',
            'embedding_model' => 'old-model',
            'embedded_at' => now(),
        ]);

        $chunks = $this->app->make(ChunkDocumentText::class)->handle(
            $document,
            new ExtractedDocumentText([
                new ExtractedPageText(1, 'Replacement content for the first physical page.'),
                new ExtractedPageText(2, 'Replacement content for the second physical page.'),
            ]),
        );

        $this->assertDatabaseMissing('document_chunks', ['id' => $oldChunk->id]);
        $this->assertCount(2, $chunks);
        $this->assertSame([0, 1], $chunks->pluck('chunk_index')->all());
        $this->assertSame([1, 2], $chunks->pluck('page_number')->all());
        $this->assertSame(
            ['Replacement content for the first physical page.', 'Replacement content for the second physical page.'],
            $chunks->pluck('content')->all(),
        );
        $this->assertTrue($chunks->every(static fn ($chunk): bool => $chunk->embedding === null));
    }

    public function test_a_failure_before_replacement_preserves_existing_chunks_and_marks_the_document_failed(): void
    {
        $document = $this->createDocument();
        $oldChunk = $document->chunks()->create([
            'workspace_id' => $document->workspace_id,
            'content' => 'Previously complete chunk.',
            'page_number' => 1,
            'chunk_index' => 0,
        ]);

        try {
            $this->app->make(ChunkDocumentText::class)->handle(
                $document,
                new ExtractedDocumentText([new ExtractedPageText(1, '')]),
            );
            $this->fail('Blank extracted text should not produce a persisted chunk set.');
        } catch (DocumentChunkingException $exception) {
            $this->assertSame(DocumentChunkingException::USER_MESSAGE, $exception->getMessage());
        }

        $document->refresh();

        $this->assertDatabaseHas('document_chunks', [
            'id' => $oldChunk->id,
            'content' => 'Previously complete chunk.',
        ]);
        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(DocumentChunkingException::USER_MESSAGE, $document->processing_error);
    }

    public function test_a_database_failure_rolls_back_the_entire_replacement(): void
    {
        $document = $this->createDocument();
        $oldChunk = $document->chunks()->create([
            'workspace_id' => $document->workspace_id,
            'content' => 'Keep this complete chunk set.',
            'page_number' => 1,
            'chunk_index' => 0,
        ]);
        $chunker = Mockery::mock(TextChunker::class);
        $chunker->shouldReceive('chunk')->once()->andReturn([
            new DocumentTextChunk(1, 0, 'Valid replacement row.'),
            (object) [
                'pageNumber' => 0,
                'pageChunkIndex' => 1,
                'content' => 'This row violates the positive page number constraint.',
            ],
        ]);
        $this->app->instance(TextChunker::class, $chunker);

        try {
            $this->app->make(ChunkDocumentText::class)->handle(
                $document,
                new ExtractedDocumentText([new ExtractedPageText(1, 'Replacement source text.')]),
            );
            $this->fail('A database error should fail chunk replacement.');
        } catch (DocumentChunkingException $exception) {
            $this->assertSame(DocumentChunkingException::USER_MESSAGE, $exception->getMessage());
        }

        $document->refresh();

        $this->assertDatabaseCount('document_chunks', 1);
        $this->assertDatabaseHas('document_chunks', [
            'id' => $oldChunk->id,
            'content' => 'Keep this complete chunk set.',
        ]);
        $this->assertSame(DocumentStatus::Failed, $document->status);
    }

    private function createDocument(): Document
    {
        $workspace = Workspace::query()->create([
            'name' => 'Chunking Test',
            'slug' => 'chunking-test-'.str()->random(8),
        ]);

        return $workspace->documents()->create([
            'original_filename' => 'support-policy.pdf',
            'storage_disk' => 'local',
            'storage_path' => "documents/{$workspace->id}/support-policy.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Processing,
        ]);
    }
}
