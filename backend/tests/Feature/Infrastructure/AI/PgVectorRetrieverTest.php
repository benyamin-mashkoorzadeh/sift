<?php

namespace Tests\Feature\Infrastructure\AI;

use App\Contracts\AI\Retriever;
use App\Data\AI\RetrievalOptions;
use App\Data\AI\RetrievalRequest;
use App\Enums\DocumentStatus;
use App\Exceptions\AI\RetrievalException;
use App\Infrastructure\AI\PgVectorRetriever;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PgVectorRetrieverTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_retriever_contract_resolves_to_pgvector(): void
    {
        $this->assertInstanceOf(PgVectorRetriever::class, $this->app->make(Retriever::class));
    }

    public function test_it_ranks_citation_ready_results_and_strictly_scopes_them_to_the_workspace(): void
    {
        $workspace = $this->createWorkspace('target-workspace');
        $otherWorkspace = $this->createWorkspace('other-workspace');
        $processingDocument = $this->createDocument(
            $workspace,
            'Returns Policy.pdf',
            DocumentStatus::Processing,
        );
        $readyDocument = $this->createDocument(
            $workspace,
            'Warranty.pdf',
            DocumentStatus::Ready,
        );
        $failedDocument = $this->createDocument(
            $workspace,
            'Failed.pdf',
            DocumentStatus::Failed,
        );
        $otherDocument = $this->createDocument(
            $otherWorkspace,
            'Other Workspace.pdf',
            DocumentStatus::Ready,
        );

        $exact = $this->createChunk($processingDocument, 0, $this->vector(1, 0));
        $medium = $this->createChunk($processingDocument, 1, $this->vector(1, 1));
        $this->createChunk($processingDocument, 2, $this->vector(0, 1));
        $ready = $this->createChunk($readyDocument, 0, $this->vector(1, 0.5));
        $this->createChunk($failedDocument, 0, $this->vector(1, 0));
        $this->createChunk($otherDocument, 0, $this->vector(1, 0));
        $this->createChunk($processingDocument, 3, null);
        $this->createChunk($processingDocument, 4, $this->vector(1, 0), provider: 'other-provider');
        $this->createChunk($processingDocument, 5, $this->vector(1, 0), model: 'other-model');
        $this->createChunk($processingDocument, 6, $this->vector(1, 0), embedded: false);

        $result = $this->retriever()->retrieve($this->request(
            workspace: $workspace,
            queryVector: $this->vector(1, 0),
            options: new RetrievalOptions(topK: 3),
        ));

        $this->assertSame([$exact->id, $ready->id, $medium->id], array_column($result->chunks, 'chunkId'));
        $this->assertCount(3, $result->chunks);
        $this->assertSame($processingDocument->id, $result->chunks[0]->documentId);
        $this->assertSame('Returns Policy.pdf', $result->chunks[0]->originalFilename);
        $this->assertSame(1, $result->chunks[0]->pageNumber);
        $this->assertSame(0, $result->chunks[0]->chunkIndex);
        $this->assertSame('Chunk 0 from Returns Policy.pdf', $result->chunks[0]->content);
        $this->assertEqualsWithDelta(0.0, $result->chunks[0]->distance, 0.000001);
        $this->assertEqualsWithDelta(1.0, $result->chunks[0]->similarity, 0.000001);
        $this->assertGreaterThan($result->chunks[2]->similarity, $result->chunks[1]->similarity);
    }

    public function test_it_applies_an_optional_similarity_threshold(): void
    {
        $workspace = $this->createWorkspace('threshold-workspace');
        $document = $this->createDocument($workspace, 'Threshold.pdf', DocumentStatus::Ready);
        $exact = $this->createChunk($document, 0, $this->vector(1, 0));
        $this->createChunk($document, 1, $this->vector(1, 1));

        $result = $this->retriever()->retrieve($this->request(
            workspace: $workspace,
            queryVector: $this->vector(1, 0),
            options: new RetrievalOptions(topK: 5, minimumSimilarity: 0.8),
        ));

        $this->assertSame([$exact->id], array_column($result->chunks, 'chunkId'));
    }

    public function test_an_empty_workspace_returns_an_empty_result(): void
    {
        $workspace = $this->createWorkspace('empty-workspace');

        $result = $this->retriever()->retrieve($this->request(
            workspace: $workspace,
            queryVector: $this->vector(1, 0),
            options: new RetrievalOptions(topK: 5),
        ));

        $this->assertTrue($result->isEmpty());
    }

    public function test_it_rejects_a_query_vector_with_the_wrong_dimension(): void
    {
        $workspace = $this->createWorkspace('wrong-dimension-workspace');

        $this->expectException(RetrievalException::class);
        $this->expectExceptionMessage(RetrievalException::USER_MESSAGE);

        $this->retriever()->retrieve($this->request(
            workspace: $workspace,
            queryVector: array_fill(0, 1023, 0.1),
            options: new RetrievalOptions(topK: 5),
        ));
    }

    private function retriever(): Retriever
    {
        return $this->app->make(Retriever::class);
    }

    private function request(
        Workspace $workspace,
        array $queryVector,
        RetrievalOptions $options,
    ): RetrievalRequest {
        return new RetrievalRequest(
            workspaceId: $workspace->id,
            queryVector: $queryVector,
            embeddingProvider: 'cohere',
            embeddingModel: 'embed-v4.0',
            options: $options,
        );
    }

    private function createWorkspace(string $slug): Workspace
    {
        return Workspace::query()->create([
            'name' => str($slug)->headline()->toString(),
            'slug' => $slug,
        ]);
    }

    private function createDocument(
        Workspace $workspace,
        string $filename,
        DocumentStatus $status,
    ): Document {
        return $workspace->documents()->create([
            'original_filename' => $filename,
            'storage_disk' => 'local',
            'storage_path' => "documents/{$workspace->id}/".str($filename)->slug().'.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'status' => $status,
        ]);
    }

    private function createChunk(
        Document $document,
        int $chunkIndex,
        ?array $embedding,
        string $provider = 'cohere',
        string $model = 'embed-v4.0',
        bool $embedded = true,
    ): DocumentChunk {
        return $document->chunks()->create([
            'workspace_id' => $document->workspace_id,
            'content' => "Chunk {$chunkIndex} from {$document->original_filename}",
            'page_number' => $chunkIndex + 1,
            'chunk_index' => $chunkIndex,
            'embedding' => $embedding,
            'embedding_provider' => $embedding === null ? null : $provider,
            'embedding_model' => $embedding === null ? null : $model,
            'embedded_at' => $embedding !== null && $embedded ? now() : null,
        ]);
    }

    /**
     * @return list<float>
     */
    private function vector(float $first, float $second): array
    {
        return array_merge([$first, $second], array_fill(0, 1022, 0.0));
    }
}
