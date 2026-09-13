<?php

use App\Support\CategoryRecommendationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_recommendations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('recommended_category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->enum('relation_type', array_map(
                static fn (CategoryRecommendationType $type) => $type->value,
                CategoryRecommendationType::cases()
            ));

            $table->integer('priority')->default(1);

            $table->timestamps();

            $table->unique(['category_id', 'recommended_category_id'], 'cat_rec_unique');

            $table->index(['category_id', 'relation_type', 'priority'], 'cat_rec_type_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_recommendations');
    }
};
