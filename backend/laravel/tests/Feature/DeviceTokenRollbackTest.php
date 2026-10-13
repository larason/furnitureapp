<?php

namespace Tests\Feature;

use App\Exceptions\MigrationPreconditionException;
use App\Models\DeviceInstallation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Rollback behaviour of the ACTIVE-only token constraint.
 *
 * `down()` must be safe to call when there is nothing to undo, because an operator or a
 * partial-failure recovery can attempt a rollback more than once. It must also keep
 * refusing to restore global uniqueness while retired rows legitimately share a
 * fingerprint, since that would mean deleting history.
 */
final class DeviceTokenRollbackTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIVE_INDEX = 'device_installations_active_token_unique';

    private const LEGACY_INDEX = 'device_installations_token_fingerprint_unique';

    private function amendment(): object
    {
        return require database_path(
            'migrations/2026_10_13_120000_scope_device_token_uniqueness_to_active_rows.php'
        );
    }

    /** The constraint is a column on MySQL and an index elsewhere, so probe by name. */
    private function hasActiveConstraint(): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return Schema::hasColumn('device_installations', 'active_token_fingerprint');
        }

        return DB::selectOne(
            "SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?",
            [self::ACTIVE_INDEX],
        ) !== null;
    }

    public function test_down_is_a_no_op_when_the_constraint_is_already_absent(): void
    {
        $this->assertTrue($this->hasActiveConstraint());

        // Undo the constraint exactly as a completed rollback would have.
        $this->amendment()->down();
        $this->assertFalse($this->hasActiveConstraint());

        // A second attempt has nothing left to undo and must not fail.
        $this->amendment()->down();
        $this->amendment()->down();

        $this->assertTrue(Schema::hasTable('device_installations'));
    }

    public function test_rollback_then_forward_migration_restores_the_constraint(): void
    {
        $this->amendment()->down();
        $this->assertFalse($this->hasActiveConstraint());

        $this->amendment()->up();
        $this->assertTrue($this->hasActiveConstraint());
    }

    public function test_rollback_is_refused_while_rows_share_a_fingerprint(): void
    {
        $customer = User::factory()->customer()->create();
        $other = User::factory()->customer()->create();
        DeviceInstallation::factory()->forOwner($customer)->inactive()->withToken('rollback-shared')->create();
        DeviceInstallation::factory()->forOwner($other)->inactive()->withToken('rollback-shared')->create();

        $this->expectException(MigrationPreconditionException::class);
        try {
            $this->amendment()->down();
        } finally {
            // The constraint and the history must both survive a refused rollback.
            $this->assertTrue($this->hasActiveConstraint());
            $this->assertSame(2, DeviceInstallation::query()->count());
        }
    }

    public function test_a_rollback_with_no_shared_fingerprints_succeeds(): void
    {
        $customer = User::factory()->customer()->create();
        DeviceInstallation::factory()->forOwner($customer)->create();

        // No duplicates, so restoring global uniqueness is lossless.
        $this->amendment()->down();

        $this->assertFalse($this->hasActiveConstraint());
        $this->assertSame(1, DeviceInstallation::query()->count());
    }
}
