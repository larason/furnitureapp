<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->string('provider');
            $table->string('provider_event_id');
            $table->string('provider_correlation_id')->nullable();
            $table->string('event_type');
            $table->string('processing_status')->default('RECEIVED');

            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();

            $table->text('failure_reason')->nullable();

            $table->timestamps();

            $table->unique(['provider', 'provider_event_id']);
            $table->index('payment_id');
            $table->index(['provider', 'processing_status']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_events');
    }
};
