<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('furniture_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('request_reference', 16)->unique();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->json('product_details');

            $table->string('style', 200);

            $table->string('name', 120);
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();

            $table->text('message');

            $table->unsignedInteger('quantity')->nullable();

            $table->json('dimensions')->nullable();

            $table->string('material', 500)->nullable();
            $table->string('color', 200)->nullable();

            $table->string('request_status')->default('SUBMITTED');

            $table->timestamps();

            $table->text('staff_internal_notes')->nullable();

            $table->index('user_id');
            $table->index(['request_status', 'created_at']);
            $table->index('product_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('furniture_requests');
    }
};
