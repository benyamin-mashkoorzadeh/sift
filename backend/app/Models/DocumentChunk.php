<?php

namespace App\Models;

use App\Casts\EmbeddingVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'document_id',
        'content',
        'page_number',
        'chunk_index',
        'embedding',
        'embedding_provider',
        'embedding_model',
        'embedded_at',
        'metadata',
    ];

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'chunk_index' => 'integer',
            'embedding' => EmbeddingVector::class,
            'embedded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
