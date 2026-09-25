<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use DomainException;
use Illuminate\Database\DeadlockException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderDeliverySnapshotConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const STATUS_OK = 0;

    private const STATUS_LOCKED = 1;

    private const STATUS_REJECTED = 2;

    private const STATUS_ERROR = 3;

    private string $connection = 'sqlite_delivery_snapshot';

    public function test_concurrent_delivery_creation_and_snapshot_update_serialize(): void
    {
        $this->runRace('updateDeliverySnapshot');
    }

    public function test_concurrent_delivery_creation_and_plain_save_serialize(): void
    {
        $this->runRace('plainSave');
    }

    private function runRace(string $mode): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl extension required.');
        }

        $dbFile = tempnam(sys_get_temp_dir(), 'delivery-snapshot-');
        $this->assertNotFalse($dbFile);
        $barrier = tempnam(sys_get_temp_dir(), 'delivery-barrier-');
        $this->assertNotFalse($barrier);

        $this->registerConnection($dbFile);
        Artisan::call('migrate:fresh', ['--database' => $this->connection, '--force' => true]);
        DB::connection($this->connection)->statement('PRAGMA busy_timeout=10000');

        $order = $this->createOrderOn();

        $pid = pcntl_fork();
        $this->assertNotSame(-1, $pid, 'pcntl_fork failed');

        if ($pid === 0) {
            $code = self::STATUS_ERROR;

            try {
                $this->waitForBarrier($barrier);
                $code = $this->runCreateDelivery();
            } finally {
                exit($code);
            }
        }

        $this->releaseBarrier($barrier);
        $parentCode = $mode === 'updateDeliverySnapshot'
            ? $this->runSnapshotUpdate($order->id)
            : $this->runPlainSave($order->id);

        pcntl_waitpid($pid, $status);
        $childCode = pcntl_wexitstatus($status);

        DB::purge($this->connection);
        $this->assertTrue($this->isKnownOutcome($parentCode), "Unexpected parent outcome: $parentCode");
        $this->assertTrue($this->isKnownOutcome($childCode), "Unexpected child outcome: $childCode");
        $this->assertTrue(
            $parentCode === self::STATUS_OK || $childCode === self::STATUS_OK,
            'Neither operation succeeded; the race did not make progress.'
        );
        $this->assertFinalState($order->id, $parentCode, $childCode);

        @unlink($dbFile);
        @unlink($dbFile.'-wal');
        @unlink($dbFile.'-shm');
        @unlink($barrier);
    }

    private function waitForBarrier(string $barrier): void
    {
        while (file_exists($barrier)) {
            usleep(1_000);
        }
    }

    private function releaseBarrier(string $barrier): void
    {
        @unlink($barrier);
    }

    private function runCreateDelivery(): int
    {
        $error = null;

        try {
            $order = Order::on($this->connection)->firstOrFail();
            $order->createDelivery();
        } catch (\Throwable $e) {
            $error = $e;
        }

        return $this->statusOf($error);
    }

    private function runSnapshotUpdate(int $orderId): int
    {
        $error = null;

        try {
            $order = Order::on($this->connection)->find($orderId);
            $order->updateDeliverySnapshot(function (Order $locked): void {
                $locked->recipient_name = 'Changed Name';
            });
        } catch (\Throwable $e) {
            $error = $e;
        }

        return $this->statusOf($error);
    }

    private function runPlainSave(int $orderId): int
    {
        $error = null;

        try {
            $order = Order::on($this->connection)->find($orderId);
            $order->recipient_name = 'Changed Name';
            $order->save();
        } catch (\Throwable $e) {
            $error = $e;
        }

        return $this->statusOf($error);
    }

    private function statusOf(?\Throwable $error): int
    {
        return match (true) {
            $error === null => self::STATUS_OK,
            $error instanceof DeadlockException => self::STATUS_LOCKED,
            $error instanceof QueryException => self::STATUS_LOCKED,
            $error instanceof DomainException => self::STATUS_REJECTED,
            default => self::STATUS_ERROR,
        };
    }

    private function isKnownOutcome(int $code): bool
    {
        return in_array($code, [self::STATUS_OK, self::STATUS_LOCKED, self::STATUS_REJECTED], true);
    }

    private function assertFinalState(int $orderId, int $parentCode, int $childCode): void
    {
        $order = Order::on($this->connection)->find($orderId);
        $delivery = Delivery::on($this->connection)->where('order_id', $orderId)->first();

        if ($delivery !== null) {
            $this->assertSame($order->recipient_name, $delivery->recipient_name);
            $this->assertSame($order->recipient_phone, $delivery->recipient_phone);
            $this->assertSame($order->delivery_address, $delivery->delivery_address);
        }

        if ($parentCode === self::STATUS_OK) {
            $this->assertSame('Changed Name', $order->recipient_name);
        }

        if ($childCode === self::STATUS_OK) {
            $this->assertNotNull($delivery);
        }
    }

    private function createOrderOn(): Order
    {
        DB::connection($this->connection)->table('users')->insert([
            'id' => 1,
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'hashed',
        ]);

        $order = new Order;
        $order->setConnection($this->connection);
        $order->forceFill([
            'customer_id' => 1,
            'order_reference' => 'OD-ABCDE',
            'status' => 'PENDING_PAYMENT',
            'fulfillment_type' => 'DELIVERY',
            'delivery_fee_status' => 'FINALIZED',
            'currency' => 'TZS',
            'subtotal_amount' => 10000,
            'delivery_fee_amount' => 0,
            'total_amount' => 10000,
            'recipient_name' => 'Original',
            'recipient_phone' => '+255700000000',
            'delivery_address' => ['address_line' => '1 St', 'city' => 'Dar es Salaam'],
        ]);
        $order->save();

        return $order;
    }

    private function registerConnection(string $dbFile): void
    {
        config(["database.connections.$this->connection" => [
            'driver' => 'sqlite',
            'database' => $dbFile,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
    }
}
