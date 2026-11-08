<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_predictions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('model_name', 100);
            $table->string('model_version', 40);
            $table->json('prediction')->nullable();
            $table->decimal('score', 10, 4)->nullable();
            $table->timestamp('predicted_at');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['website_id', 'model_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_predictions');
    }
};
