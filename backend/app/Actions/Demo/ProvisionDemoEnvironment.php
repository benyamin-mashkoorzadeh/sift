<?php

namespace App\Actions\Demo;

use App\Actions\Documents\ProcessDocument;
use App\Data\Demo\DemoProvisioningResult;
use App\Enums\AssistantInteractionOrigin;
use App\Enums\DocumentStatus;
use App\Enums\RagAnswerStatus;
use App\Enums\ReviewItemStatus;
use App\Enums\WorkspaceRole;
use App\Exceptions\Demo\DemoProvisioningException;
use App\Models\AssistantInteraction;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\ReviewItem;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProvisionDemoEnvironment
{
    public function __construct(
        private readonly ProcessDocument $processDocument,
    ) {}

    public function handle(bool $dryRun = false): DemoProvisioningResult
    {
        $manifest = $this->manifest();
        $assets = $this->validateAssets($manifest['documents']);
        [$user, $workspace] = $this->resolvePrincipal($manifest, $dryRun);

        if ($workspace === null) {
            $this->assertStoragePathsAvailable($manifest['documents']);

            return new DemoProvisioningResult(
                dryRun: true,
                userId: null,
                workspaceId: null,
                documentCount: count($manifest['documents']),
                interactionCount: count($manifest['interactions']),
                reviewCount: count($manifest['reviews']),
            );
        }

        $documents = $this->provisionDocuments($workspace, $manifest['documents'], $assets, $dryRun);
        $this->provisionHistory($workspace, $documents, $manifest, $dryRun);

        return new DemoProvisioningResult(
            dryRun: $dryRun,
            userId: (int) $user?->getKey(),
            workspaceId: (int) $workspace->getKey(),
            documentCount: count($manifest['documents']),
            interactionCount: count($manifest['interactions']),
            reviewCount: count($manifest['reviews']),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function manifest(): array
    {
        $manifest = config('demo-dataset');

        if (! is_array($manifest)) {
            throw new DemoProvisioningException('The Demo dataset manifest is unavailable.');
        }

        return $manifest;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @return array<string, string>
     */
    private function validateAssets(array $documents): array
    {
        $assets = [];

        foreach ($documents as $definition) {
            $assetName = (string) ($definition['asset'] ?? '');
            $expectedHash = (string) ($definition['sha256'] ?? '');
            $path = resource_path("demo/knowledge/{$assetName}");
            $contents = is_file($path) ? file_get_contents($path) : false;

            if (! is_string($contents) || $contents === '') {
                throw new DemoProvisioningException("The bundled Demo asset {$assetName} is missing.");
            }

            if (! hash_equals($expectedHash, hash('sha256', $contents))) {
                throw new DemoProvisioningException("The bundled Demo asset {$assetName} failed its integrity check.");
            }

            $assets[$assetName] = $contents;
        }

        return $assets;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{?User, ?Workspace}
     */
    private function resolvePrincipal(array $manifest, bool $dryRun): array
    {
        $guest = $manifest['guest'];
        $workspaceDefinition = $manifest['workspace'];
        $user = User::query()->where('email', $guest['email'])->first();
        $workspace = Workspace::query()->where('slug', $workspaceDefinition['slug'])->first();

        if (($user === null) !== ($workspace === null)) {
            throw new DemoProvisioningException('The canonical Demo identity collides with incomplete existing data.');
        }

        if ($user !== null && $workspace !== null) {
            $this->assertPrincipalIntegrity($user, $workspace, $guest, $workspaceDefinition);

            return [$user, $workspace];
        }

        if ($dryRun) {
            return [null, null];
        }

        return DB::transaction(function () use ($guest, $workspaceDefinition): array {
            if (User::query()->where('email', $guest['email'])->lockForUpdate()->exists()
                || Workspace::query()->where('slug', $workspaceDefinition['slug'])->lockForUpdate()->exists()) {
                throw new DemoProvisioningException('The canonical Demo identity changed during provisioning.');
            }

            $user = User::query()->create([
                'name' => $guest['name'],
                'email' => $guest['email'],
                'password' => Str::random(64),
            ]);
            $workspace = Workspace::query()->create([
                'name' => $workspaceDefinition['name'],
                'slug' => $workspaceDefinition['slug'],
            ]);
            $workspace->users()->attach($user->getKey(), [
                'role' => WorkspaceRole::Member->value,
            ]);

            return [$user, $workspace];
        });
    }

    /**
     * @param  array<string, mixed>  $guest
     * @param  array<string, mixed>  $workspaceDefinition
     */
    private function assertPrincipalIntegrity(
        User $user,
        Workspace $workspace,
        array $guest,
        array $workspaceDefinition,
    ): void {
        if ($user->name !== $guest['name'] || $workspace->name !== $workspaceDefinition['name']) {
            throw new DemoProvisioningException('The canonical Demo user or workspace has incompatible identity data.');
        }

        $userWorkspaces = $user->workspaces()->get();
        $workspaceUsers = $workspace->users()->get();

        if ($userWorkspaces->count() !== 1
            || ! $userWorkspaces->first()->is($workspace)
            || $workspaceUsers->count() !== 1
            || ! $workspaceUsers->first()->is($user)
            || $userWorkspaces->first()->pivot->role !== WorkspaceRole::Member) {
            throw new DemoProvisioningException('The canonical Demo membership is missing or conflicts with existing memberships.');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function assertStoragePathsAvailable(array $definitions): void
    {
        $disk = $this->documentDisk();

        foreach ($definitions as $definition) {
            if (Storage::disk($disk)->exists($definition['storage_path'])
                || Document::query()
                    ->where('storage_disk', $disk)
                    ->where('storage_path', $definition['storage_path'])
                    ->exists()) {
                throw new DemoProvisioningException('A canonical Demo document path is already occupied by unrelated data.');
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, string>  $assets
     * @return array<string, Document>
     */
    private function provisionDocuments(
        Workspace $workspace,
        array $definitions,
        array $assets,
        bool $dryRun,
    ): array {
        $disk = $this->documentDisk();
        $expectedPaths = array_column($definitions, 'storage_path');
        $unexpected = $workspace->documents()
            ->where(function ($query) use ($disk, $expectedPaths): void {
                $query->where('storage_disk', '!=', $disk)
                    ->orWhereNotIn('storage_path', $expectedPaths);
            })
            ->exists();

        if ($unexpected) {
            throw new DemoProvisioningException('The canonical Demo workspace contains unexpected documents.');
        }

        $documents = [];

        foreach ($definitions as $definition) {
            $path = (string) $definition['storage_path'];
            $asset = $assets[$definition['asset']];
            $document = Document::query()
                ->where('storage_disk', $disk)
                ->where('storage_path', $path)
                ->first();
            $stored = Storage::disk($disk)->exists($path);

            if ($document !== null && ! $document->workspace->is($workspace)) {
                throw new DemoProvisioningException("The Demo document path {$path} belongs to another workspace.");
            }

            if (($document === null) !== (! $stored)) {
                throw new DemoProvisioningException("The Demo document {$path} has inconsistent storage state.");
            }

            if ($document === null) {
                if ($dryRun) {
                    continue;
                }

                $document = $this->createDocument($workspace, $disk, $definition, $asset);
            } else {
                $this->assertDocumentMetadata($document, $definition, $asset);
                $storedContents = Storage::disk($disk)->get($path);

                if (! is_string($storedContents)
                    || ! hash_equals($definition['sha256'], hash('sha256', $storedContents))) {
                    throw new DemoProvisioningException("The stored Demo document {$path} failed its integrity check.");
                }
            }

            if ($dryRun) {
                if ($document->status === DocumentStatus::Ready) {
                    $this->assertReadyDocument($document, $definition);
                }

                $documents[$path] = $document;

                continue;
            }

            if ($document->status !== DocumentStatus::Ready) {
                $document = $this->processDocument->handle($document);
            }

            $this->assertReadyDocument($document, $definition);
            $documents[$path] = $document;
        }

        return $documents;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function createDocument(
        Workspace $workspace,
        string $disk,
        array $definition,
        string $asset,
    ): Document {
        $path = (string) $definition['storage_path'];

        if (! Storage::disk($disk)->put($path, $asset)) {
            throw new DemoProvisioningException("The Demo document {$path} could not be stored.");
        }

        try {
            return $workspace->documents()->create([
                'original_filename' => $definition['filename'],
                'storage_disk' => $disk,
                'storage_path' => $path,
                'mime_type' => 'application/pdf',
                'size_bytes' => strlen($asset),
                'status' => DocumentStatus::Processing,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertDocumentMetadata(Document $document, array $definition, string $asset): void
    {
        if ($document->original_filename !== $definition['filename']
            || $document->mime_type !== 'application/pdf'
            || $document->size_bytes !== strlen($asset)) {
            throw new DemoProvisioningException("The Demo document {$definition['storage_path']} has incompatible metadata.");
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertReadyDocument(Document $document, array $definition): void
    {
        $chunks = $document->chunks()->orderBy('chunk_index')->get();
        $dimensions = (int) config('ai.embeddings.dimensions');

        if ($document->status !== DocumentStatus::Ready
            || $document->page_count !== $definition['page_count']
            || $document->processed_at === null
            || $chunks->isEmpty()
            || $chunks->contains(static fn (DocumentChunk $chunk): bool => count($chunk->embedding ?? []) !== $dimensions
                || ! is_string($chunk->embedding_provider)
                || $chunk->embedding_provider === ''
                || ! is_string($chunk->embedding_model)
                || $chunk->embedding_model === ''
                || $chunk->embedded_at === null)) {
            throw new DemoProvisioningException("The Demo document {$definition['storage_path']} is not completely processed.");
        }
    }

    /**
     * @param  array<string, Document>  $documents
     * @param  array<string, mixed>  $manifest
     */
    private function provisionHistory(
        Workspace $workspace,
        array $documents,
        array $manifest,
        bool $dryRun,
    ): void {
        $expectedQuestions = array_column($manifest['interactions'], 'question');
        $expectedReviewQuestions = array_column($manifest['reviews'], 'question');

        if ($workspace->assistantInteractions()
            ->where(function ($query) use ($expectedQuestions): void {
                $query->where('origin', '!=', AssistantInteractionOrigin::Assistant->value)
                    ->orWhereNotIn('question', $expectedQuestions);
            })
            ->exists()
            || $workspace->reviewItems()->whereNotIn('question', $expectedReviewQuestions)->exists()) {
            throw new DemoProvisioningException('The canonical Demo workspace contains unexpected history or Review records.');
        }

        if ($dryRun) {
            $this->validateExistingHistory($workspace, $documents, $manifest);

            return;
        }

        DB::transaction(function () use ($workspace, $documents, $manifest): void {
            $reviews = [];

            foreach ($manifest['reviews'] as $definition) {
                $reviews[$definition['question']] = $this->ensureReview($workspace, $definition);
            }

            foreach ($manifest['interactions'] as $definition) {
                $this->ensureInteraction($workspace, $documents, $reviews, $definition);
            }
        });

        $this->assertExpectedCounts($workspace, $manifest);
    }

    /**
     * @param  array<string, Document>  $documents
     * @param  array<string, mixed>  $manifest
     */
    private function validateExistingHistory(Workspace $workspace, array $documents, array $manifest): void
    {
        foreach ($manifest['reviews'] as $definition) {
            $matches = $workspace->reviewItems()->where('question', $definition['question'])->get();

            if ($matches->count() > 1) {
                throw new DemoProvisioningException('A canonical Demo Review item is ambiguous.');
            }

            if ($matches->isNotEmpty()) {
                $this->assertReview($matches->first(), $definition);
            }
        }

        foreach ($manifest['interactions'] as $definition) {
            $matches = $workspace->assistantInteractions()
                ->where('origin', AssistantInteractionOrigin::Assistant)
                ->where('question', $definition['question'])
                ->with(['citations', 'reviewItem'])
                ->get();

            if ($matches->count() > 1) {
                throw new DemoProvisioningException('A canonical Demo interaction is ambiguous.');
            }

            if ($matches->isNotEmpty()) {
                $this->assertInteraction($matches->first(), $documents, $definition);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function ensureReview(Workspace $workspace, array $definition): ReviewItem
    {
        $matches = $workspace->reviewItems()->where('question', $definition['question'])->lockForUpdate()->get();

        if ($matches->count() > 1) {
            throw new DemoProvisioningException('A canonical Demo Review item is ambiguous.');
        }

        if ($matches->isNotEmpty()) {
            $review = $matches->first();
            $this->assertReview($review, $definition);

            return $review;
        }

        $resolved = $definition['status'] === ReviewItemStatus::Resolved->value;

        return $workspace->reviewItems()->create([
            'question' => $definition['question'],
            'deduplication_key' => $resolved ? null : hash('sha256', Str::lower(Str::squish($definition['question']))),
            'status' => $definition['status'],
            'resolution' => $definition['resolution'],
            'last_asked_at' => now(),
            'resolved_at' => $resolved ? now() : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function assertReview(ReviewItem $review, array $definition): void
    {
        if ($review->status->value !== $definition['status']
            || $review->resolution !== $definition['resolution']
            || ($review->status === ReviewItemStatus::Pending && $review->deduplication_key === null)
            || ($review->status === ReviewItemStatus::Resolved
                && ($review->deduplication_key !== null || $review->resolved_at === null))) {
            throw new DemoProvisioningException('A canonical Demo Review item is inconsistent.');
        }
    }

    /**
     * @param  array<string, Document>  $documents
     * @param  array<string, ReviewItem>  $reviews
     * @param  array<string, mixed>  $definition
     */
    private function ensureInteraction(
        Workspace $workspace,
        array $documents,
        array $reviews,
        array $definition,
    ): AssistantInteraction {
        $matches = $workspace->assistantInteractions()
            ->where('origin', AssistantInteractionOrigin::Assistant)
            ->where('question', $definition['question'])
            ->with(['citations', 'reviewItem'])
            ->lockForUpdate()
            ->get();

        if ($matches->count() > 1) {
            throw new DemoProvisioningException('A canonical Demo interaction is ambiguous.');
        }

        if ($matches->isNotEmpty()) {
            $interaction = $matches->first();
            $this->assertInteraction($interaction, $documents, $definition);

            return $interaction;
        }

        $answered = $definition['status'] === RagAnswerStatus::Answered->value;
        $review = $answered ? null : $reviews[$definition['question']];
        $interaction = $workspace->assistantInteractions()->create([
            'origin' => AssistantInteractionOrigin::Assistant,
            'review_item_id' => $review?->getKey(),
            'question' => $definition['question'],
            'status' => $definition['status'],
            'answer' => $definition['answer'],
            'provider' => $answered ? 'sift' : null,
            'model' => $answered ? 'prepared-demo-v1' : null,
        ]);

        if ($answered) {
            [$document, $chunk] = $this->citationSource($documents, $definition['citation']);
            $interaction->citations()->create([
                'document_id' => $document->getKey(),
                'document_chunk_id' => $chunk->getKey(),
                'original_filename' => $document->original_filename,
                'page_number' => $chunk->page_number,
                'chunk_index' => $chunk->chunk_index,
                'position' => 0,
            ]);
        }

        return $interaction->load(['citations', 'reviewItem']);
    }

    /**
     * @param  array<string, Document>  $documents
     * @param  array<string, mixed>  $definition
     */
    private function assertInteraction(
        AssistantInteraction $interaction,
        array $documents,
        array $definition,
    ): void {
        $answered = $definition['status'] === RagAnswerStatus::Answered->value;

        if ($interaction->status->value !== $definition['status']
            || $interaction->answer !== $definition['answer']
            || $interaction->origin !== AssistantInteractionOrigin::Assistant
            || ($answered && ($interaction->review_item_id !== null
                || $interaction->provider !== 'sift'
                || $interaction->model !== 'prepared-demo-v1'))
            || (! $answered && ($interaction->reviewItem?->question !== $definition['question']
                || $interaction->citations->isNotEmpty()))) {
            throw new DemoProvisioningException('A canonical Demo interaction is inconsistent.');
        }

        if (! $answered) {
            return;
        }

        [$document, $chunk] = $this->citationSource($documents, $definition['citation']);

        if ($interaction->citations->count() !== 1) {
            throw new DemoProvisioningException('A canonical Demo citation is missing or ambiguous.');
        }

        $citation = $interaction->citations->first();

        if ($citation->document_id !== $document->getKey()
            || $citation->document_chunk_id !== $chunk->getKey()
            || $citation->original_filename !== $document->original_filename
            || $citation->page_number !== $chunk->page_number
            || $citation->chunk_index !== $chunk->chunk_index
            || $citation->position !== 0) {
            throw new DemoProvisioningException('A canonical Demo citation is inconsistent.');
        }
    }

    /**
     * @param  array<string, Document>  $documents
     * @param  array<string, mixed>  $citation
     * @return array{Document, DocumentChunk}
     */
    private function citationSource(array $documents, array $citation): array
    {
        $document = $documents[$citation['storage_path']] ?? null;

        if (! $document instanceof Document) {
            throw new DemoProvisioningException('A canonical Demo citation document is unavailable.');
        }

        $chunks = $document->chunks()
            ->where('page_number', $citation['page_number'])
            ->where('content', 'like', '%'.$citation['phrase'].'%')
            ->get();

        if ($chunks->count() !== 1) {
            throw new DemoProvisioningException('A canonical Demo citation source is missing or ambiguous.');
        }

        return [$document, $chunks->first()];
    }

    /**
     * @param  array<string, mixed>  $manifest
     */
    private function assertExpectedCounts(Workspace $workspace, array $manifest): void
    {
        if ($workspace->documents()->count() !== count($manifest['documents'])
            || $workspace->assistantInteractions()->count() !== count($manifest['interactions'])
            || $workspace->reviewItems()->count() !== count($manifest['reviews'])
            || $workspace->reviewItems()->where('status', ReviewItemStatus::Pending)->count() !== 1) {
            throw new DemoProvisioningException('The provisioned Demo dataset did not pass its final count checks.');
        }
    }

    private function documentDisk(): string
    {
        $disk = trim((string) config('documents.disk'));

        if ($disk === '') {
            throw new DemoProvisioningException('The document storage disk is not configured.');
        }

        return $disk;
    }
}
