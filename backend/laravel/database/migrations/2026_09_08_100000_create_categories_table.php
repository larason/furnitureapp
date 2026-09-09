<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();

            $table->enum('space_type', ['home', 'office', 'hybrid'])->default('home');

            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['parent_id', 'display_order']);
            $table->index(['is_active', 'display_order']);
            $table->index('space_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
