<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('review_item_id')->nullable()->constrained()->nullOnDelete();
            $table->text('question');
            $table->enum('status', ['answered', 'needs_review']);
            $table->text('answer');
            $table->string('provider', 100)->nullable();
            $table->string('model')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status', 'created_at']);
        });

        Schema::create('assistant_interaction_citations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_interaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_chunk_id')->nullable()->constrained('document_chunks')->nullOnDelete();
            $table->string('original_filename');
            $table->unsignedInteger('page_number');
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['assistant_interaction_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE assistant_interactions
            ADD CONSTRAINT assistant_interactions_question_nonblank CHECK (NULLIF(BTRIM(question), '') IS NOT NULL),
            ADD CONSTRAINT assistant_interactions_answer_nonblank CHECK (NULLIF(BTRIM(answer), '') IS NOT NULL),
            ADD CONSTRAINT assistant_interactions_state_consistent CHECK (
                (
                    status = 'answered'
                    AND review_item_id IS NULL
                    AND NULLIF(BTRIM(provider), '') IS NOT NULL
                    AND NULLIF(BTRIM(model), '') IS NOT NULL
                )
                OR
                (
                    status = 'needs_review'
                    AND review_item_id IS NOT NULL
                )
            )
            SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE assistant_interaction_citations
            ADD CONSTRAINT assistant_interaction_citations_filename_nonblank CHECK (NULLIF(BTRIM(original_filename), '') IS NOT NULL),
            ADD CONSTRAINT assistant_interaction_citations_page_positive CHECK (page_number > 0),
            ADD CONSTRAINT assistant_interaction_citations_chunk_index_non_negative CHECK (chunk_index >= 0),
            ADD CONSTRAINT assistant_interaction_citations_position_non_negative CHECK (position >= 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_interaction_citations');
        Schema::dropIfExists('assistant_interactions');
    }
};
