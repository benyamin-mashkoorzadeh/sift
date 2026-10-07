<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('document_id');
            $table->text('content');
            $table->unsignedInteger('page_number');
            $table->unsignedInteger('chunk_index');
            $table->vector('embedding')->nullable();
            $table->string('embedding_provider', 100)->nullable();
            $table->string('embedding_model')->nullable();
            $table->timestampTz('embedded_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign(['document_id', 'workspace_id'])
                ->references(['id', 'workspace_id'])
                ->on('documents')
                ->cascadeOnDelete();
            $table->unique(['document_id', 'chunk_index']);
            $table->index(['workspace_id', 'document_id']);
            $table->index(['document_id', 'page_number', 'chunk_index']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE document_chunks
            ADD CONSTRAINT document_chunks_content_not_empty CHECK (btrim(content) <> ''),
            ADD CONSTRAINT document_chunks_page_number_positive CHECK (page_number > 0),
            ADD CONSTRAINT document_chunks_chunk_index_non_negative CHECK (chunk_index >= 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
