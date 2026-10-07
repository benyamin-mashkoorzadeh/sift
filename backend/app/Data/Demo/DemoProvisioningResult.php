<?php

namespace App\Data\Demo;

final readonly class DemoProvisioningResult
{
    public function __construct(
        public bool $dryRun,
        public ?int $userId,
        public ?int $workspaceId,
        public int $documentCount,
        public int $interactionCount,
        public int $reviewCount,
    ) {}
}
