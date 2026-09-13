<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Support\Backups\OwnedTableScanner;

/**
 * `php artisan serp:harden-append-only-tables` (Book A Volume 1's
 * append-only-at-the-database-grant-level doctrine; BR-CORE-08-005,
 * safeguarding's own equivalent, and the Book A Acceptance Gate's
 * "UPDATE and DELETE confirmed revoked on financial_audit_log and
 * period_state_transitions for the application database user").
 *
 * Every one of these tables already refuses an update/delete at the
 * Eloquent layer (`static::updating()`/`static::deleting()` throwing
 * `InvalidStateTransitionException` — see `FinancialAuditLogEntry`'s
 * own docblock) — that's the layer this codebase's own tests can
 * exercise. This command is the OTHER layer: the same protection at
 * the database grant itself, so a raw query, a compromised application
 * process, or a bug that bypasses Eloquent entirely still can't touch
 * these rows.
 *
 * **Why this rebuilds every grant from scratch rather than a plain
 * `REVOKE UPDATE, DELETE ON db.append_only_table FROM user`.** MySQL's
 * privileges are purely additive across global/database/table scope —
 * there is no "deny" that overrides a broader grant, and a table-level
 * `REVOKE` only removes a privilege that was itself recorded at the
 * table level; it errors with "1147: no such grant defined" against
 * the single most common real setup, a database-level
 * `GRANT ALL ON db.* TO app_user` (confirmed against a real, narrowly-
 * privileged MySQL test user during this command's own development —
 * the naive table-level REVOKE failed exactly this way). The only
 * MySQL-correct way to make three tables genuinely exempt from
 * UPDATE/DELETE while the app can still write everywhere else is to
 * revoke everything and re-grant SELECT/INSERT database-wide plus
 * UPDATE/DELETE per table, excluding the append-only ones —
 * `OwnedTableScanner::names()` (already this app's own canonical
 * "every table we own" list, built for `CreateBackupAction`) is exactly
 * the enumeration this needs, so no second hand-kept table list drifts
 * against it.
 *
 * Never run from a migration — the database user's exact name/host and
 * grant model differ per deployment, and issuing this mid-migration-run
 * risks a LATER migration in the same run losing privileges it still
 * needs (a new table an in-progress migration run hasn't created yet
 * obviously can't appear in this run's own table enumeration either).
 * This is a deliberate, operator-run deployment step: run once after
 * provisioning the production database user and again after any
 * schema change that adds tables, so the re-grant picks up new tables.
 *
 * Refuses to run against `local`/`testing` without `--force`, the same
 * guard Laravel's own destructive commands (`migrate:fresh` in
 * production, etc.) use.
 *
 * **Must run as an account that already holds `GRANT OPTION`** (an
 * admin/root connection), never as the application's own runtime user
 * — MySQL's `1142 GRANT command denied` is exactly this command
 * refusing to let a user revoke its own privileges, which is correct.
 * `--user` names the TARGET to re-provision (the application's runtime
 * username), defaulting to the current connection's own configured
 * username only as a convenience for the — usually local/staging-only —
 * case where the account running this happens to be the same as the
 * app's. `--host` is the other half of the `user@host` pair MySQL keys
 * grants on; it must match whatever `mysql.user` already has a row for.
 */
final class HardenAppendOnlyTablesCommand extends Command
{
    protected $signature = 'serp:harden-append-only-tables
        {--force : Run even in a non-production environment}
        {--user= : The application database username to re-provision — defaults to the current connection\'s own configured username, but a real deployment almost always needs this passed explicitly (see this class\'s own docblock)}
        {--host=% : The host part of the user@host grant pair}';

    protected $description = 'Rebuild the application database user\'s grants so it can read/write everywhere except the append-only audit tables.';

    /**
     * @var array<int, string>
     */
    private const array APPEND_ONLY_TABLES = [
        'financial_audit_log',
        'period_state_transitions',
        'safeguarding_audit',
    ];

    public function handle(): int
    {
        if (! app()->environment('production') && ! $this->option('force')) {
            $this->components->error('Refusing to run outside production without --force — this rebuilds real database grants, not application state.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver !== 'mysql') {
            $this->components->error("This command only knows the GRANT/REVOKE syntax for MySQL/MariaDB; the current connection driver is [{$driver}].");

            return self::FAILURE;
        }

        /** @var string|null $username */
        $username = $this->option('user') ?? $connection->getConfig('username');

        if (! is_string($username) || $username === '') {
            $this->components->error('No --user given and the current database connection has no configured username either.');

            return self::FAILURE;
        }

        $pdo = $connection->getPdo();

        // GRANT/REVOKE is DCL, not DML — MySQL does not accept a bound
        // placeholder inside the `user@host` clause, so both are quoted
        // as SQL string literals via the PDO driver itself (not
        // string-interpolated as user input would be) and then embedded
        // directly, the safest option this statement type allows.
        $quotedUsername = $pdo->quote($username);
        $host = (string) $this->option('host');
        $quotedHost = $pdo->quote($host);
        $userAtHost = "{$quotedUsername}@{$quotedHost}";
        $database = $connection->getDatabaseName();

        $connection->statement("REVOKE ALL PRIVILEGES, GRANT OPTION FROM {$userAtHost}");
        $connection->statement("GRANT SELECT, INSERT ON `{$database}`.* TO {$userAtHost}");
        $this->components->info("Reset [{$username}@{$host}] to SELECT, INSERT on every table in [{$database}].");

        $writableCount = 0;

        foreach (OwnedTableScanner::names() as $table) {
            if (in_array($table, self::APPEND_ONLY_TABLES, true) || ! $this->tableExists($table)) {
                continue;
            }

            $connection->statement("GRANT UPDATE, DELETE ON `{$database}`.`{$table}` TO {$userAtHost}");
            $writableCount++;
        }

        $connection->statement('FLUSH PRIVILEGES');

        $this->components->info("Granted UPDATE, DELETE on {$writableCount} table(s), excluding: ".implode(', ', self::APPEND_ONLY_TABLES).'.');

        return self::SUCCESS;
    }

    private function tableExists(string $table): bool
    {
        return DB::connection()->getSchemaBuilder()->hasTable($table);
    }
}
