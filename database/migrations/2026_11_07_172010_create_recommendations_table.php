<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_analysis_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20)->default('heuristic');
            $table->string('title');
            $table->text('description');
            $table->text('rationale');
            $table->json('action_steps')->nullable();
            $table->json('ab_test')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->json('evidence_refs')->nullable();
            $table->boolean('is_validated')->default(false);
            $table->text('validation_notes')->nullable();
            $table->timestamps();

            $table->index(['crawl_id', 'priority']);
            $table->index(['workspace_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
