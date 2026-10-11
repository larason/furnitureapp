<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_email_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->restrictOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('channel', 16);
            $table->string('status', 16);
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->string('error_category', 80)->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'channel'], 'notification_email_delivery_unique');
            $table->index(['status', 'last_attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_email_deliveries');
    }
};
