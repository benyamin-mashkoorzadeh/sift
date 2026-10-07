<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreDocumentUpload
{
    public function handle(Workspace $workspace, UploadedFile $file): Document
    {
        $diskName = (string) config('documents.disk');
        $disk = Storage::disk($diskName);
        $directory = "workspaces/{$workspace->getKey()}/documents";
        $path = $disk->putFile($directory, $file);

        if ($path === false) {
            throw new RuntimeException('The document could not be stored.');
        }

        try {
            return DB::transaction(fn (): Document => $workspace->documents()->create([
                'original_filename' => $this->safeOriginalFilename($file),
                'storage_disk' => $diskName,
                'storage_path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'size_bytes' => $file->getSize(),
                'status' => DocumentStatus::Processing,
            ]));
        } catch (Throwable $exception) {
            $this->deleteOrphanedUpload($disk, $path);

            throw $exception;
        }
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $untrustedName = str_replace('\\', '/', $file->getClientOriginalName());
        $basename = basename($untrustedName);
        $basename = preg_replace('/[\x00-\x1F\x7F]/u', '', $basename) ?? '';
        $stem = trim((string) pathinfo($basename, PATHINFO_FILENAME));

        if ($stem === '') {
            $stem = 'document';
        }

        return Str::limit($stem, 251, '').'.pdf';
    }

    private function deleteOrphanedUpload(FilesystemAdapter $disk, string $path): void
    {
        try {
            if (! $disk->delete($path)) {
                report(new RuntimeException("Failed to delete orphaned document upload [{$path}]."));
            }
        } catch (Throwable $cleanupException) {
            report($cleanupException);
        }
    }
}
