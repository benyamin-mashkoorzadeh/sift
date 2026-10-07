<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assistant_interactions', function (Blueprint $table): void {
            $table->dropForeign(['review_item_id']);
            $table->foreign('review_item_id')
                ->references('id')
                ->on('review_items')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assistant_interactions', function (Blueprint $table): void {
            $table->dropForeign(['review_item_id']);
            $table->foreign('review_item_id')
                ->references('id')
                ->on('review_items')
                ->nullOnDelete();
        });
    }
};
