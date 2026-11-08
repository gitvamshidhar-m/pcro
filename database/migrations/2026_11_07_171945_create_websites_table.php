<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('websites', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('domain')->index();
            $table->string('url', 2048);
            $table->string('label')->nullable();
            $table->string('favicon', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('websites');
    }
};
