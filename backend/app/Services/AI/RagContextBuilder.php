<?php

namespace App\Services\AI;

use App\Data\AI\RagContext;
use App\Data\AI\RagContextSource;
use App\Data\AI\RetrievalResult;
use App\Data\AI\RetrievedChunk;
use App\Exceptions\AI\RagAnswerException;

class RagContextBuilder
{
    public function build(RetrievalResult $retrieval): RagContext
    {
        $maximumChunks = (int) config('ai.context.max_chunks');
        $maximumCharacters = (int) config('ai.context.max_characters');

        if ($maximumChunks < 1 || $maximumCharacters < 1) {
            throw new RagAnswerException(RagAnswerException::USER_MESSAGE);
        }

        $chunks = $retrieval->chunks;

        usort($chunks, static function (RetrievedChunk $left, RetrievedChunk $right): int {
            return $right->similarity <=> $left->similarity
                ?: $left->distance <=> $right->distance
                ?: $left->chunkId <=> $right->chunkId;
        });

        $sources = [];
        $seenChunkIds = [];
        $usedCharacters = 0;

        foreach ($chunks as $chunk) {
            if (isset($seenChunkIds[$chunk->chunkId])) {
                continue;
            }

            if (count($sources) >= $maximumChunks) {
                break;
            }

            $content = trim($chunk->content);
            $characters = mb_strlen($content);

            if ($content === '' || $usedCharacters + $characters > $maximumCharacters) {
                continue;
            }

            $seenChunkIds[$chunk->chunkId] = true;
            $sources[] = new RagContextSource(
                key: 'source_'.(count($sources) + 1),
                documentId: $chunk->documentId,
                originalFilename: $chunk->originalFilename,
                chunkId: $chunk->chunkId,
                pageNumber: $chunk->pageNumber,
                chunkIndex: $chunk->chunkIndex,
                content: $content,
                distance: $chunk->distance,
                similarity: $chunk->similarity,
            );
            $usedCharacters += $characters;
        }

        return new RagContext($sources);
    }
}
