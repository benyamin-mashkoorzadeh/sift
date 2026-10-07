<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantInteractionCitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'assistant_interaction_id',
        'document_id',
        'document_chunk_id',
        'original_filename',
        'page_number',
        'chunk_index',
        'position',
    ];

    /**
     * @return BelongsTo<AssistantInteraction, $this>
     */
    public function assistantInteraction(): BelongsTo
    {
        return $this->belongsTo(AssistantInteraction::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<DocumentChunk, $this>
     */
    public function documentChunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'chunk_index' => 'integer',
            'position' => 'integer',
        ];
    }
}
