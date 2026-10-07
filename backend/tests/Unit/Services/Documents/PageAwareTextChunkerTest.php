<?php

namespace Tests\Unit\Services\Documents;

use App\Data\Documents\ExtractedDocumentText;
use App\Data\Documents\ExtractedPageText;
use App\Services\Documents\PageAwareTextChunker;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PageAwareTextChunkerTest extends TestCase
{
    public function test_it_is_deterministic_and_never_crosses_physical_page_boundaries(): void
    {
        $documentText = new ExtractedDocumentText([
            new ExtractedPageText(1, str_repeat('ALPHA policy sentence. ', 8)),
            new ExtractedPageText(2, " \n\t "),
            new ExtractedPageText(3, 'BETA short page.'),
        ]);
        $chunker = new PageAwareTextChunker;

        $firstRun = $chunker->chunk($documentText, chunkSize: 70, chunkOverlap: 12);
        $secondRun = $chunker->chunk($documentText, chunkSize: 70, chunkOverlap: 12);

        $this->assertEquals($firstRun, $secondRun);
        $this->assertGreaterThan(1, count($firstRun));
        $this->assertNotContains(2, array_column($firstRun, 'pageNumber'));

        foreach ($firstRun as $chunk) {
            $this->assertLessThanOrEqual(70, mb_strlen($chunk->content));

            if ($chunk->pageNumber === 1) {
                $this->assertStringContainsString('ALPHA', $chunk->content);
                $this->assertStringNotContainsString('BETA', $chunk->content);
            } else {
                $this->assertSame(3, $chunk->pageNumber);
                $this->assertSame('BETA short page.', $chunk->content);
                $this->assertStringNotContainsString('ALPHA', $chunk->content);
            }
        }

        $pageOneIndexes = array_values(array_map(
            static fn ($chunk): int => $chunk->pageChunkIndex,
            array_filter($firstRun, static fn ($chunk): bool => $chunk->pageNumber === 1),
        ));

        $this->assertSame(range(0, count($pageOneIndexes) - 1), $pageOneIndexes);
        $this->assertSame(0, $firstRun[array_key_last($firstRun)]->pageChunkIndex);
    }

    public function test_it_prefers_semantic_boundaries_and_overlaps_adjacent_chunks(): void
    {
        $text = 'Alpha bravo charlie delta echo. Foxtrot golf hotel india juliet. '
            .'Kilo lima mike november oscar. Papa quebec romeo sierra tango.';
        $chunks = (new PageAwareTextChunker)->chunk(
            new ExtractedDocumentText([new ExtractedPageText(1, $text)]),
            chunkSize: 70,
            chunkOverlap: 15,
        );

        $this->assertGreaterThan(1, count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(70, mb_strlen($chunk->content));
        }

        $firstWords = preg_split('/\s+/u', $chunks[0]->content) ?: [];
        $secondWords = preg_split('/\s+/u', $chunks[1]->content) ?: [];
        $sharedWords = array_intersect(
            array_map(static fn (string $word): string => trim($word, '.!?'), $firstWords),
            array_map(static fn (string $word): string => trim($word, '.!?'), $secondWords),
        );

        $this->assertNotEmpty($sharedWords);
        $this->assertMatchesRegularExpression('/[.!?]$/', $chunks[0]->content);
    }

    public function test_a_short_nonblank_page_produces_one_chunk_and_blank_pages_produce_none(): void
    {
        $chunks = (new PageAwareTextChunker)->chunk(
            new ExtractedDocumentText([
                new ExtractedPageText(1, ''),
                new ExtractedPageText(2, 'A warning.'),
                new ExtractedPageText(3, "\n\n"),
            ]),
            chunkSize: 1200,
            chunkOverlap: 200,
        );

        $this->assertCount(1, $chunks);
        $this->assertSame(2, $chunks[0]->pageNumber);
        $this->assertSame(0, $chunks[0]->pageChunkIndex);
        $this->assertSame('A warning.', $chunks[0]->content);
    }

    public function test_overlap_must_be_smaller_than_the_chunk_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PageAwareTextChunker)->chunk(
            new ExtractedDocumentText([new ExtractedPageText(1, 'Text')]),
            chunkSize: 200,
            chunkOverlap: 200,
        );
    }
}
