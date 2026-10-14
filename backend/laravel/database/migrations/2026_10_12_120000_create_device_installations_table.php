<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer device installations registered for push delivery.
 *
 * The registration token is stored encrypted because delivery later needs to
 * recover it. Uniqueness is enforced on an irreversible keyed fingerprint so a
 * single token can never be associated with two owners, including under
 * concurrent registration.
 *
 * Rows are deactivated rather than deleted so a replayed message cannot
 * resurrect a retired association.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('platform', 16);
            $table->string('app_instance_id', 128);
            $table->text('token_ciphertext');
            $table->char('token_fingerprint', 64);
            $table->string('status', 16)->default('ACTIVE');
            $table->string('app_version', 64)->nullable();
            $table->string('app_build', 64)->nullable();
            $table->timestamp('last_registered_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();

            $table->unique('token_fingerprint', 'device_installations_token_fingerprint_unique');
            $table->unique(['user_id', 'app_instance_id'], 'device_installations_user_instance_unique');
            $table->index(['user_id', 'status'], 'device_installations_user_status_index');
            $table->index(['status', 'updated_at'], 'device_installations_status_updated_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_installations');
    }
};
