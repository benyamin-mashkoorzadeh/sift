<?php

namespace App\Infrastructure\AI;

use App\Contracts\AI\Retriever;
use App\Data\AI\RetrievalRequest;
use App\Data\AI\RetrievalResult;
use App\Data\AI\RetrievedChunk;
use App\Enums\DocumentStatus;
use App\Exceptions\AI\RetrievalException;
use Illuminate\Support\Facades\DB;
use Throwable;

class PgVectorRetriever implements Retriever
{
    public function retrieve(RetrievalRequest $request): RetrievalResult
    {
        if (count($request->queryVector) !== (int) config('ai.embeddings.dimensions')) {
            throw new RetrievalException(RetrievalException::USER_MESSAGE);
        }

        $thresholdClause = $request->options->minimumSimilarity === null
            ? ''
            : 'WHERE (1 - distance) >= :minimum_similarity';

        $sql = <<<SQL
            WITH query_vector AS (
                SELECT CAST(:query_vector AS vector(1024)) AS embedding
            ), ranked_chunks AS (
                SELECT
                    dc.id AS chunk_id,
                    dc.document_id,
                    d.original_filename,
                    dc.page_number,
                    dc.chunk_index,
                    dc.content,
                    dc.embedding <=> query_vector.embedding AS distance
                FROM document_chunks dc
                INNER JOIN documents d
                    ON d.id = dc.document_id
                    AND d.workspace_id = dc.workspace_id
                CROSS JOIN query_vector
                WHERE dc.workspace_id = :workspace_id
                    AND d.workspace_id = :document_workspace_id
                    AND d.status IN (:processing_status, :ready_status)
                    AND dc.embedding IS NOT NULL
                    AND dc.embedding_provider = :embedding_provider
                    AND dc.embedding_model = :embedding_model
                    AND dc.embedded_at IS NOT NULL
            )
            SELECT
                chunk_id,
                document_id,
                original_filename,
                page_number,
                chunk_index,
                content,
                distance,
                1 - distance AS similarity
            FROM ranked_chunks
            {$thresholdClause}
            ORDER BY distance ASC, chunk_id ASC
            LIMIT :top_k
            SQL;

        $bindings = [
            'query_vector' => $this->serializeVector($request->queryVector),
            'workspace_id' => $request->workspaceId,
            'document_workspace_id' => $request->workspaceId,
            'processing_status' => DocumentStatus::Processing->value,
            'ready_status' => DocumentStatus::Ready->value,
            'embedding_provider' => $request->embeddingProvider,
            'embedding_model' => $request->embeddingModel,
            'top_k' => $request->options->topK,
        ];

        if ($request->options->minimumSimilarity !== null) {
            $bindings['minimum_similarity'] = $request->options->minimumSimilarity;
        }

        try {
            $rows = DB::select($sql, $bindings);
        } catch (Throwable $exception) {
            throw new RetrievalException(
                RetrievalException::USER_MESSAGE,
                previous: $exception,
            );
        }

        return new RetrievalResult(array_map(
            static fn (object $row): RetrievedChunk => new RetrievedChunk(
                documentId: (int) $row->document_id,
                originalFilename: (string) $row->original_filename,
                chunkId: (int) $row->chunk_id,
                pageNumber: (int) $row->page_number,
                chunkIndex: (int) $row->chunk_index,
                content: (string) $row->content,
                distance: (float) $row->distance,
                similarity: (float) $row->similarity,
            ),
            $rows,
        ));
    }

    /**
     * @param  list<float>  $vector
     */
    private function serializeVector(array $vector): string
    {
        return '['.implode(',', array_map(
            static fn (float $component): string => (string) $component,
            $vector,
        )).']';
    }
}
