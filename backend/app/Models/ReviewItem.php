<?php

namespace App\Models;

use App\Enums\ReviewItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'question',
        'deduplication_key',
        'status',
        'resolution',
        'last_asked_at',
        'resolved_at',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return HasMany<AssistantInteraction, $this>
     */
    public function assistantInteractions(): HasMany
    {
        return $this->hasMany(AssistantInteraction::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReviewItemStatus::class,
            'last_asked_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
