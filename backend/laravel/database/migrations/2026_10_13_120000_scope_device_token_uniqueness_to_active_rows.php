<?php

use App\Exceptions\MigrationPreconditionException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Constrain device registrations to one ACTIVE owner per token fingerprint.
 *
 * The previous schema enforced a global unique index on `token_fingerprint`, so a
 * token could never be registered again once any customer had held it — including after
 * that customer retired it. A device handed to a new customer could therefore never
 * register for push, because the retired row permanently reserved the fingerprint.
 *
 * Uniqueness becomes ACTIVE-only. Retired rows keep their stored fingerprint and their
 * original `user_id`, so the record of which customer once held the device is preserved:
 * no historical row is deleted or rewritten. Because a unique index ignores NULLs,
 * expressing "the fingerprint of a live registration" as NULL for retired rows yields
 * exactly the required behaviour.
 *
 * Three engines are supported because they cannot express this the same way:
 *
 * - MySQL 8: a STORED generated column plus a unique index.
 * - MariaDB: MariaDB rejects every conditional function in a generated column
 *   expression, so a nullable column populated by BEFORE INSERT/UPDATE triggers carries the
 *   same value and the unique index still does the enforcement. The trigger always
 *   overwrites the column, so it cannot drift from `status`.
 * - SQLite: a partial unique index, which states the rule directly.
 *
 * This migration is additive. The previous global index already guaranteed that no
 * fingerprint was duplicated, so the new ACTIVE-only constraint cannot fail on existing
 * data.
 */
