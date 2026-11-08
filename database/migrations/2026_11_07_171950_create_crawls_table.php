<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crawls', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('queued');
            $table->string('stage', 40)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedTinyInteger('pages_requested')->default(30);
            $table->unsignedTinyInteger('max_depth')->default(3);
            $table->unsignedSmallInteger('pages_discovered')->default(0);
            $table->unsignedSmallInteger('pages_crawled')->default(0);
            $table->unsignedSmallInteger('pages_failed')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->string('worker_output_path', 2048)->nullable();
            $table->json('summary')->nullable();
            $table->json('settings')->nullable();
            $table->decimal('cro_score', 5, 2)->nullable();
            $table->decimal('friction_score', 5, 2)->nullable();
            $table->string('scoring_version', 40)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['project_id', 'created_at']);
            $table->index(['status', 'queued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crawls');
    }
};
