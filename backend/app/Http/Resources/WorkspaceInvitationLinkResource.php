<?php

namespace App\Http\Resources;

use App\Data\Team\WorkspaceInvitationLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceInvitationLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkspaceInvitationLink $link */
        $link = $this->resource;

        return [
            'invitation' => (new WorkspaceInvitationResource($link->invitation))->resolve($request),
            'invitation_url' => $link->url,
        ];
    }
}
