<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WidgetConversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'workspace_id',
        'session_token_hash',
        'expires_at',
        'last_activity_at',
    ];

    protected $hidden = [
        'session_token_hash',
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
            'expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }
}
