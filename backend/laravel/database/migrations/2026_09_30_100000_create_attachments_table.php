<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('furniture_request_id')
                ->nullable()
                ->constrained('furniture_requests')
                ->cascadeOnDelete();

            $table->foreignId('enquiry_id')
                ->nullable()
                ->constrained('enquiries')
                ->cascadeOnDelete();

            $table->string('storage_disk');
            $table->string('storage_key', 512);
            $table->string('filename', 255);
            $table->string('content_type', 100);
            $table->unsignedBigInteger('size');

            $table->timestamps();

            $table->unique('furniture_request_id');
            $table->unique('enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
