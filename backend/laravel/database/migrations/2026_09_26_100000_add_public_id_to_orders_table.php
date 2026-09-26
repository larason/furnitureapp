<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->char('public_id', 26)->nullable()->unique()->after('id');
        });

        DB::table('orders')
            ->whereNull('public_id')
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['public_id' => strtolower((string) Str::ulid())]);
                }
            });

        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_orders_public_id_required_insert
                BEFORE INSERT ON orders
                FOR EACH ROW
                WHEN NEW.public_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'orders.public_id is required');
                END
                SQL);

            DB::statement(<<<'SQL'
                CREATE TRIGGER trg_orders_public_id_required_update
                BEFORE UPDATE OF public_id ON orders
                FOR EACH ROW
                WHEN NEW.public_id IS NULL
                BEGIN
                    SELECT RAISE(ABORT, 'orders.public_id is required');
                END
                SQL);

            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->char('public_id', 26)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS trg_orders_public_id_required_insert');
            DB::statement('DROP TRIGGER IF EXISTS trg_orders_public_id_required_update');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn('public_id');
        });
    }
};
