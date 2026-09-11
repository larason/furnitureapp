<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->string('payment_reference', 12)->unique();

            $table->string('provider');
            $table->string('method');
            $table->string('status')->default('PENDING');

            $table->unsignedBigInteger('amount');
            $table->char('currency', 3)->default('TZS');

            $table->string('provider_transaction_id')->nullable();
            $table->string('provider_reference')->nullable();

            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();

            $table->timestamp('initiated_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['order_id', 'created_at']);
            $table->unique(['provider', 'provider_transaction_id']);
        });

        $this->addNonNegativeAmountConstraint();
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }

    private function addNonNegativeAmountConstraint(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE payments
                    ADD CONSTRAINT chk_payments_amount_non_negative
                    CHECK (amount >= 0)
                SQL);

            return;
        }

        if ($driver !== 'sqlite') {
            return;
        }

        $guard = <<<'SQL'
            WHEN (NEW.amount < 0)
            BEGIN
                SELECT RAISE(ABORT, 'payment amount must not be negative');
            END
            SQL;

        DB::statement(<<<SQL
            CREATE TRIGGER trg_payments_amount_insert
            BEFORE INSERT ON payments
            FOR EACH ROW
            {$guard}
            SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER trg_payments_amount_update
            BEFORE UPDATE ON payments
            FOR EACH ROW
            {$guard}
            SQL);
    }
};
