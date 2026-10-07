<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\Workspace;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DocumentIndexController extends Controller
{
    public function __invoke(Workspace $workspace): AnonymousResourceCollection
    {
        $documents = $workspace->documents()
            ->latest()
            ->paginate(25);

        return DocumentResource::collection($documents);
    }
}
