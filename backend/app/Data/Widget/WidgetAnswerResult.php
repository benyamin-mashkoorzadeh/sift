<?php

namespace App\Data\Widget;

use App\Enums\RagAnswerStatus;
use InvalidArgumentException;

final readonly class WidgetAnswerResult
{
    public function __construct(
        public RagAnswerStatus $status,
        public string $answer,
    ) {
        if (trim($answer) === '') {
            throw new InvalidArgumentException('A widget answer cannot be blank.');
        }
    }
}
