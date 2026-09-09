<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku_prefix')->nullable()->unique();

            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            $table->string('brand')->nullable();
            $table->string('room_type')->nullable();
            $table->enum('assembly_required', ['none', 'partial', 'full'])->default('none');
            $table->string('primary_material')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);

            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['is_active', 'is_featured']);
            $table->index('room_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
