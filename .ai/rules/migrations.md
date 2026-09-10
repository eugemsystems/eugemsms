---
paths:
  - 'Modules/*/database/migrations/**'
---

# Migrations

## MySQL 64-char identifier limit on multi-column indexes/uniques
Laravel auto-generates index/unique/FK names as `{table}_{col1}_{col2}..._{type}`. On a long table name (e.g. `personal_access_tokens`, `feature_flag_overrides`) plus 2-3 columns this silently exceeds MySQL's 64-char identifier limit and throws error 1059 — SQLite doesn't enforce this, so it can pass in Pest (sqlite) and only fail against the real MySQL dev DB. Always pass an explicit short name as the last arg to `$table->unique([...], 'name')` / `->index([...], 'name')` when the table name is long or there are 3+ columns. If a migration partially runs before failing (Schema::create commits before a later `alter table add index` fails), the table exists but the migration isn't marked run — drop it manually (`Schema::dropIfExists`) before re-running `migrate`.

## Schema::table()->change() works without doctrine/dbal on Laravel 13
This app's Laravel 13 no longer requires the doctrine/dbal package for `$table->column(...)->change()` (older-Laravel-version folklore says it's required — verified false here on both MySQL and SQLite). Use it freely for nullability/type changes on existing columns instead of writing raw DB::statement() per-driver or avoiding schema changes altogether.

## Append-only tables: DB-level grant revocation is a deployment task, not a migration
CLAUDE.md requires financial/safeguarding tables to be append-only "at the database grant level, not by convention." REVOKE UPDATE/DELETE requires knowing the actual production app DB user name (environment-specific) and superuser privileges the migration connection may not have — running it from a portable Laravel migration risks locking the shared dev DB user out of the table for ALL future migrations too. Pattern used for financial_audit_log (CORE-08): enforce append-only at the model level instead (booted() throwing on updating/deleting, same as PeriodSnapshot from CORE-03), and leave the actual REVOKE statement as a documented ops/deployment step for each real environment. Follow this same split for any future append-only table.

## Number a new module's first migration after every existing module's last one
Laravel runs migrations in filename order across ALL directories combined (Modules/*/database/migrations plus the root database/migrations), not per-module. Starting a new module's migrations at `0001_01_01_...` (mirroring how Core itself started) makes them sort BEFORE other modules' later migrations and even before Core's own foundational tables (users, schools, settings) that a new module's FKs need — every FK'd table must already exist. Always check `ls Modules/*/database/migrations | sort | tail -5` across ALL modules first and number the new module's migrations to sort strictly after the highest one found (e.g. Finance ended at 0018, so People started at 0019). Hit for both Finance (FIN-01) and People (PPL-01); if caught only after migrating, see `.ai/rules/migrations.md`'s note on cleaning up a partially-run migration under the wrong name before renaming and re-running.

## MySQL 64-char index-name limit: name any 3+ column index/unique explicitly
Laravel's auto-generated index name is `{table}_{col1}_{col2}_..._index|unique`. On MySQL (this project's driver) identifiers over 64 chars fail with "Identifier name ... is too long" — and it fails on the ALTER TABLE ADD INDEX statement *after* CREATE TABLE has already succeeded, so a failed migration leaves the table behind; re-running migrate then fails again with "table already exists" until you manually `Schema::dropIfExists()` the orphaned table first.

This had already bitten 7 migrations across Academic/Core/Finance before anyone ran `php artisan migrate` against real MySQL (Pest's tests use SQLite, which has no such limit, so it went undetected for a long time).

Rule: whenever an `->index([...])` or `->unique([...])` call has 3+ columns (or otherwise looks like it might be long), pass an explicit short name as the second argument: `$table->index([...], 'short_table_cols_idx')`. Don't rely on the default and don't discover this by running migrate — check it at write time.

## Encrypted decimal/BIGINT spec columns need TEXT, not the spec's literal width
A spec column marked `-- ENCRYPTED` next to a narrow width (`VARCHAR(30)`, or worse `BIGINT`) is sized for the plaintext, not for what Laravel's `encrypted` Eloquent cast actually stores. That cast serializes then encrypts, producing base64 ciphertext + IV + auth tag — routinely 100-300+ characters even for a short value or a small integer, and always a string, never fitting a numeric column type at all.

Rule: any migration column the spec marks ENCRYPTED must be `$table->text(...)`, regardless of what width or type the spec's literal SQL shows. Note the deviation inline in the migration's own docblock (this project's established practice for any spec correction) rather than silently diverging. Seen so far: `staff.national_registration_no`/`passport_no`/`zimra_bp_number`/`nssa_number`/`medical_aid_number`/`bank_account_number`, `staff_contracts.basic_salary_minor` (spec: `BIGINT ENCRYPTED`).

## Order migrations within a batch by FK dependency, not just spec order
The dev DB is real MySQL (not sqlite), so `$table->foreignId(...)->constrained(...)` fails at migrate time with "Failed to open the referenced table" if the referenced table's own migration hasn't run yet — MySQL enforces FKs at DDL time, sqlite (used in Pest) does not, so this only surfaces against the real dev DB. A spec often lists tables in a different order than their FK dependencies require (e.g. Book F lists `roll_call_points` before `escalation_profiles`, but `roll_call_points.escalation_profile_id` references it) — number migration files by dependency order, not by the spec's own table listing order. If `php artisan migrate` fails partway through a batch, the failed CREATE TABLE may still have run (MySQL DDL isn't transactional) even though it's not recorded in the `migrations` table as run — check with `Schema::hasTable(...)` and `Schema::dropIfExists(...)` the orphaned table before re-running `migrate`, or the retry fails again with "table already exists".
