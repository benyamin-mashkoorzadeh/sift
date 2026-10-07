<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->char('deduplication_key', 64)->nullable();
            $table->enum('status', ['pending', 'resolved'])->default('pending');
            $table->text('resolution')->nullable();
            $table->timestampTz('last_asked_at');
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'deduplication_key']);
            $table->index(['workspace_id', 'status', 'last_asked_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE review_items
            ADD CONSTRAINT review_items_question_nonblank CHECK (NULLIF(BTRIM(question), '') IS NOT NULL),
            ADD CONSTRAINT review_items_state_consistent CHECK (
                (
                    status = 'pending'
                    AND resolution IS NULL
                    AND resolved_at IS NULL
                    AND deduplication_key IS NOT NULL
                )
                OR
                (
                    status = 'resolved'
                    AND NULLIF(BTRIM(resolution), '') IS NOT NULL
                    AND resolved_at IS NOT NULL
                    AND deduplication_key IS NULL
                )
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('review_items');
    }
};
