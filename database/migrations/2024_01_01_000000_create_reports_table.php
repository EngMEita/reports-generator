<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create(config('reports-generator.table', 'reports'), function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('connection')->nullable();
            $table->text('description')->nullable();
            $table->text('base_query');
            $table->json('filters')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('cache_ttl')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('reports-generator.table', 'reports'));
    }
};
