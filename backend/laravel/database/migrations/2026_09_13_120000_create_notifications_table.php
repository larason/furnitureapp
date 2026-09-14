<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recipient_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('type', 80);
            $table->string('title', 255);
            $table->text('message');

            $table->json('target')->nullable();

            $table->string('source_type', 80)->nullable();
            $table->string('source_id', 36)->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index('recipient_user_id');
            $table->index(['recipient_user_id', 'created_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['recipient_user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
