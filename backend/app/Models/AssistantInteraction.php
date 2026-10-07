<?php

namespace App\Models;

use App\Enums\AssistantInteractionOrigin;
use App\Enums\RagAnswerStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class AssistantInteraction extends Model
{
    use HasFactory;

    protected $attributes = [
        'origin' => 'assistant',
    ];

    protected $fillable = [
        'workspace_id',
        'origin',
        'review_item_id',
        'widget_conversation_id',
        'question',
        'status',
        'answer',
        'provider',
        'model',
    ];

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<ReviewItem, $this>
     */
    public function reviewItem(): BelongsTo
    {
        return $this->belongsTo(ReviewItem::class);
    }

    /**
     * @return BelongsTo<WidgetConversation, $this>
     */
    public function widgetConversation(): BelongsTo
    {
        return $this->belongsTo(WidgetConversation::class);
    }

    /**
     * @return HasMany<AssistantInteractionCitation, $this>
     */
    public function citations(): HasMany
    {
        return $this->hasMany(AssistantInteractionCitation::class)->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => AssistantInteractionOrigin::class,
            'status' => RagAnswerStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $interaction): void {
            if ($interaction->exists
                && ! $interaction->isDirty(['workspace_id', 'origin', 'widget_conversation_id'])) {
                return;
            }

            if ($interaction->origin === AssistantInteractionOrigin::Assistant) {
                if ($interaction->widget_conversation_id !== null) {
                    throw new LogicException('Assistant interactions cannot reference widget conversations.');
                }

                return;
            }

            if ($interaction->origin !== AssistantInteractionOrigin::Widget
                || $interaction->widget_conversation_id === null) {
                throw new LogicException('Widget interactions require a widget conversation.');
            }

            $conversationBelongsToWorkspace = WidgetConversation::query()
                ->whereKey($interaction->widget_conversation_id)
                ->where('workspace_id', $interaction->workspace_id)
                ->exists();

            if (! $conversationBelongsToWorkspace) {
                throw new LogicException('Widget interactions and conversations must belong to the same workspace.');
            }
        });
    }
}
