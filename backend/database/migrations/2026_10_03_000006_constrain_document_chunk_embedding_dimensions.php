<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('document_chunks')->update([
            'embedding' => null,
            'embedding_provider' => null,
            'embedding_model' => null,
            'embedded_at' => null,
        ]);

        DB::statement(<<<'SQL'
            ALTER TABLE document_chunks
            ALTER COLUMN embedding TYPE vector(1024)
            USING embedding::vector(1024)
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE document_chunks
            ALTER COLUMN embedding TYPE vector
            USING embedding::vector
            SQL);
    }
};
