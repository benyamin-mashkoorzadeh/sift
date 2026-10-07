<?php

namespace App\Data\Documents;

use InvalidArgumentException;

final readonly class ExtractedPageText
{
    public function __construct(
        public int $pageNumber,
        public string $text,
    ) {
        if ($pageNumber < 1) {
            throw new InvalidArgumentException('A PDF page number must be positive.');
        }
    }
}
