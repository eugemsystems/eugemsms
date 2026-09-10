---
paths:
  - 'Modules/*/tests/**'
---

# Tests

## Testing abort()/abort_unless() inside a Livewire component's mount()
An `abort_unless($condition, 403)` (or any `abort()`) thrown inside a Livewire full-page component's `mount()` does NOT propagate as a catchable PHP exception to `Livewire::test(...)` — Livewire's `Testable` routes it through the same exception-to-response pipeline a real HTTP request would use, so you get back an ordinary response object, not a thrown exception.

Assert on it the way you'd assert an HTTP response: `Livewire::test(Component::class, [...])->assertForbidden()` (or `assertStatus(403)`, `assertNotFound()`, etc.) — these work because `Testable::__call()` proxies unknown method calls straight to the underlying `TestResponse` (`vendor/livewire/livewire/src/Features/SupportTesting/Testable.php`). A `try { Livewire::test(...); } catch (HttpException $e) { ... }` pattern will silently never catch anything and the test will wrongly report the abort didn't fire.

Also: `Testable::create()` builds the component lazily — nothing in `mount()` actually runs until you interrogate the Testable (e.g. `->assertForbidden()`, `->assertSee()`, `->html()`). A bare `Livewire::test($component, [...]);` with no following assertion executes nothing.

## Finance/tenant-scoped tests must call SchoolContext::set() in fixtures
Any model using `BelongsToSchool` silently returns an EMPTY result set on every read (not an exception) when no ambient `SchoolContext` is set — `SchoolScope` just filters to nothing. Writes via explicit `.for($school)`/`school_id` succeed fine without context, which masks the bug until the first `->load()`, `with()`, `findOrFail()`, or relation access, which then fails confusingly (e.g. "property on null", "No query results for model X" even though the row exists). Real requests always have context via CORE-02's tenant-resolution middleware — tests must set it manually: call `SchoolContext::set($school)` inside the test's fixture/setup helper, not just pass `school_id` to factories. Hit repeatedly in Book B's FIN-01 ledger tests (`Modules/Finance/tests/Feature/Ledger/*`); also add `created_at` to any model's `$fillable` when `$timestamps = false` and the migration marks `created_at` NOT NULL — mass-assignment silently drops unlisted keys, so `new Model(['created_at' => now(), ...])` inserts NULL if `created_at` isn't fillable.

## allocated_numbers is unique per (school_id, formatted_number) — not per document_type
CORE-06's `allocated_numbers` table has a unique constraint on `(school_id, formatted_number)` only — `document_type` is not part of it. If a test registers the same numbering pattern (e.g. bare `'{SEQ:6}'`) for two different document types on the same school, the first allocation of each type produces the identical formatted string ("000001") and the second insert throws a unique-constraint violation that looks unrelated to the code under test.

Always give each document type its own prefix in test fixtures, e.g. `'ADM/{SEQ:6}'`, `'APP/{SEQ:6}'`, `'RCT/{SEQ:6}'`, `'JNL/{SEQ:6}'` — matching the convention already used in Finance's `fin04Fixture()`/`fin05Fixture()`.

## A new nwidart module's test directories must be added to BOTH tests/Pest.php and phpunit.xml
Creating a new module (`php artisan module:make X`) does not register its `tests/Feature`/`tests/Unit` directories anywhere automatically. Two separate files both need a manual edit, and missing either produces a different failure mode:

- `tests/Pest.php`'s `pest()->extend(...)->use(RefreshDatabase::class)->in(...)` list — miss this and every test in the new module's Feature dir fails at the very first Eloquent call with "Call to a member function connection() on null" (no DB/RefreshDatabase wiring at all), which looks like a totally unrelated bug.
- `phpunit.xml`'s `<testsuite>` `<directory>` entries — miss this and `php artisan test` / `vendor/bin/pest` (which read phpunit.xml's suite definitions, not Pest.php's `.in()` calls) silently skip the new module's tests entirely with no error — they just don't run, and the total test count doesn't include them.

Also add the new module's `Domain`/`Http`/`Models`/`Providers` dirs to phpunet.xml's `<source><include>` list for coverage, mirroring the existing per-module entries (missing `Http` is fine if the module has none — PHPUnit ignores a listed directory that doesn't exist).

## Give every numbering-series prefix in a test fixture an explicit, distinct value
`allocated_numbers` is unique on `(school_id, formatted_number)` only, not `document_type` (already recorded — see [[tests]]). A second failure mode of the same root cause: deriving a numbering-series prefix from the document type string itself (e.g. `strtoupper(substr($documentType, 0, 3))`) silently collides whenever two document types share the same first three letters — `payroll_run` and `payslip` both produce `PAY`, so the first allocation of each type both format to `PAY/000001` and the second insert throws a unique-constraint violation that looks unrelated to the code under test.

Always spell out an explicit `documentType => prefix` map in the fixture (e.g. `['payroll_run' => 'RUN', 'payslip' => 'PSL']`) rather than deriving the prefix programmatically from the type string.

## Pest cross-file helper functions aren't available when a test file runs alone
A plain PHP function (e.g. `ppl04Fixture()`) defined at the top of one Pest test file is only callable from another file if BOTH happen to be loaded in the same run. Running the full suite loads every file first, so it can look like sharing works — but `php artisan test --compact path/to/OneFile.php` (or any path-scoped run) loads only that file, and every call to the other file's helper fails with "Call to undefined function".

Rule: never assume a helper defined in another test file is callable. Either duplicate the fixture/helper under a distinct name in the new file (suffix it, e.g. `ppl04bFixture()`/`createTeachingStaffB()`, to avoid a "cannot redeclare function" fatal when the full suite later loads both files together), or promote genuinely shared helpers into `tests/Pest.php`'s own function section.
