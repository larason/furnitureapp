<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->restoreSqliteCartGuards();
        }
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'sqlite') {
            $this->restoreSqliteCartGuards();
        }
    }

    private function restoreSqliteCartGuards(): void
    {
        $guards = [
            [
                'name' => 'ownership',
                'when' => 'NOT ((NEW.user_id IS NULL AND NEW.guest_token_digest IS NOT NULL) OR (NEW.user_id IS NOT NULL AND NEW.guest_token_digest IS NULL))',
                'message' => 'cart must be owned by either a customer or a guest, never both and never neither',
            ],
            [
                'name' => 'status',
                'when' => "NEW.status NOT IN ('ACTIVE', 'INACTIVE')",
                'message' => 'cart status must be ACTIVE or INACTIVE',
            ],
        ];

        foreach ($guards as $guard) {
            foreach (['insert', 'update'] as $operation) {
                $trigger = "trg_carts_{$guard['name']}_{$operation}";
                DB::statement("DROP TRIGGER IF EXISTS {$trigger}");
                DB::statement(<<<SQL
                    CREATE TRIGGER {$trigger}
                    BEFORE {$operation} ON carts
                    FOR EACH ROW
                    WHEN ({$guard['when']})
                    BEGIN
                        SELECT RAISE(ABORT, '{$guard['message']}');
                    END
                    SQL);
            }
        }
    }
};
