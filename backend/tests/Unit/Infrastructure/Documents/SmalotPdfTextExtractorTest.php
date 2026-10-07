<?php

namespace Tests\Unit\Infrastructure\Documents;

use App\Exceptions\Documents\DocumentExtractionException;
use App\Infrastructure\Documents\SmalotPdfTextExtractor;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Parser;

class SmalotPdfTextExtractorTest extends TestCase
{
    public function test_it_extracts_ordered_pages_and_preserves_a_blank_page(): void
    {
        $extractor = new SmalotPdfTextExtractor(new Parser);

        $result = $extractor->extract($this->threePagePdfWithBlankMiddlePage());

        $this->assertSame(3, $result->pageCount());
        $this->assertSame([1, 2, 3], array_column($result->pages, 'pageNumber'));
        $this->assertStringContainsString('First page text', $result->pages[0]->text);
        $this->assertSame('', $result->pages[1]->text);
        $this->assertStringContainsString('Third page text', $result->pages[2]->text);
    }

    public function test_it_wraps_parser_failures_in_an_application_exception(): void
    {
        $extractor = new SmalotPdfTextExtractor(new Parser);

        $this->expectException(DocumentExtractionException::class);
        $this->expectExceptionMessage(DocumentExtractionException::USER_MESSAGE);

        $extractor->extract('not a PDF');
    }

    private function threePagePdfWithBlankMiddlePage(): string
    {
        $firstPageStream = 'BT /F1 12 Tf 72 720 Td (First page text) Tj ET';
        $thirdPageStream = 'BT /F1 12 Tf 72 720 Td (Third page text) Tj ET';

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R 5 0 R 6 0 R] /Count 3 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 7 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 8 0 R >>',
            6 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 9 0 R >>',
            7 => '<< /Length '.strlen($firstPageStream)." >>\nstream\n{$firstPageStream}\nendstream",
            8 => "<< /Length 0 >>\nstream\n\nendstream",
            9 => '<< /Length '.strlen($thirdPageStream)." >>\nstream\n{$thirdPageStream}\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 10\n";
        $pdf .= "0000000000 65535 f \n";

        for ($number = 1; $number <= 9; $number++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$number])."\n";
        }

        $pdf .= "trailer\n<< /Size 10 /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        return $pdf;
    }
}
