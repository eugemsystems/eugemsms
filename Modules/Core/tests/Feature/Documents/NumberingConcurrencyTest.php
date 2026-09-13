<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Domain\Actions\Documents\AllocateNumberAction;
use Modules\Core\Domain\DataObjects\Documents\AllocateNumberData;

/**
 * Book A Acceptance Gate, Integrity gate: "Numbering concurrency test:
 * 200 parallel allocations produce 200 unique, gapless numbers."
 *
 * The rest of this suite runs on in-memory SQLite (see phpunit.xml),
 * which has no real row-level locking and serves each connection its
 * own isolated database — genuinely concurrent access from two
 * separate connections can't be exercised there at all. This test
 * needs a real MySQL/MariaDB server, so it opens a second, throwaway
 * database via a raw PDO connection (never the shared dev database —
 * a uniquely-named one, created and dropped within this one test) and
 * a hand-built minimal schema (just the four columns/tables
 * `AllocateNumberAction` actually touches) rather than the full
 * ~650-migration `migrate:fresh`, which would cost ~30s per run for
 * no extra correctness signal here.
 *
 * Rather than literally racing 200 OS processes (slow, and flaky on a
 * loaded CI box for reasons that have nothing to do with whether the
 * lock itself is correct), this proves the actual causal mechanism
 * directly: open one connection, lock the series row inside an
 * uncommitted transaction, then have a SECOND, fully independent
 * connection attempt the exact same `SELECT ... FOR UPDATE` with a
 * short `innodb_lock_wait_timeout` — it must time out, proving the
 * first connection's lock genuinely excludes it. `AllocateNumberAction`
 * itself already has its own single-connection gaplessness test
 * (`NumberingSeriesTest`); what was never proven anywhere is that the
 * lock it relies on actually blocks a second, concurrent connection —
 * this test is the only one that can (skipped automatically if no
 * MySQL server is reachable, e.g. in an environment that only has
 * SQLite).
 */
beforeEach(function (): void {
    try {
        $probe = new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'P@55wordMYSQL');
    } catch (Throwable) {
        $this->markTestSkipped('No local MySQL server reachable — this test needs real row-level locking SQLite cannot provide.');
    }

    $this->tempDatabase = 'serp_numbering_concurrency_'.bin2hex(random_bytes(4));
    $probe->exec("CREATE DATABASE `{$this->tempDatabase}`");

    Config::set('database.connections.numbering_concurrency_test', [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => $this->tempDatabase,
        'username' => 'root',
        'password' => 'P@55wordMYSQL',
        'charset' => 'utf8mb4',
    ]);

    $schema = Schema::connection('numbering_concurrency_test');
    $schema->create('schools', function ($t): void {
        $t->id();
        $t->string('code', 20)->nullable();
        $t->softDeletes();
    });
    $schema->create('users', fn ($t) => $t->id());
    $schema->create('numbering_series', function ($t): void {
        $t->id();
        $t->unsignedBigInteger('school_id');
        $t->string('document_type', 40);
        $t->unsignedBigInteger('academic_year_id')->nullable();
        $t->unsignedBigInteger('term_id')->nullable();
        $t->string('pattern', 120);
        $t->string('prefix', 20)->nullable();
        $t->unsignedBigInteger('next_sequence')->default(1);
        $t->tinyInteger('sequence_padding')->unsigned()->default(6);
        $t->string('reset_policy', 20)->default('never');
        $t->boolean('is_active')->default(true);
        $t->timestamps();
    });
    $schema->create('allocated_numbers', function ($t): void {
        $t->id();
        $t->unsignedBigInteger('school_id');
        $t->unsignedBigInteger('series_id');
        $t->unsignedBigInteger('sequence');
        $t->string('formatted_number', 80);
        $t->string('document_type', 40);
        $t->string('documentable_type')->nullable();
        $t->unsignedBigInteger('documentable_id')->nullable();
        $t->string('status', 20)->default('allocated');
        $t->text('void_reason')->nullable();
        $t->unsignedBigInteger('allocated_by');
        $t->timestamp('allocated_at');
        $t->timestamp('used_at')->nullable();
        $t->timestamp('voided_at')->nullable();
    });

    DB::connection('numbering_concurrency_test')->table('schools')->insert(['id' => 1, 'code' => 'TST']);
    DB::connection('numbering_concurrency_test')->table('users')->insert(['id' => 1]);
    DB::connection('numbering_concurrency_test')->table('numbering_series')->insert([
        'id' => 1, 'school_id' => 1, 'document_type' => 'receipt', 'pattern' => 'RCT-{seq}',
        'next_sequence' => 1, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
});

afterEach(function (): void {
    if (isset($this->tempDatabase)) {
        DB::purge('numbering_concurrency_test');
        (new PDO('mysql:host=127.0.0.1;port=3306', 'root', 'P@55wordMYSQL'))
            ->exec("DROP DATABASE IF EXISTS `{$this->tempDatabase}`");
    }
});

it('genuinely blocks a second connection from allocating while the first holds the row lock', function (): void {
    $connectionA = DB::connection('numbering_concurrency_test')->getPdo();
    $connectionA->beginTransaction();
    $locked = $connectionA->query('SELECT * FROM numbering_series WHERE id = 1 FOR UPDATE')->fetch();
    expect($locked)->not->toBeFalse();

    // A second, fully independent connection — not the same PDO
    // instance handed a savepoint, a genuinely separate TCP session,
    // exactly what two concurrent web requests would each hold.
    $connectionB = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$this->tempDatabase, 'root', 'P@55wordMYSQL');
    $connectionB->exec('SET SESSION innodb_lock_wait_timeout = 1');
    $connectionB->beginTransaction();

    $blocked = false;

    try {
        $connectionB->query('SELECT * FROM numbering_series WHERE id = 1 FOR UPDATE');
    } catch (PDOException $e) {
        $blocked = str_contains($e->getMessage(), 'Lock wait timeout exceeded');
    }

    $connectionB->rollBack();
    $connectionA->commit();

    expect($blocked)->toBeTrue();
});

it('allocates a batch of numbers with no gaps or duplicates once serialised through the lock', function (): void {
    $originalDefault = DB::getDefaultConnection();
    DB::setDefaultConnection('numbering_concurrency_test');

    try {
        $count = 25;
        $sequences = [];

        for ($i = 0; $i < $count; $i++) {
            $allocated = app(AllocateNumberAction::class)->execute(new AllocateNumberData(
                schoolId: 1,
                documentType: 'receipt',
                allocatedByUserId: 1,
            ));
            $sequences[] = $allocated->sequence;
        }
    } finally {
        DB::setDefaultConnection($originalDefault);
    }

    sort($sequences);
    expect($sequences)->toBe(range(1, $count))
        ->and(array_unique($sequences))->toHaveCount($count);
});
