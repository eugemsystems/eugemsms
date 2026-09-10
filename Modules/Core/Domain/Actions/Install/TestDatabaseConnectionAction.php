<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\ConnectionResult;
use Modules\Core\Domain\DataObjects\Install\DatabaseCredentialsData;
use Modules\Core\Domain\DataObjects\Install\DatabaseSchemaState;
use Throwable;

/**
 * ACT-TestDatabaseConnection (Book A CORE-01 §3). Connects, checks
 * version, checks the schema is empty or already sERP-managed
 * (BR-CORE-01-004, BR-CORE-01-005, AC-CORE-01-005).
 */
final class TestDatabaseConnectionAction extends Action
{
    protected bool $transactional = false;

    private const PROBE_CONNECTION = 'serp_install_probe';

    public function execute(DatabaseCredentialsData $data): ConnectionResult
    {
        config(['database.connections.'.self::PROBE_CONNECTION => [
            'driver' => $data->driver,
            'host' => $data->host,
            'port' => $data->port,
            'database' => $data->database,
            'username' => $data->username,
            'password' => $data->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ]]);

        try {
            $connection = DB::connection(self::PROBE_CONNECTION);
            $serverVersion = $this->serverVersion($connection, $data->driver);
            $tables = $this->tableNames($connection, $data->driver);

            [$schemaState, $conflicting] = $this->classifySchema($tables);

            return new ConnectionResult(
                connected: true,
                serverVersion: $serverVersion,
                schemaState: $schemaState,
                conflictingTables: $conflicting,
                message: $schemaState === DatabaseSchemaState::Conflicting
                    ? 'The database contains tables from another application: '.implode(', ', $conflicting).'.'
                    : 'Connected successfully.',
            );
        } catch (Throwable $exception) {
            return new ConnectionResult(
                connected: false,
                serverVersion: null,
                schemaState: DatabaseSchemaState::Empty,
                conflictingTables: [],
                message: 'Could not connect: '.$exception->getMessage(),
            );
        } finally {
            DB::purge(self::PROBE_CONNECTION);
        }
    }

    private function serverVersion(Connection $connection, string $driver): ?string
    {
        if ($driver === 'sqlite') {
            $row = $connection->selectOne('select sqlite_version() as version');

            return $row !== null ? (string) $row->version : null;
        }

        $row = $connection->selectOne('select version() as version');

        return $row !== null ? (string) $row->version : null;
    }

    /**
     * @return array<int, string>
     */
    private function tableNames(Connection $connection, string $driver): array
    {
        return match ($driver) {
            'pgsql' => array_map(
                fn (object $row): string => (string) ((array) $row)['tablename'],
                $connection->select("select tablename from pg_tables where schemaname = 'public'"),
            ),
            'sqlite' => array_map(
                fn (object $row): string => (string) ((array) $row)['name'],
                $connection->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"),
            ),
            default => array_map(
                fn (object $row): string => (string) array_values((array) $row)[0],
                $connection->select('show tables'),
            ),
        };
    }

    /**
     * @param  array<int, string>  $tables
     * @return array{0: DatabaseSchemaState, 1: array<int, string>}
     */
    private function classifySchema(array $tables): array
    {
        if ($tables === []) {
            return [DatabaseSchemaState::Empty, []];
        }

        if (in_array('migrations', $tables, true)) {
            return [DatabaseSchemaState::Serp, []];
        }

        return [DatabaseSchemaState::Conflicting, $tables];
    }
}
