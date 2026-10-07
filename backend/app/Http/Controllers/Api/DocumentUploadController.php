<?php

namespace App\Http\Controllers\Api;

use App\Actions\Documents\StoreDocumentUpload;
use App\Enums\DocumentStatus;
use App\Exceptions\Documents\DocumentProcessingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\Documents\ProcessDocumentJob;
use App\Models\Workspace;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Throwable;

class DocumentUploadController extends Controller
{
    public function __invoke(
        StoreDocumentRequest $request,
        Workspace $workspace,
        StoreDocumentUpload $storeDocumentUpload,
        Dispatcher $dispatcher,
    ): JsonResponse {
        $file = $request->file('file');

        if (! $file instanceof UploadedFile) {
            abort(422);
        }

        $document = $storeDocumentUpload->handle($workspace, $file);

        try {
            $dispatcher->dispatch(new ProcessDocumentJob((int) $document->getKey()));
        } catch (Throwable $exception) {
            report($exception);

            $document->forceFill([
                'status' => DocumentStatus::Failed,
                'processing_error' => DocumentProcessingException::DISPATCH_USER_MESSAGE,
                'processed_at' => null,
            ])->save();
        }

        return (new DocumentResource($document->refresh()))
            ->response()
            ->setStatusCode(201);
    }
}
