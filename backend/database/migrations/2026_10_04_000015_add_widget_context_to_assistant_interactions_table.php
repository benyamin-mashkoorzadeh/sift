<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_interactions', function (Blueprint $table): void {
            $table->string('origin', 32)->default('assistant')->after('workspace_id');
            $table->foreignId('widget_conversation_id')
                ->nullable()
                ->after('review_item_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['workspace_id', 'origin', 'created_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE assistant_interactions
            ADD CONSTRAINT assistant_interactions_origin_valid CHECK (
                origin IN ('assistant', 'widget')
            ),
            ADD CONSTRAINT assistant_interactions_assistant_origin_context_valid CHECK (
                origin <> 'assistant' OR widget_conversation_id IS NULL
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE assistant_interactions
            DROP CONSTRAINT IF EXISTS assistant_interactions_assistant_origin_context_valid,
            DROP CONSTRAINT IF EXISTS assistant_interactions_origin_valid
            SQL);

        Schema::table('assistant_interactions', function (Blueprint $table): void {
            $table->dropIndex(['workspace_id', 'origin', 'created_at']);
            $table->dropConstrainedForeignId('widget_conversation_id');
            $table->dropColumn('origin');
        });
    }
};
