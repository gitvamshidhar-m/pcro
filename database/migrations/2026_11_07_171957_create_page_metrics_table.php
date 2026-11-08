<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_metrics', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->json('lighthouse')->nullable();
            $table->json('axe')->nullable();
            $table->json('vitals')->nullable();
            $table->json('image_stats')->nullable();
            $table->json('js_errors')->nullable();
            $table->json('broken_links')->nullable();
            $table->json('availability')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->unique('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_metrics');
    }
};
