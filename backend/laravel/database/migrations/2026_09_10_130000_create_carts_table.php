<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->char('guest_token_digest', 64)->nullable();

            $table->string('status')->default('ACTIVE');

            $table->unsignedBigInteger('active_user_guard')->nullable()->storedAs('CASE WHEN `status` = \'ACTIVE\' AND `user_id` IS NOT NULL THEN `user_id` ELSE NULL END');

            $table->timestamps();

            $table->unique('guest_token_digest', 'carts_guest_token_digest_unique');
            $table->unique('active_user_guard', 'carts_unique_active_per_user');

            $table->index(['user_id', 'status']);
            $table->index(['status', 'updated_at']);
        });

        $this->addOwnershipExclusiveConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }

    private function addOwnershipExclusiveConstraint(): void
    {
        $driver = DB::getDriverName();
        $check = <<<'SQL'
            (user_id IS NULL AND guest_token_digest IS NOT NULL)
            OR (user_id IS NOT NULL AND guest_token_digest IS NULL)
            SQL;

        if ($driver === 'mysql') {
            DB::statement(<<<SQL
                ALTER TABLE carts
                    ADD CONSTRAINT chk_cart_ownership_exclusive
                    CHECK ({$check})
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $guard = <<<'SQL'
            WHEN NOT (
                (NEW.user_id IS NULL AND NEW.guest_token_digest IS NOT NULL)
                OR (NEW.user_id IS NOT NULL AND NEW.guest_token_digest IS NULL)
            )
            BEGIN
                SELECT RAISE(ABORT, 'cart must be owned by either a customer or a guest, never both and never neither');
            END
            SQL;

        DB::statement(<<<SQL
            CREATE TRIGGER trg_carts_ownership_insert
            BEFORE INSERT ON carts
            FOR EACH ROW
            {$guard}
            SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_carts_ownership_update
            BEFORE UPDATE ON carts
            FOR EACH ROW
            {$guard}
            SQL);
    }
};
