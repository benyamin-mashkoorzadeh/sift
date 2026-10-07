<?php

namespace App\Services\Documents;

use App\Contracts\Documents\TextChunker;
use App\Data\Documents\DocumentTextChunk;
use App\Data\Documents\ExtractedDocumentText;
use InvalidArgumentException;

class PageAwareTextChunker implements TextChunker
{
    private const MINIMUM_SEMANTIC_BOUNDARY_RATIO = 0.6;

    public function chunk(
        ExtractedDocumentText $documentText,
        int $chunkSize,
        int $chunkOverlap,
    ): array {
        $this->validateConfiguration($chunkSize, $chunkOverlap);

        $chunks = [];

        foreach ($documentText->pages as $page) {
            $pageText = $this->normalize($page->text);

            if ($pageText === '') {
                continue;
            }

            foreach ($this->chunkPage($pageText, $chunkSize, $chunkOverlap) as $pageChunkIndex => $content) {
                $chunks[] = new DocumentTextChunk(
                    pageNumber: $page->pageNumber,
                    pageChunkIndex: $pageChunkIndex,
                    content: $content,
                );
            }
        }

        return $chunks;
    }

    private function validateConfiguration(int $chunkSize, int $chunkOverlap): void
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size must be positive.');
        }

        if ($chunkOverlap < 0 || $chunkOverlap >= $chunkSize) {
            throw new InvalidArgumentException('The chunk overlap must be non-negative and smaller than the chunk size.');
        }
    }

    private function normalize(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace('/[\p{Zs}\t]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/ *\n */u', "\n", $normalized) ?? $normalized;
        $normalized = preg_replace('/\n{3,}/u', "\n\n", $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * @return list<string>
     */
    private function chunkPage(string $text, int $chunkSize, int $chunkOverlap): array
    {
        if (mb_strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $textLength = mb_strlen($text);
        $offset = 0;

        while ($offset < $textLength) {
            $remainingLength = $textLength - $offset;

            if ($remainingLength <= $chunkSize) {
                $content = trim(mb_substr($text, $offset));

                if ($content !== '') {
                    $chunks[] = $content;
                }

                break;
            }

            $window = mb_substr($text, $offset, $chunkSize);
            $cutLength = $this->preferredCutLength($window, $chunkSize);
            $content = trim(mb_substr($window, 0, $cutLength));

            if ($content !== '') {
                $chunks[] = $content;
            }

            $nextOffset = $this->nextOffset(
                text: $text,
                currentOffset: $offset,
                cutLength: $cutLength,
                overlap: $chunkOverlap,
            );

            $offset = max($offset + 1, $nextOffset);
        }

        return $chunks;
    }

    private function preferredCutLength(string $window, int $chunkSize): int
    {
        $minimumCut = max(1, (int) floor($chunkSize * self::MINIMUM_SEMANTIC_BOUNDARY_RATIO));

        foreach (["\n\n", "\n"] as $boundary) {
            $position = mb_strrpos($window, $boundary);

            if ($position !== false && $position >= $minimumCut) {
                return $position;
            }
        }

        $sentenceBoundary = $this->lastRegexBoundary(
            $window,
            '/[.!?](?:["\')\]]*)\s+/u',
            $minimumCut,
        );

        if ($sentenceBoundary !== null) {
            return $sentenceBoundary;
        }

        $wordBoundary = mb_strrpos($window, ' ');

        if ($wordBoundary !== false && $wordBoundary >= $minimumCut) {
            return $wordBoundary;
        }

        return $chunkSize;
    }

    private function lastRegexBoundary(string $text, string $pattern, int $minimumCut): ?int
    {
        $matched = preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);

        if ($matched === false || $matched === 0) {
            return null;
        }

        for ($index = count($matches[0]) - 1; $index >= 0; $index--) {
            [$match, $byteOffset] = $matches[0][$index];
            $boundary = mb_strlen(substr($text, 0, $byteOffset).rtrim($match));

            if ($boundary >= $minimumCut) {
                return $boundary;
            }
        }

        return null;
    }

    private function nextOffset(
        string $text,
        int $currentOffset,
        int $cutLength,
        int $overlap,
    ): int {
        $offset = $currentOffset + $cutLength - $overlap;
        $minimumProgress = $currentOffset + 1;

        if ($offset <= $minimumProgress) {
            return $minimumProgress;
        }

        $textLength = mb_strlen($text);

        while ($offset < $textLength && ! preg_match('/\s/u', mb_substr($text, $offset, 1))) {
            $offset++;
        }

        while ($offset < $textLength && preg_match('/\s/u', mb_substr($text, $offset, 1))) {
            $offset++;
        }

        return $offset;
    }
}
