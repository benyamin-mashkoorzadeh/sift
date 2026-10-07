<?php

namespace App\Http\Resources;

use App\Data\Auth\AuthenticatedWorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var AuthenticatedWorkspaceContext $context */
        $context = $this->resource;

        return [
            'user' => [
                'id' => $context->user->id,
                'name' => $context->user->name,
                'email' => $context->user->email,
            ],
            'workspace' => [
                'id' => $context->workspace->id,
                'name' => $context->workspace->name,
                'role' => $context->role->value,
            ],
            'access_mode' => $context->accessMode->value,
            'permissions' => array_map(
                static fn ($permission): string => $permission->value,
                $context->permissions,
            ),
        ];
    }
}
