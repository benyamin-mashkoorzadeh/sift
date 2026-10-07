<?php

namespace Tests\Feature\Models;

use App\Enums\DocumentStatus;
use App\Enums\WorkspaceRole;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_and_workspaces_have_role_aware_many_to_many_relationships(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace('acme-support');

        $workspace->users()->attach($user, [
            'role' => WorkspaceRole::Owner->value,
        ]);

        $attachedWorkspace = $user->workspaces()->firstOrFail();
        $attachedUser = $workspace->users()->firstOrFail();

        $this->assertInstanceOf(WorkspaceUser::class, $attachedWorkspace->pivot);
        $this->assertSame(WorkspaceRole::Owner, $attachedWorkspace->pivot->role);
        $this->assertTrue($attachedUser->is($user));
    }

    public function test_documents_and_chunks_are_related_and_cast_for_postgresql(): void
    {
        $workspace = $this->createWorkspace('northstar-support');
        $document = $this->createDocument($workspace);

        $chunk = $document->chunks()->create([
            'workspace_id' => $workspace->id,
            'content' => 'Customers may return unopened items within 30 days.',
            'page_number' => 3,
            'chunk_index' => 0,
            'embedding' => array_merge([0.125, -0.5, 0.75], array_fill(0, 1021, 0.0)),
            'embedding_provider' => 'cohere',
            'embedding_model' => 'model-not-selected',
            'embedded_at' => now(),
            'metadata' => ['section' => 'Returns'],
        ])->fresh();

        $columnType = DB::selectOne(<<<'SQL'
            SELECT format_type(atttypid, atttypmod) AS type
            FROM pg_attribute
            WHERE attrelid = 'document_chunks'::regclass
              AND attname = 'embedding'
            SQL);

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertSame('vector(1024)', $columnType->type);
        $this->assertTrue($document->workspace->is($workspace));
        $this->assertTrue($chunk->document->is($document));
        $this->assertTrue($chunk->workspace->is($workspace));
        $this->assertSame(DocumentStatus::Processing, $document->status);
        $this->assertSame(['section' => 'Returns'], $chunk->metadata);
        $this->assertEqualsWithDelta(0.125, $chunk->embedding[0], 0.000001);
        $this->assertEqualsWithDelta(-0.5, $chunk->embedding[1], 0.000001);
        $this->assertEqualsWithDelta(0.75, $chunk->embedding[2], 0.000001);
    }

    public function test_workspace_role_constraint_rejects_unknown_roles(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace('constraint-test');

        $this->expectException(QueryException::class);

        DB::table('workspace_user')->insert([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'viewer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_document_chunk_embeddings_must_have_exactly_1024_dimensions(): void
    {
        $workspace = $this->createWorkspace('embedding-dimension-test');
        $document = $this->createDocument($workspace);

        $this->expectException(QueryException::class);

        $document->chunks()->create([
            'workspace_id' => $workspace->id,
            'content' => 'An incorrectly sized embedding must be rejected.',
            'page_number' => 1,
            'chunk_index' => 0,
            'embedding' => [0.1, 0.2],
        ]);
    }

    public function test_chunks_cannot_reference_a_document_from_another_workspace(): void
    {
        $documentWorkspace = $this->createWorkspace('document-workspace');
        $otherWorkspace = $this->createWorkspace('other-workspace');
        $document = $this->createDocument($documentWorkspace);

        $this->expectException(QueryException::class);

        DocumentChunk::query()->create([
            'workspace_id' => $otherWorkspace->id,
            'document_id' => $document->id,
            'content' => 'This must not cross workspace boundaries.',
            'page_number' => 1,
            'chunk_index' => 0,
        ]);
    }

    public function test_deleting_a_workspace_cascades_memberships_documents_and_chunks(): void
    {
        $user = $this->createUser();
        $workspace = $this->createWorkspace('cascade-test');
        $workspace->users()->attach($user, ['role' => WorkspaceRole::Admin->value]);
        $document = $this->createDocument($workspace);
        $chunk = $document->chunks()->create([
            'workspace_id' => $workspace->id,
            'content' => 'Disposable test content.',
            'page_number' => 1,
            'chunk_index' => 0,
        ]);

        $workspace->delete();

        $this->assertDatabaseMissing('workspace_user', ['workspace_id' => $workspace->id]);
        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
        $this->assertDatabaseMissing('document_chunks', ['id' => $chunk->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    private function createUser(): User
    {
        return User::query()->create([
            'name' => 'Test User',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'test-password',
        ]);
    }

    private function createWorkspace(string $slug): Workspace
    {
        return Workspace::query()->create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
        ]);
    }

    private function createDocument(Workspace $workspace): Document
    {
        return $workspace->documents()->create([
            'original_filename' => 'return-policy.pdf',
            'storage_disk' => 'local',
            'storage_path' => "documents/{$workspace->id}/return-policy.pdf",
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => DocumentStatus::Processing,
        ]);
    }
}
