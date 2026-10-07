<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkspaceSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'workspace' => [
                'id' => $this->id,
                'name' => $this->name,
                'created_at' => $this->created_at?->toISOString(),
            ],
            'knowledge' => [
                'supported_document_types' => [
                    [
                        'extension' => 'pdf',
                        'label' => 'PDF',
                    ],
                ],
                'maximum_upload_size_bytes' => max(1, (int) config('documents.max_upload_kb')) * 1024,
            ],
            'widget' => $this->widget === null
                ? null
                : (new WorkspaceWidgetSettingsResource($this->widget))->toArray($request),
        ];
    }
}
