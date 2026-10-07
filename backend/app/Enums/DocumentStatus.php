<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
