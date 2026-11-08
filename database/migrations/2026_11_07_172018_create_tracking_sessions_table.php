<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('website_id')->constrained()->cascadeOnDelete();
            $table->string('session_key', 100)->unique();
            $table->string('visitor_hash', 64)->index();
            $table->string('landing_page', 2048)->nullable();
            $table->string('referrer', 2048)->nullable();
            $table->json('utm')->nullable();
            $table->json('device')->nullable();
            $table->json('consent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['website_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_sessions');
    }
};
