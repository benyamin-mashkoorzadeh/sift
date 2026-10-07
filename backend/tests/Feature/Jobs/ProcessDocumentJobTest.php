<?php

namespace Tests\Feature\Jobs;

use App\Actions\Documents\ProcessDocument;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentExtractionException;
use App\Exceptions\Documents\DocumentProcessingException;
use App\Jobs\Documents\ProcessDocumentJob;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProcessDocumentJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_job_uses_the_documents_queue_and_one_attempt(): void
    {
        config()->set('documents.processing.queue', 'documents');
        config()->set('documents.processing.timeout', 300);

        $job = new ProcessDocumentJob(123);

        $this->assertSame('documents', $job->queue);
        $this->assertSame(1, $job->tries);
        $this->assertSame(300, $job->timeout);
        $this->assertTrue($job->failOnTimeout);
        $this->assertCount(1, $job->middleware());
    }

    public function test_the_job_delegates_to_the_provider_neutral_orchestrator(): void
    {
        $document = $this->createDocument();
        $processor = Mockery::mock(ProcessDocument::class);
        $processor->shouldReceive('handle')
            ->once()
            ->withArgs(fn (Document $candidate): bool => $candidate->is($document))
            ->andReturn($document);

        (new ProcessDocumentJob((int) $document->getKey()))->handle($processor);
    }

    public function test_a_job_for_a_deleted_document_exits_safely(): void
    {
        $processor = Mockery::mock(ProcessDocument::class);
        $processor->shouldNotReceive('handle');

        (new ProcessDocumentJob(999999))->handle($processor);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_terminal_worker_failure_does_not_leave_a_document_processing(): void
    {
        $document = $this->createDocument();

        (new ProcessDocumentJob((int) $document->getKey()))->failed(
            new \RuntimeException('Sensitive worker detail.'),
        );

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(DocumentProcessingException::USER_MESSAGE, $document->processing_error);
        $this->assertNull($document->processed_at);
    }

    public function test_a_terminal_worker_failure_preserves_a_stage_specific_safe_error(): void
    {
        $document = $this->createDocument();
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'processing_error' => DocumentExtractionException::USER_MESSAGE,
        ])->save();

        (new ProcessDocumentJob((int) $document->getKey()))->failed(
            new \RuntimeException('Sensitive worker detail.'),
        );

        $document->refresh();

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertSame(DocumentExtractionException::USER_MESSAGE, $document->processing_error);
    }

    private function createDocument(): Document
    {
        $workspace = Workspace::query()->create([
            'name' => 'Queued Processing Test',
            'slug' => 'queued-processing-test-'.str()->random(8),
        ]);

        return $workspace->documents()->create([
            'original_filename' => 'queued.pdf',
            'storage_disk' => 'documents',
            'storage_path' => "workspaces/{$workspace->id}/documents/queued.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Processing,
        ]);
    }
}
