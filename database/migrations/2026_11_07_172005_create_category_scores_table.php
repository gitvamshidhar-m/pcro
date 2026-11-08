<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_scores', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->decimal('score', 5, 2);
            $table->decimal('weight', 5, 4);
            $table->json('components')->nullable();
            $table->string('rule_version', 40);
            $table->timestamps();

            $table->unique(['crawl_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_scores');
    }
};
