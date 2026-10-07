<?php

namespace App\Http\Resources\Widget;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WidgetBootstrapResource extends JsonResource
{
    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'workspace_name' => $this->workspace->name,
        ];
    }
}
