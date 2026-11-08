<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crawl_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('disk_path', 2048)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['crawl_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
