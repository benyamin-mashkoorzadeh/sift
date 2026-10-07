<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('original_filename');
            $table->string('storage_disk', 100);
            $table->string('storage_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
            $table->unsignedInteger('page_count')->nullable();
            $table->text('processing_error')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['storage_disk', 'storage_path']);
            $table->index(['workspace_id', 'status']);
            $table->unique(['id', 'workspace_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE documents
            ADD CONSTRAINT documents_size_bytes_non_negative CHECK (size_bytes >= 0),
            ADD CONSTRAINT documents_page_count_positive CHECK (page_count IS NULL OR page_count > 0)
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
