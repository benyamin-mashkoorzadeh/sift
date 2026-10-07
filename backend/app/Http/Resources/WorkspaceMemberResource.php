<?php

namespace App\Http\Resources;

use App\Models\WorkspaceUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkspaceUser $membership */
        $membership = $this->pivot;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $membership->role->value,
            'joined_at' => $membership->created_at?->toISOString(),
        ];
    }
}
