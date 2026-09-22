<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();

            // Ephemeral actor-scoped retry record: cascade with the actor.
            // Audit history is immutable and uses restrictOnDelete instead.
            $table->foreignId('actor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('action');
            $table->char('key_hash', 64);
            $table->char('request_fingerprint', 64);

            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();

            $table->timestamp('expires_at');

            $table->timestamps();

            $table->unique(['actor_id', 'action', 'key_hash']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
