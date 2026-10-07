<?php

namespace App\Actions\Widget;

use App\Models\Workspace;
use App\Models\WorkspaceWidget;
use Illuminate\Support\Facades\DB;

class SetWorkspaceWidgetEnabled
{
    public function handle(Workspace $workspace, bool $enabled): WorkspaceWidget
    {
        return DB::transaction(function () use ($workspace, $enabled): WorkspaceWidget {
            $lockedWorkspace = Workspace::query()
                ->lockForUpdate()
                ->findOrFail($workspace->getKey());
            $widget = $lockedWorkspace->widget()->firstOrFail();

            $widget->enabled = $enabled;
            $widget->save();

            return $widget->refresh();
        });
    }
}
