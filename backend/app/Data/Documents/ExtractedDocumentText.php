<?php

namespace App\Data\Documents;

final readonly class ExtractedDocumentText
{
    /**
     * @param  list<ExtractedPageText>  $pages
     */
    public function __construct(public array $pages) {}

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function extractedCharacterCount(): int
    {
        return array_sum(array_map(
            static function (ExtractedPageText $page): int {
                $withoutWhitespace = preg_replace('/\s+/u', '', $page->text);

                return mb_strlen($withoutWhitespace ?? trim($page->text));
            },
            $this->pages,
        ));
    }
}