return new class extends Migration
{
    private const ACTIVE_INDEX = 'device_installations_active_token_unique';

    private const LEGACY_INDEX = 'device_installations_token_fingerprint_unique';

    private const ACTIVE_COLUMN = 'active_token_fingerprint';

    private const INSERT_TRIGGER = 'trg_device_installations_active_token_insert';

    private const UPDATE_TRIGGER = 'trg_device_installations_active_token_update';

    public function up(): void
    {
        if (! Schema::hasTable('device_installations') || $this->hasActiveTokenConstraint()) {
            return;
        }

        if ($this->isMariaDb()) {
            $this->useTriggerMaintainedColumn();

            return;
        }

        match (DB::getDriverName()) {
            'mysql' => $this->useGeneratedColumn(),
            'sqlite' => $this->usePartialIndex(),
            // An unsupported driver keeps the legacy global constraint rather than
            // silently losing the single-owner guarantee.
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasTable('device_installations')) {
            return;
        }

        // Nothing to undo unless this migration actually applied its constraint.
        // Without this guard a re-run of down() on an already-rolled-back schema
        // drops an index and a column that are absent, which fails hard.
        if (! $this->hasActiveTokenConstraint()) {
            return;
        }

        $this->assertNoSharedFingerprints();

        if (DB::getDriverName() === 'mysql') {
            // Hardening, not a fix: the completed end state is identical either way,
            // because MariaDB lets an orphaned trigger be dropped without complaint.
            // Removing the triggers first closes the window in which the column is gone
            // but the triggers still reference it, where an interrupted rollback would
            // leave every later INSERT and UPDATE failing on "Unknown column ... in
            // 'NEW'" with no migration able to repair it.
            if ($this->isMariaDb()) {
                DB::statement("DROP TRIGGER IF EXISTS {$this->insertTrigger()}");
                DB::statement("DROP TRIGGER IF EXISTS {$this->updateTrigger()}");
            }

            $this->dropActiveTokenConstraint();
            $this->restoreLegacyIndex();
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS {$this->activeIndex()}");
            $this->restoreLegacyIndex();
        }
    }

    private function useGeneratedColumn(): void
    {
        DB::statement(<<<SQL
            ALTER TABLE device_installations
                ADD COLUMN {$this->activeColumn()} CHAR(64)
                    GENERATED ALWAYS AS (IF(`status` = 'ACTIVE', `token_fingerprint`, NULL)) STORED,
                ADD UNIQUE KEY {$this->activeIndex()} ({$this->activeColumn()})
            SQL);

        $this->dropLegacyIndex();
    }

    /**
     * MariaDB path.
     *
     * MariaDB rejects `IF`, `CASE`, `NULLIF`, and `CONCAT` in a generated column
     * expression, so the value is maintained by triggers instead. The unique index
     * still performs the enforcement, which keeps the failure a normal integrity
     * violation (SQLSTATE 23000) rather than a bespoke error.
     */
    private function useTriggerMaintainedColumn(): void
    {
        DB::statement(<<<SQL
            ALTER TABLE device_installations
                ADD COLUMN {$this->activeColumn()} CHAR(64) NULL
            SQL);

        $this->addActiveTokenTriggers();

        DB::statement(<<<SQL
            ALTER TABLE device_installations
                ADD UNIQUE KEY {$this->activeIndex()} ({$this->activeColumn()})
            SQL);

        // Rows written before the triggers existed must be backfilled.
        DB::statement(<<<SQL
            UPDATE device_installations
            SET {$this->activeColumn()} = IF(`status` = 'ACTIVE', `token_fingerprint`, NULL)
            SQL);

        $this->dropLegacyIndex();
    }

    private function addActiveTokenTriggers(): void
    {
        $expression = "SET NEW.{$this->activeColumn()} = IF(NEW.status = 'ACTIVE', NEW.token_fingerprint, NULL)";

        DB::statement(<<<SQL
            CREATE TRIGGER {$this->insertTrigger()}
            BEFORE INSERT ON device_installations
            FOR EACH ROW
            {$expression}
            SQL);

        DB::statement(<<<SQL
            CREATE TRIGGER {$this->updateTrigger()}
            BEFORE UPDATE ON device_installations
            FOR EACH ROW
            {$expression}
            SQL);
    }

    private function usePartialIndex(): void
    {
        DB::statement(<<<SQL
            CREATE UNIQUE INDEX {$this->activeIndex()}
                ON device_installations (token_fingerprint)
                WHERE status = 'ACTIVE'
            SQL);

        $this->dropLegacyIndex();
    }

    private function dropActiveTokenConstraint(): void
    {
        DB::statement("ALTER TABLE device_installations DROP INDEX {$this->activeIndex()}");
        DB::statement("ALTER TABLE device_installations DROP COLUMN {$this->activeColumn()}");
    }

    private function dropLegacyIndex(): void
    {
        // SQLite has no `ALTER TABLE ... DROP INDEX`; the index is dropped on
        // its own. MySQL and MariaDB drop it from the table definition.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("DROP INDEX IF EXISTS {$this->legacyIndex()}");

            return;
        }

        DB::statement("ALTER TABLE device_installations DROP INDEX {$this->legacyIndex()}");
    }

    private function restoreLegacyIndex(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<SQL
                CREATE UNIQUE INDEX {$this->legacyIndex()}
                    ON device_installations (token_fingerprint)
                SQL);

            return;
        }

        DB::statement(<<<SQL
            ALTER TABLE device_installations
                ADD UNIQUE {$this->legacyIndex()} (token_fingerprint)
            SQL);
    }

    /**
     * Reports whether the ACTIVE-only constraint is *whole*, not merely present.
     *
     * The column on its own is not the constraint. A unique index over a column that is
     * never populated fires on nothing, so a schema missing either the index or the
     * triggers enforces nothing while still looking applied. Treating that as done would
     * record the migration as applied over a table that happily accepts two ACTIVE owners
     * of one token, which is the exact guarantee this migration exists to add.
     *
     * An incomplete schema therefore falls through and re-runs the steps, where the
     * duplicated object is reported by the engine instead of being skipped over.
     */
    private function hasActiveTokenConstraint(): bool
    {
        return match (DB::getDriverName()) {
            'mysql' => $this->hasCompleteGeneratedColumnConstraint(),
            'sqlite' => DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type = 'index' AND name = ?",
                [$this->activeIndex()],
            ) !== null,
            default => false,
        };
    }

    private function hasCompleteGeneratedColumnConstraint(): bool
    {
        if (! Schema::hasColumn('device_installations', $this->activeColumn())) {
            return false;
        }

        if (! Schema::hasIndex('device_installations', $this->activeIndex())) {
            return false;
        }

        // MySQL 8 computes the column itself and creates no triggers, so they are only
        // part of the constraint on the MariaDB path that populates it by hand.
        return ! $this->isMariaDb() || $this->hasActiveTokenTriggers();
    }

    private function hasActiveTokenTriggers(): bool
    {
        $found = DB::select(
            'SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
             WHERE TRIGGER_SCHEMA = DATABASE()
               AND EVENT_OBJECT_TABLE = ?
               AND TRIGGER_NAME IN (?, ?)',
            ['device_installations', $this->insertTrigger(), $this->updateTrigger()],
        );

        return count($found) === 2;
    }

    /**
     * Rollback would reintroduce global uniqueness, which cannot hold once two retired
     * rows legitimately share a fingerprint. Refusing is safer than deleting history to
     * make a rollback succeed.
     *
     * This refusal is intentional and the remediation is always forward. If this
     * method throws during `migrate:rollback`, leave the migration applied: the
     * constraint is a deliberate strengthening, and a new forward migration is
     * the only way to change it. See `docs/decisions.md` ADR/BACKEND-057.
     */
    private function assertNoSharedFingerprints(): void
    {
        $shared = DB::table('device_installations')
            ->select('token_fingerprint')
            ->groupBy('token_fingerprint')
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->exists();

        if ($shared) {
            throw new MigrationPreconditionException(
                'Cannot restore global token uniqueness: multiple device_installations rows share a token fingerprint. '
                .'Retired rows are kept for audit, so rolling back would require deleting history.',
            );
        }
    }

    private function isMariaDb(): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $version = DB::selectOne('SELECT VERSION() AS server_version');

        return str_contains((string) ($version->server_version ?? ''), 'MariaDB');
    }

    private function activeIndex(): string
    {
        return self::ACTIVE_INDEX;
    }

    private function activeColumn(): string
    {
        return self::ACTIVE_COLUMN;
    }

    private function legacyIndex(): string
    {
        return self::LEGACY_INDEX;
    }

    private function insertTrigger(): string
    {
        return self::INSERT_TRIGGER;
    }

    private function updateTrigger(): string
    {
        return self::UPDATE_TRIGGER;
    }
};
