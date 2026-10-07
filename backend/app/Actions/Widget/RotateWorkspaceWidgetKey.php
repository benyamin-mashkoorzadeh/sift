<?php

namespace App\Actions\Widget;

use App\Models\Workspace;
use App\Models\WorkspaceWidget;
use App\Services\Widget\WorkspaceWidgetKeyGenerator;
use Illuminate\Support\Facades\DB;

class RotateWorkspaceWidgetKey
{
    public function __construct(
        private readonly WorkspaceWidgetKeyGenerator $keyGenerator,
    ) {}

    public function handle(Workspace $workspace): WorkspaceWidget
    {
        return DB::transaction(function () use ($workspace): WorkspaceWidget {
            $lockedWorkspace = Workspace::query()
                ->lockForUpdate()
                ->findOrFail($workspace->getKey());
            $widget = $lockedWorkspace->widget()->firstOrFail();

            $widget->public_key = $this->keyGenerator->generate();
            $widget->key_rotated_at = now();
            $widget->save();

            return $widget->refresh();
        });
    }
}
