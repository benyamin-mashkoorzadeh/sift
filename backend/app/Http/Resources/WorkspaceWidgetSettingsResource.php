<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceWidgetSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $frontendUrl = rtrim((string) config('widget.frontend_url'), '/');

        return [
            'enabled' => $this->enabled,
            'public_key' => $this->public_key,
            'script_url' => "{$frontendUrl}/widget.js",
            'key_rotated_at' => $this->key_rotated_at?->toISOString(),
        ];
    }
}
