<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widget_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->char('session_token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('last_activity_at');
            $table->timestamps();

            $table->index(['workspace_id', 'last_activity_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE widget_conversations
            ADD CONSTRAINT widget_conversations_session_token_hash_valid CHECK (
                session_token_hash ~ '^[0-9a-f]{64}$'
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('widget_conversations');
    }
};
