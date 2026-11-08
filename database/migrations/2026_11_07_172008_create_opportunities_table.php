<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained('audit_findings')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category', 30);
            $table->unsignedTinyInteger('impact');
            $table->unsignedTinyInteger('confidence');
            $table->unsignedTinyInteger('reach');
            $table->unsignedTinyInteger('funnel_relevance');
            $table->unsignedTinyInteger('effort');
            $table->decimal('score', 10, 2);
            $table->json('evidence')->nullable();
            $table->json('heuristic')->nullable();
            $table->unsignedInteger('rank')->default(0);
            $table->timestamps();

            $table->index(['crawl_id', 'score']);
            $table->index(['workspace_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunities');
    }
};
