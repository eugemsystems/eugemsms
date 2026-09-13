<?php

/**
 * The actual GRANT/REVOKE rebuild only makes sense against a real MySQL
 * server (this suite runs on in-memory SQLite — see phpunit.xml) and
 * was verified manually, end to end, against a disposable MySQL
 * database and a real, narrowly-privileged test user during this
 * command's own development (see its class docblock for what that
 * verification found and why the command is shaped the way it is).
 * These tests cover the two guards that ARE meaningfully testable
 * here: the environment confirmation and the driver check.
 */
it('refuses to run outside production without --force', function (): void {
    $this->artisan('serp:harden-append-only-tables')
        ->assertFailed();
});

it('still refuses on a non-MySQL connection even with --force', function (): void {
    $this->artisan('serp:harden-append-only-tables', ['--force' => true])
        ->assertFailed();
});
