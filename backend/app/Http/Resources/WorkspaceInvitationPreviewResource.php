<?php

namespace App\Http\Resources;

use App\Data\Team\WorkspaceInvitationPreview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceInvitationPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkspaceInvitationPreview $preview */
        $preview = $this->resource;

        return [
            'workspace' => [
                'name' => $preview->invitation->workspace->name,
            ],
            'email' => $preview->invitation->email,
            'role' => $preview->invitation->role->value,
            'expires_at' => $preview->invitation->expires_at->toISOString(),
            'account_state' => $preview->accountState->value,
        ];
    }
}
