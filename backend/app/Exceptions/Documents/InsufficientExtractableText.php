<?php

namespace App\Exceptions\Documents;

class InsufficientExtractableText extends DocumentExtractionException
{
    public const USER_MESSAGE = 'This PDF contains little or no extractable text. It may be scanned or image-based.';
}
