<?php

namespace Tests\Feature\Api;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_documents_from_the_requested_workspace_newest_first(): void
    {
        $workspace = $this->createWorkspace('primary');
        $otherWorkspace = $this->createWorkspace('other');

        $older = $this->createDocument($workspace, 'Older Policy.pdf', [
            'status' => DocumentStatus::Ready,
            'page_count' => 8,
            'created_at' => now()->subDay(),
        ]);
        $newer = $this->createDocument($workspace, 'Latest Policy.pdf', [
            'status' => DocumentStatus::Failed,
            'processing_error' => 'No extractable text was found in this PDF.',
            'created_at' => now(),
        ]);
        $this->createDocument($otherWorkspace, 'Private Policy.pdf');

        $response = $this->getJson(route('workspaces.documents.index', $workspace));

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.original_filename', 'Latest Policy.pdf')
            ->assertJsonPath('data.0.status', DocumentStatus::Failed->value)
            ->assertJsonPath('data.0.page_count', null)
            ->assertJsonPath('data.0.processing_error', 'No extractable text was found in this PDF.')
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.1.page_count', 8)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonMissing(['original_filename' => 'Private Policy.pdf']);
    }

    public function test_it_paginates_workspace_documents(): void
    {
        $workspace = $this->createWorkspace('pagination');

        foreach (range(1, 26) as $index) {
            $this->createDocument($workspace, "Policy {$index}.pdf", [
                'created_at' => now()->addSeconds($index),
            ]);
        }

        $response = $this->getJson(route('workspaces.documents.index', [
            'workspace' => $workspace,
            'page' => 2,
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 26);
    }

    public function test_an_unknown_workspace_returns_not_found(): void
    {
        $this->createWorkspace('known');

        $this->getJson('/api/workspaces/999999/documents')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createDocument(Workspace $workspace, string $filename, array $attributes = []): Document
    {
        return $workspace->documents()->create(array_merge([
            'original_filename' => $filename,
            'storage_disk' => 'documents',
            'storage_path' => "workspaces/{$workspace->id}/documents/".str()->uuid().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Processing,
        ], $attributes));
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
