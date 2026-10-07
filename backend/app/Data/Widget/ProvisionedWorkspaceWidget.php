<?php

namespace App\Data\Widget;

use App\Models\WorkspaceWidget;

final readonly class ProvisionedWorkspaceWidget
{
    public function __construct(
        public WorkspaceWidget $widget,
        public bool $created,
    ) {}
}
