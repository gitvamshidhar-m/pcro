<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->text('url');
            $table->char('url_hash', 64);
            $table->string('path', 1024)->nullable();
            $table->string('page_type', 30)->default('other');
            $table->unsignedTinyInteger('depth')->default(0);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('title', 500)->nullable();
            $table->text('meta_description')->nullable();
            $table->text('canonical_url')->nullable();
            $table->text('h1')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->char('content_hash', 64)->nullable();
            $table->boolean('is_rendered')->default(false);
            $table->json('extraction')->nullable();
            $table->string('screenshot_path', 2048)->nullable();
            $table->timestamps();

            $table->unique(['crawl_id', 'url_hash']);
            $table->index(['workspace_id', 'page_type']);
            $table->index(['crawl_id', 'page_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
