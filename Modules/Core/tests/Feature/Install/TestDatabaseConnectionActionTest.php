<?php

use Modules\Core\Domain\Actions\Install\TestDatabaseConnectionAction;
use Modules\Core\Domain\DataObjects\Install\DatabaseCredentialsData;
use Modules\Core\Domain\DataObjects\Install\DatabaseSchemaState;

function makeProbeSqliteFile(bool $withMigrationsTable): string
{
    $path = tempnam(sys_get_temp_dir(), 'serp_install_probe_').'.sqlite';
    touch($path);

    if ($withMigrationsTable) {
        $pdo = new PDO('sqlite:'.$path);
        $pdo->exec('create table migrations (id integer primary key)');
    }

    return $path;
}

afterEach(function (): void {
    foreach (glob(sys_get_temp_dir().'/serp_install_probe_*.sqlite') ?: [] as $file) {
        @unlink($file);
    }
});

it('recognises a schema with a migrations table as serp-managed', function (): void {
    $path = makeProbeSqliteFile(withMigrationsTable: true);

    $result = (new TestDatabaseConnectionAction)->execute(new DatabaseCredentialsData(
        driver: 'sqlite',
        host: '127.0.0.1',
        port: 3306,
        database: $path,
        username: '',
        password: '',
    ));

    expect($result->connected)->toBeTrue()
        ->and($result->schemaState)->toBe(DatabaseSchemaState::Serp)
        ->and($result->canProceed())->toBeTrue();
});

it('recognises a brand new empty database as empty', function (): void {
    $path = makeProbeSqliteFile(withMigrationsTable: false);

    $result = (new TestDatabaseConnectionAction)->execute(new DatabaseCredentialsData(
        driver: 'sqlite',
        host: '127.0.0.1',
        port: 3306,
        database: $path,
        username: '',
        password: '',
    ));

    expect($result->connected)->toBeTrue()
        ->and($result->schemaState)->toBe(DatabaseSchemaState::Empty)
        ->and($result->canProceed())->toBeTrue();
});

it('rejects a schema containing tables from another application', function (): void {
    $path = makeProbeSqliteFile(withMigrationsTable: false);
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec('create table wp_posts (id integer primary key)');

    $result = (new TestDatabaseConnectionAction)->execute(new DatabaseCredentialsData(
        driver: 'sqlite',
        host: '127.0.0.1',
        port: 3306,
        database: $path,
        username: '',
        password: '',
    ));

    expect($result->connected)->toBeTrue()
        ->and($result->schemaState)->toBe(DatabaseSchemaState::Conflicting)
        ->and($result->conflictingTables)->toBe(['wp_posts'])
        ->and($result->canProceed())->toBeFalse();
});

it('reports an unreachable database as not connected', function (): void {
    $result = (new TestDatabaseConnectionAction)->execute(new DatabaseCredentialsData(
        driver: 'sqlite',
        host: '127.0.0.1',
        port: 3306,
        database: '/nonexistent/path/does-not-exist.sqlite',
        username: '',
        password: '',
    ));

    expect($result->connected)->toBeFalse()
        ->and($result->canProceed())->toBeFalse();
});
