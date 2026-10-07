<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('public_key', 64)->unique();
            $table->boolean('enabled')->default(false);
            $table->timestampTz('key_rotated_at')->nullable();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE workspace_widgets
            ADD CONSTRAINT workspace_widgets_public_key_valid CHECK (
                public_key ~ '^sift_w_[A-Za-z0-9_-]{43}$'
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_widgets');
    }
};
