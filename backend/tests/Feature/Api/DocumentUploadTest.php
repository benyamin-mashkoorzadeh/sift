<?php

namespace Tests\Feature\Api;

use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentProcessingException;
use App\Jobs\Documents\ProcessDocumentJob;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_a_pdf_can_be_uploaded_to_a_workspace(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');

        $workspace = $this->createWorkspace('upload-success');
        $file = UploadedFile::fake()->create('../../Return Policy.pdf', 64, 'application/pdf');

        $response = $this->postJson(route('workspaces.documents.store', $workspace), [
            'file' => $file,
        ]);

        $document = Document::query()->sole();

        $response
            ->assertCreated()
            ->assertExactJson([
                'data' => [
                    'id' => $document->id,
                    'workspace_id' => $workspace->id,
                    'original_filename' => 'Return Policy.pdf',
                    'mime_type' => 'application/pdf',
                    'size_bytes' => 64 * 1024,
                    'status' => DocumentStatus::Processing->value,
                    'page_count' => null,
                    'processing_error' => null,
                    'created_at' => $document->created_at->toISOString(),
                ],
            ]);

        $this->assertTrue($document->workspace->is($workspace));
        $this->assertSame(DocumentStatus::Processing, $document->status);
        $this->assertSame('documents', $document->storage_disk);
        $this->assertStringStartsWith("workspaces/{$workspace->id}/documents/", $document->storage_path);
        $this->assertStringNotContainsString('Return Policy', $document->storage_path);
        Storage::disk('documents')->assertExists($document->storage_path);
        Queue::assertPushed(
            ProcessDocumentJob::class,
            fn (ProcessDocumentJob $job): bool => $job->documentId === $document->id
                && $job->queue === 'documents',
        );
    }

    public function test_a_file_is_required(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');

        $workspace = $this->createWorkspace('missing-file');

        $this->postJson(route('workspaces.documents.store', $workspace))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_non_pdf_files_are_rejected(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');

        $workspace = $this->createWorkspace('invalid-type');
        $file = UploadedFile::fake()->create('notes.txt', 1, 'text/plain');

        $this->postJson(route('workspaces.documents.store', $workspace), ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_files_over_the_configured_limit_are_rejected(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        config()->set('documents.max_upload_kb', 100);

        $workspace = $this->createWorkspace('oversized-file');
        $file = UploadedFile::fake()->create('large.pdf', 101, 'application/pdf');

        $this->postJson(route('workspaces.documents.store', $workspace), ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_an_unknown_workspace_returns_not_found(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        $this->createWorkspace('known');

        $file = UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf');

        $this->postJson('/api/workspaces/999999/documents', ['file' => $file])
            ->assertNotFound();

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_a_storage_failure_does_not_create_a_document(): void
    {
        config()->set('documents.disk', 'documents');

        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFile')->once()->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('documents')->andReturn($disk);

        $workspace = $this->createWorkspace('storage-failure');
        $file = UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf');

        $this->postJson(route('workspaces.documents.store', $workspace), ['file' => $file])
            ->assertInternalServerError();

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_a_database_failure_removes_the_stored_file(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');

        $workspace = $this->createWorkspace('database-failure');
        $file = UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf');

        Document::creating(static function (): never {
            throw new RuntimeException('Simulated database failure.');
        });

        try {
            $this->postJson(route('workspaces.documents.store', $workspace), ['file' => $file])
                ->assertInternalServerError();
        } finally {
            Document::flushEventListeners();
        }

        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_a_queue_dispatch_failure_marks_the_document_failed_safely(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');

        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('dispatch')
            ->once()
            ->andThrow(new RuntimeException('Sensitive queue connection detail.'));
        $this->app->instance(Dispatcher::class, $dispatcher);

        $workspace = $this->createWorkspace('dispatch-failure');
        $file = UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf');

        $response = $this->postJson(route('workspaces.documents.store', $workspace), [
            'file' => $file,
        ]);

        $document = Document::query()->sole();

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', DocumentStatus::Failed->value)
            ->assertJsonPath('data.processing_error', DocumentProcessingException::DISPATCH_USER_MESSAGE)
            ->assertJsonMissing(['Sensitive queue connection detail.']);

        $this->assertSame(DocumentStatus::Failed, $document->status);
        $this->assertNull($document->processed_at);
        Storage::disk('documents')->assertExists($document->storage_path);
    }

    private function createWorkspace(string $slug): Workspace
    {
        $workspace = Workspace::query()->create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
        ]);

        if (! $this->app['auth']->check()) {
            $this->actingAsWorkspaceMember($workspace);
        }

        return $workspace;
    }
}
