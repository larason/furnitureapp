<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('enquiry_reference', 16)->unique();

            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();

            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->restrictOnDelete();

            $table->string('name', 120);
            $table->string('email', 255)->nullable();
            $table->string('phone', 30)->nullable();

            $table->string('subject', 200);
            $table->text('message');

            $table->string('enquiry_status')->default('OPEN');

            $table->timestamps();

            $table->text('staff_internal_notes')->nullable();

            $table->index('user_id');
            $table->index('product_id');
            $table->index('order_id');
            $table->index(['enquiry_status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
