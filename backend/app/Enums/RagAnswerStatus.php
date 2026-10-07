<?php

namespace App\Enums;

enum RagAnswerStatus: string
{
    case Answered = 'answered';
    case NeedsReview = 'needs_review';
}
