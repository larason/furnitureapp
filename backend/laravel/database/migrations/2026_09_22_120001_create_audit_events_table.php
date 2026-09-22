<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->string('actor_role')->nullable();
            $table->string('action');
            $table->string('resource_type');
            $table->string('resource_id');

            $table->json('previous_state')->nullable();
            $table->json('resulting_state')->nullable();

            $table->string('request_id')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            $table->index(['resource_type', 'resource_id']);
            $table->index(['actor_id', 'occurred_at']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
