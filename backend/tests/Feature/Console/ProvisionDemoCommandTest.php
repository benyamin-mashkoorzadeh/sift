<?php

namespace Tests\Feature\Console;

use App\Actions\Overview\GetWorkspaceOverview;
use App\Contracts\AI\EmbeddingProvider;
use App\Contracts\AI\LlmProvider;
use App\Contracts\Documents\PdfTextExtractor;
use App\Data\AI\EmbeddingOutput;
use App\Data\AI\EmbeddingRequest;
use App\Data\AI\EmbeddingResult;
use App\Data\Documents\ExtractedDocumentText;
use App\Data\Documents\ExtractedPageText;
use App\Enums\DocumentStatus;
use App\Enums\ReviewItemStatus;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ProvisionDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_provisions_the_complete_dataset_idempotently_without_changing_unrelated_data(): void
    {
        $this->configureFakePipeline();

        $unrelatedUser = User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'StrongPassword123',
        ]);
        $unrelatedWorkspace = Workspace::query()->create([
            'name' => 'Sift Demo',
            'slug' => 'existing-sift-demo',
        ]);
        $unrelatedWorkspace->users()->attach($unrelatedUser, ['role' => WorkspaceRole::Owner->value]);

        $this->artisan('sift:provision-demo')
            ->expectsOutputToContain('Guest Demo provisioning completed successfully.')
            ->expectsOutputToContain('DEMO_USER_ID=')
            ->expectsOutputToContain('DEMO_WORKSPACE_ID=')
            ->assertSuccessful();

        $workspace = Workspace::query()->where('slug', 'lumenfield-demo')->sole();
        $guest = User::query()->where('email', 'demo-guest@sift.internal')->sole();

        $this->assertSame('Lumenfield Supply', $workspace->name);
        $this->assertSame('Guest Demo', $guest->name);
        $this->assertCount(1, $guest->workspaces);
        $this->assertTrue($guest->workspaces->first()->is($workspace));
        $this->assertSame(WorkspaceRole::Member, $guest->workspaces->first()->pivot->role);
        $this->assertSame(3, $workspace->documents()->count());
        $this->assertSame(3, $workspace->documents()->where('status', DocumentStatus::Ready)->count());
        $this->assertSame(6, $workspace->assistantInteractions()->count());
        $this->assertSame(2, $workspace->reviewItems()->count());
        $this->assertSame(1, $workspace->reviewItems()->where('status', ReviewItemStatus::Pending)->count());
        $this->assertFalse($workspace->widget()->exists());

        $this->assertSame(4, $workspace->assistantInteractions()->where('status', 'answered')->count());
        $this->assertSame(2, $workspace->assistantInteractions()->where('status', 'needs_review')->count());
        $this->assertSame(4, $workspace->assistantInteractions()->withCount('citations')->get()->sum('citations_count'));

        foreach ($workspace->assistantInteractions()->where('status', 'answered')->with('citations')->get() as $interaction) {
            $citation = $interaction->citations->sole();

            $this->assertSame($workspace->id, $citation->document->workspace_id);
            $this->assertSame($workspace->id, $citation->documentChunk->workspace_id);
            $this->assertSame($citation->document_id, $citation->documentChunk->document_id);
            $this->assertSame($citation->page_number, $citation->documentChunk->page_number);
        }

        $warrantyCitation = collect(config('demo-dataset.interactions'))
            ->firstWhere('question', 'How long does the product warranty last?')['citation'];
        $warrantyDocument = $workspace->documents()
            ->where('storage_path', $warrantyCitation['storage_path'])
            ->sole();
        $this->assertSame(1, $warrantyDocument->chunks()
            ->where('page_number', $warrantyCitation['page_number'])
            ->where('content', 'like', '%'.$warrantyCitation['phrase'].'%')
            ->count());

        $overview = $this->app->make(GetWorkspaceOverview::class)->handle($workspace);
        $this->assertSame(['total' => 3, 'ready' => 3, 'processing' => 0, 'failed' => 0], $overview['documents']);
        $this->assertSame(['total' => 6, 'answered' => 4, 'needs_review' => 2], $overview['interactions']);
        $this->assertSame(['pending' => 1], $overview['reviews']);

        $this->artisan('sift:provision-demo')->assertSuccessful();

        $this->assertSame(1, User::query()->where('email', 'demo-guest@sift.internal')->count());
        $this->assertSame(1, Workspace::query()->where('slug', 'lumenfield-demo')->count());
        $this->assertSame(3, $workspace->documents()->count());
        $this->assertSame(6, $workspace->assistantInteractions()->count());
        $this->assertSame(2, $workspace->reviewItems()->count());
        $this->assertTrue(User::query()->whereKey($unrelatedUser->id)->exists());
        $this->assertTrue(Workspace::query()->whereKey($unrelatedWorkspace->id)->exists());
        $this->assertSame('Sift Demo', $unrelatedWorkspace->fresh()->name);
    }

    public function test_dry_run_validates_a_new_dataset_without_writes_storage_or_provider_calls(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        $this->bindProvidersThatMustNotRun();

        $this->artisan('sift:provision-demo', ['--dry-run' => true])
            ->expectsOutputToContain('Guest Demo dry-run checks passed. No changes were made.')
            ->assertSuccessful();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Workspace::query()->count());
        $this->assertSame([], Storage::disk('documents')->allFiles());
    }

    public function test_canonical_guest_email_collision_fails_without_overwriting_the_user(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        $this->bindProvidersThatMustNotRun();

        $user = User::query()->create([
            'name' => 'Unrelated Account',
            'email' => 'demo-guest@sift.internal',
            'password' => 'StrongPassword123',
        ]);

        $this->artisan('sift:provision-demo', ['--dry-run' => true])
            ->expectsOutputToContain('The canonical Demo identity collides with incomplete existing data.')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame('Unrelated Account', $user->fresh()->name);
        $this->assertDatabaseMissing('workspaces', ['slug' => 'lumenfield-demo']);
    }

    public function test_canonical_workspace_slug_collision_fails_without_overwriting_the_workspace(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        $this->bindProvidersThatMustNotRun();

        $workspace = Workspace::query()->create([
            'name' => 'Unrelated Workspace',
            'slug' => 'lumenfield-demo',
        ]);

        $this->artisan('sift:provision-demo', ['--dry-run' => true])
            ->expectsOutputToContain('The canonical Demo identity collides with incomplete existing data.')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame('Unrelated Workspace', $workspace->fresh()->name);
        $this->assertDatabaseMissing('users', ['email' => 'demo-guest@sift.internal']);
    }

    public function test_membership_conflicts_fail_without_repairing_or_detaching_memberships(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        $this->bindProvidersThatMustNotRun();
        [$guest, $workspace] = $this->createCanonicalPrincipal();
        $otherWorkspace = Workspace::query()->create([
            'name' => 'Other Workspace',
            'slug' => 'other-workspace',
        ]);
        $otherWorkspace->users()->attach($guest, ['role' => WorkspaceRole::Member->value]);

        $this->artisan('sift:provision-demo', ['--dry-run' => true])
            ->expectsOutputToContain('The canonical Demo membership is missing or conflicts with existing memberships.')
            ->assertExitCode(Command::FAILURE);

        $this->assertSame(2, $guest->workspaces()->count());
        $this->assertSame(1, $workspace->users()->count());
    }

    public function test_document_storage_integrity_conflict_fails_without_processing_or_repairing_it(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        $this->bindProvidersThatMustNotRun();
        $this->createCanonicalPrincipal();
        $path = 'demo/lumenfield-demo/returns-refunds-order-changes.pdf';
        Storage::disk('documents')->put($path, 'unrelated bytes');

        $this->artisan('sift:provision-demo', ['--dry-run' => true])
            ->expectsOutputToContain("The Demo document {$path} has inconsistent storage state.")
            ->assertExitCode(Command::FAILURE);

        $this->assertSame('unrelated bytes', Storage::disk('documents')->get($path));
        $this->assertSame(0, Document::query()->count());
    }

    private function configureFakePipeline(): void
    {
        Storage::fake('documents');
        config()->set('documents.disk', 'documents');
        config()->set('documents.minimum_extracted_characters', 10);
        config()->set('documents.chunking.size', 1200);
        config()->set('documents.chunking.overlap', 200);
        config()->set('ai.embeddings.dimensions', 1024);
        config()->set('ai.embeddings.batch_size', 64);

        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldReceive('extract')->times(3)->andReturnValues($this->extractedDocuments());
        $this->app->instance(PdfTextExtractor::class, $extractor);

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldReceive('embed')->times(3)->andReturnUsing(
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
        $this->app->instance(EmbeddingProvider::class, $embeddingProvider);

        $llmProvider = Mockery::mock(LlmProvider::class);
        $llmProvider->shouldNotReceive('generate');
        $this->app->instance(LlmProvider::class, $llmProvider);
    }

    private function bindProvidersThatMustNotRun(): void
    {
        $extractor = Mockery::mock(PdfTextExtractor::class);
        $extractor->shouldNotReceive('extract');
        $this->app->instance(PdfTextExtractor::class, $extractor);

        $embeddingProvider = Mockery::mock(EmbeddingProvider::class);
        $embeddingProvider->shouldNotReceive('embed');
        $this->app->instance(EmbeddingProvider::class, $embeddingProvider);

        $llmProvider = Mockery::mock(LlmProvider::class);
        $llmProvider->shouldNotReceive('generate');
        $this->app->instance(LlmProvider::class, $llmProvider);
    }

    /**
     * @return array{User, Workspace}
     */
    private function createCanonicalPrincipal(): array
    {
        $guest = User::query()->create([
            'name' => 'Guest Demo',
            'email' => 'demo-guest@sift.internal',
            'password' => 'StrongPassword123',
        ]);
        $workspace = Workspace::query()->create([
            'name' => 'Lumenfield Supply',
            'slug' => 'lumenfield-demo',
        ]);
        $workspace->users()->attach($guest, ['role' => WorkspaceRole::Member->value]);

        return [$guest, $workspace];
    }

    /**
     * @return list<ExtractedDocumentText>
     */
    private function extractedDocuments(): array
    {
        return [
            new ExtractedDocumentText([
                new ExtractedPageText(1, 'Unused products may be returned within 30 days of delivery. Refunds are issued to the original payment method after inspection.'),
                new ExtractedPageText(2, 'An order may be cancelled within 30 minutes of placement when fulfillment has not started. Contact Support for order changes.'),
            ]),
            new ExtractedDocumentText([
                new ExtractedPageText(1, 'Standard delivery takes three to five business days. Tracking is sent after dispatch, and address changes require Support.'),
                new ExtractedPageText(2, 'A damaged or incorrect product must be reported within 7 days of delivery. Missing deliveries should be reported to Support.'),
            ]),
            new ExtractedDocumentText([
                new ExtractedPageText(1, "Lumenfield Supply products include a 12-month limited warranty beginning on the original delivery\ndate. Claims require proof of purchase."),
                new ExtractedPageText(2, 'The warranty excludes misuse, accidental damage, and normal wear. Support is available Monday through Friday.'),
            ]),
        ];
    }
}
