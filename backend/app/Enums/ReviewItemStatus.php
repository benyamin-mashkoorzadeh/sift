<?php

namespace App\Enums;

enum ReviewItemStatus: string
{
    case Pending = 'pending';
    case Resolved = 'resolved';
}
