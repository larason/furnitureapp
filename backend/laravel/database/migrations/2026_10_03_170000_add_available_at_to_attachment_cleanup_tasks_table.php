<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('attachment_cleanup_tasks', function (Blueprint $table): void {
                $table->dateTime('available_at')->nullable();
            });

            DB::table('attachment_cleanup_tasks')->update(['available_at' => now()]);

            Schema::table('attachment_cleanup_tasks', function (Blueprint $table): void {
                $table->index('available_at');
            });

            return;
        }

        Schema::table('attachment_cleanup_tasks', function (Blueprint $table): void {
            $table->timestamp('available_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::table('attachment_cleanup_tasks', function (Blueprint $table): void {
            $table->dropIndex(['available_at']);
            $table->dropColumn('available_at');
        });
    }
};
