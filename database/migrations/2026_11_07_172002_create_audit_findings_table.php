<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_key', 80);
            $table->string('category', 30);
            $table->string('title');
            $table->text('description');
            $table->string('severity', 20);
            $table->unsignedTinyInteger('confidence');
            $table->json('evidence')->nullable();
            $table->text('recommended_improvement');
            $table->string('fingerprint', 64);
            $table->string('status', 20)->default('open');
            $table->string('source', 20)->default('rule');
            $table->timestamps();

            $table->unique(['crawl_id', 'fingerprint']);
            $table->index(['crawl_id', 'category', 'severity']);
            $table->index(['workspace_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
    }
};
