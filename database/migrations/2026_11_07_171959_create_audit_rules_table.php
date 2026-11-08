<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_rules', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('rule_key', 80)->unique();
            $table->string('category', 30);
            $table->string('name');
            $table->text('description');
            $table->string('severity', 20);
            $table->decimal('weight', 5, 2)->default(1);
            $table->json('config')->nullable();
            $table->string('rule_version', 40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_rules');
    }
};
