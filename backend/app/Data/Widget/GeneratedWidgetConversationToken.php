<?php

namespace App\Data\Widget;

final readonly class GeneratedWidgetConversationToken
{
    public function __construct(
        public string $rawToken,
        public string $hash,
    ) {}
}
