<?php

namespace App\Data\Team;

final readonly class GeneratedInvitationToken
{
    public function __construct(
        public string $hash,
        public string $url,
    ) {}
}
