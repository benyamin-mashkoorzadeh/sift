<?php

namespace App\Data\Widget;

use Illuminate\Support\Carbon;

final readonly class StartedWidgetConversation
{
    public function __construct(
        public string $rawToken,
        public Carbon $expiresAt,
    ) {}
}
