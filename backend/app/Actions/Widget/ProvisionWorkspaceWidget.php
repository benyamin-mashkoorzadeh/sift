<?php

namespace App\Actions\Widget;

use App\Data\Widget\ProvisionedWorkspaceWidget;
use App\Models\Workspace;
use App\Services\Widget\WorkspaceWidgetKeyGenerator;
use Illuminate\Support\Facades\DB;

class ProvisionWorkspaceWidget
{
    public function __construct(
        private readonly WorkspaceWidgetKeyGenerator $keyGenerator,
    ) {}

    public function handle(Workspace $workspace): ProvisionedWorkspaceWidget
    {
        return DB::transaction(function () use ($workspace): ProvisionedWorkspaceWidget {
            $lockedWorkspace = Workspace::query()
                ->lockForUpdate()
                ->findOrFail($workspace->getKey());

            $existingWidget = $lockedWorkspace->widget()->first();

            if ($existingWidget !== null) {
                return new ProvisionedWorkspaceWidget($existingWidget, false);
            }

            return new ProvisionedWorkspaceWidget(
                widget: $lockedWorkspace->widget()->create([
                    'public_key' => $this->keyGenerator->generate(),
                    'enabled' => false,
                ]),
                created: true,
            );
        });
    }
}
