<?php

namespace App\Enums;

enum GenerationDecision: string
{
    case Answered = 'answered';
    case Insufficient = 'insufficient';
}
