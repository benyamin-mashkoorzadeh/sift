<?php

namespace Tests\Feature\Actions;

use App\Actions\Documents\ChunkDocumentText;
use App\Actions\Documents\EmbedDocumentChunks;
use App\Actions\Documents\ExtractDocumentText;
use App\Actions\Documents\ProcessDocument;
use App\Contracts\AI\EmbeddingProvider;
use App\Contracts\Documents\PdfTextExtractor;
use App\Data\AI\EmbeddingOutput;
use App\Data\AI\EmbeddingRequest;
use App\Data\AI\EmbeddingResult;
use App\Data\Documents\ExtractedDocumentText;
use App\Data\Documents\ExtractedPageText;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentExtractionException;
use App\Exceptions\Documents\DocumentProcessingException;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProcessDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_runs_the_existing_pipeline_and_marks_the_document_ready(): void
    {
        Storage::fake('documents');
        config()->set('documents.minimum_extracted_characters', 10);
        config()->set('documents.chunking.size', 1200);
        config()->set('documents.chunking.overlap', 200);
        config()->set('ai.embeddings.dimensions', 1024);
        config()->set('ai.embeddings.batch_size', 64);

        $document = $this->createDocument();
        Storage::disk('documents')->put($document->storage_path, '%PDF test contents');

        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldReceive('extract')->once()->andReturn(new ExtractedDocumentText([
            new ExtractedPageText(1, 'Returns are accepted within thirty days with a valid receipt.'),
            new ExtractedPageText(2, ''),
            new ExtractedPageText(3, 'Warranty claims require the original order number.'),
        ]));
        $this->app->instance(PdfTextExtractor::class, $extractor);

        $provider = Mockery::mock(EmbeddingProvider::class);
        $provider->shouldReceive('embed')->once()->andReturnUsing(
            static fn (EmbeddingRequest $request): EmbeddingResult => new EmbeddingResult(
                provider: 'cohere',
                model: 'embed-v4.0',
                dimensions: 1024,
                outputs: array_map(
                    static fn ($input): EmbeddingOutput => new EmbeddingOutput(
                        key: $input->key,
                        vector: array_fill(0, 1024, 0.25),
                    ),
                    $request->inputs,
                ),
            ),
        );
        $this->app->instance(EmbeddingProvider::class, $provider);

        $processed = $this->app->make(ProcessDocument::class)->handle($document);
        $chunks = $processed->chunks()->orderBy('chunk_index')->get();

        $this->assertSame(DocumentStatus::Ready, $processed->status);
        $this->assertSame(3, $processed->page_count);
        $this->assertNull($processed->processing_error);
        $this->assertNotNull($processed->processed_at);
        $this->assertCount(2, $chunks);
        $this->assertSame([1, 3], $chunks->pluck('page_number')->all());
        $this->assertTrue($chunks->every(
            static fn (DocumentChunk $chunk): bool => count($chunk->embedding ?? []) === 1024
                && $chunk->embedding_provider === 'cohere'
                && $chunk->embedding_model === 'embed-v4.0'
                && $chunk->embedded_at !== null,
        ));
    }

    public function test_a_stage_failure_stops_the_pipeline_and_stores_only_a_safe_error(): void
    {
        $document = $this->createDocument();
        $extract = Mockery::mock(ExtractDocumentText::class);
        $chunk = Mockery::mock(ChunkDocumentText::class);
        $embed = Mockery::mock(EmbedDocumentChunks::class);

        $extract->shouldReceive('handle')
            ->once()
            ->withArgs(fn (Document $candidate): bool => $candidate->is($document))
            ->andThrow(new DocumentExtractionException('Sensitive parser detail.'));
        $chunk->shouldNotReceive('handle');
        $embed->shouldNotReceive('handle');

        try {
            (new ProcessDocument($extract, $chunk, $embed))->handle($document);
            $this->fail('The extraction failure should stop document processing.');
        } catch (DocumentExtractionException $exception) {
            $this->assertSame('Sensitive parser detail.', $exception->getMessage());
        }

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(DocumentExtractionException::USER_MESSAGE, $document->processing_error);
        $this->assertStringNotContainsString('Sensitive parser detail', $document->processing_error);
        $this->assertNull($document->processed_at);
    }

    public function test_it_does_not_mark_changed_or_incomplete_chunks_ready(): void
    {
        $document = $this->createDocument();
        $oldChunk = $this->createEmbeddedChunk($document, 'Previously embedded content.');
        $extract = Mockery::mock(ExtractDocumentText::class);
        $chunk = Mockery::mock(ChunkDocumentText::class);
        $embed = Mockery::mock(EmbedDocumentChunks::class);
        $documentText = new ExtractedDocumentText([
            new ExtractedPageText(1, 'Replacement content.'),
        ]);

        $extract->shouldReceive('handle')->once()->andReturn($documentText);
        $chunk->shouldReceive('handle')->once()->with($document, $documentText)->andReturn(new Collection([$oldChunk]));
        $embed->shouldReceive('handle')->once()->andReturnUsing(function () use ($document, $oldChunk): Collection {
            $embedded = new Collection([$oldChunk->fresh()]);

            $document->chunks()->delete();
            $document->chunks()->create([
                'workspace_id' => $document->workspace_id,
                'content' => 'New content that has not been embedded.',
                'page_number' => 1,
                'chunk_index' => 0,
            ]);

            return $embedded;
        });

        $this->expectException(DocumentProcessingException::class);

        try {
            (new ProcessDocument($extract, $chunk, $embed))->handle($document);
        } finally {
            $document->refresh();

            $this->assertSame(DocumentStatus::Failed, $document->status);
            $this->assertSame(DocumentProcessingException::USER_MESSAGE, $document->processing_error);
            $this->assertNull($document->processed_at);
            $this->assertNull($document->chunks()->sole()->embedding);
        }
    }

    public function test_an_already_ready_document_is_not_processed_again(): void
    {
        $document = $this->createDocument();
        $document->forceFill([
            'status' => DocumentStatus::Ready,
            'processed_at' => now(),
        ])->save();
        $extract = Mockery::mock(ExtractDocumentText::class);
        $chunk = Mockery::mock(ChunkDocumentText::class);
        $embed = Mockery::mock(EmbedDocumentChunks::class);
        $extract->shouldNotReceive('handle');
        $chunk->shouldNotReceive('handle');
        $embed->shouldNotReceive('handle');

        $processed = (new ProcessDocument($extract, $chunk, $embed))->handle($document);

        $this->assertSame(DocumentStatus::Ready, $processed->status);
        $this->assertNotNull($processed->processed_at);
    }

    private function createDocument(): Document
    {
        $workspace = Workspace::query()->create([
            'name' => 'Processing Test',
            'slug' => 'processing-test-'.str()->random(8),
        ]);

        return $workspace->documents()->create([
            'original_filename' => 'support.pdf',
            'storage_disk' => 'documents',
            'storage_path' => "workspaces/{$workspace->id}/documents/support.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Processing,
        ]);
    }

    private function createEmbeddedChunk(Document $document, string $content): DocumentChunk
    {
        return $document->chunks()->create([
            'workspace_id' => $document->workspace_id,
            'content' => $content,
            'page_number' => 1,
            'chunk_index' => 0,
            'embedding' => array_fill(0, 1024, 0.25),
            'embedding_provider' => 'cohere',
            'embedding_model' => 'embed-v4.0',
            'embedded_at' => now(),
        ]);
    }
}
